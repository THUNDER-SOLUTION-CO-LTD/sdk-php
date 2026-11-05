<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Thunder\Client;
use Thunder\Exception\{BatchSubmitError, JobWaitTimeoutError};
use Thunder\Input\{BatchSlip, ListBankAccountsParams, WaitOptions};

final class HelpersTest extends TestCase
{
    private function transport(array $responses): ClientInterface
    {
        return new class ($responses) implements ClientInterface {
            public array $requests = [];
            public function __construct(private array $responses) {}
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request;
                if (!$this->responses) {
                    throw new \LogicException('Unexpected extra request');
                }
                return array_shift($this->responses);
            }
        };
    }
    private function response(array $data): Response
    {
        return new Response(200, [], json_encode(['data' => $data], JSON_THROW_ON_ERROR));
    }
    private function account(int $id): array
    {
        return ['id' => $id,'bankCode' => '004','bankNumber' => '123','nameTh' => 'ทดสอบ','nameEn' => 'Test','type' => 'NATURAL','createdAt' => '2026-10-29','updatedAt' => '2026-10-29'];
    }
    public function testPaginationPreservesFilterAndStopsAtTotal(): void
    {
        $http = $this->transport([$this->response(['items' => [$this->account(1)],'total' => 2,'page' => 1,'limit' => 1]),$this->response(['items' => [$this->account(2)],'total' => 2,'page' => 2,'limit' => 1])]);
        $client = new Client(apiKey: 'fake', httpClient: $http);
        $accounts = iterator_to_array($client->bankAccounts->iterateAll(new ListBankAccountsParams(bankCode: '004', limit: 1)), false);
        self::assertSame([1,2], array_column($accounts, 'id'));
        parse_str($http->requests[1]->getUri()->getQuery(), $query);
        self::assertSame(['bankCode' => '004','page' => '2','limit' => '1'], $query);
    }
    public function testChunksAt100AndRetainsSuccessfulBatchesOnFailure(): void
    {
        $http = $this->transport([$this->response(['batchId' => 'first','jobs' => []]),new Response(503, [], '{"code":"SERVICE_UNAVAILABLE"}')]);
        $client = new Client(apiKey: 'fake', httpClient: $http, maxRetries: 0);
        try {
            $client->jobs->submitBankMany(array_fill(0, 101, new BatchSlip('synthetic')));
            self::fail('Must report partial submission');
        } catch (BatchSubmitError $error) {
            self::assertCount(1, $error->submitted);
            self::assertSame('first', $error->submitted[0]->batchId);
            self::assertSame(1, $error->failedChunk);
        }
        self::assertSame([100,1], array_map(static fn($r) => count(json_decode((string) $r->getBody(), true)['slips']), $http->requests));
    }
    public function testWaitReturnsBothTerminalStatesAndTimeoutRetainsLastJob(): void
    {
        foreach (['done','failed'] as $status) {
            $http = $this->transport([$this->response($this->job($status))]);
            self::assertSame($status, (new Client(apiKey: 'fake', httpClient: $http))->jobs->waitForJob('job')->status);
        }
        $http = $this->transport([$this->response($this->job('queued'))]);
        try {
            (new Client(apiKey: 'fake', httpClient: $http))->jobs->waitForJob('job', new WaitOptions(timeout: 0.01));
            self::fail('Must time out');
        } catch (JobWaitTimeoutError $error) {
            self::assertSame('queued', $error->lastJob?->status);
        }
    }
    private function job(string $status): array
    {
        return ['jobId' => 'job','status' => $status,'attempts' => 1,'createdAt' => '2026-10-29','updatedAt' => '2026-10-29'];
    }
}

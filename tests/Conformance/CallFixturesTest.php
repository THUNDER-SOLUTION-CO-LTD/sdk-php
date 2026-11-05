<?php

declare(strict_types=1);

namespace Thunder\Tests\Conformance;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Thunder\Client;
use Thunder\Enum\{AccountType, MatchMode, QrType};
use Thunder\Exception\ApiError;
use Thunder\Input\{AsyncVerifyOptions, BatchOptions, BatchSlip, CreateBankAccountParams, FileInput, ListBankAccountsParams, QrRequest, UpdateBankAccountParams, V1VerifyOptions, VerifyOptions};

final class CallFixturesTest extends TestCase
{
    public function testEveryCallFixtureThroughPublicClient(): void
    {
        $count = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../fixtures')) as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }
            $f = json_decode(file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
            if (($f['conformance'] ?? '') !== 'call') {
                continue;
            }
            $http = new class ($f['response']) implements ClientInterface {
                public ?RequestInterface $request = null;
                public function __construct(private array $response) {}
                public function sendRequest(RequestInterface $request): ResponseInterface
                {
                    $this->request = $request;
                    return new Response($this->response['status'], $this->response['headers'], json_encode($this->response['body'], JSON_THROW_ON_ERROR));
                }
            };
            $c = new Client(apiKey: 'fixture-key', v1BaseUrl: 'https://unit.test', v2BaseUrl: 'https://unit.test', maxRetries: 0, httpClient: $http);
            try {
                $result = $this->invoke($c, $f['operation'], $f['call']['args']);
                self::assertArrayHasKey('model', $f['expect'], $f['id']);
                self::assertInstanceOf('Thunder\\Model\\' . $f['expect']['model'], $result, $f['id']);
                self::assertSame($f['response']['body']['data'], $result->jsonSerialize(), $f['id']);
            } catch (ApiError $e) {
                self::assertSame($f['response']['status'], $e->status, $f['id']);
                self::assertGreaterThanOrEqual(400, $e->status, $f['id']);
            }
            $r = $http->request;
            self::assertNotNull($r, $f['id']);
            self::assertSame($f['request']['method'], $r->getMethod(), $f['id']);
            self::assertSame($f['request']['path'], $r->getUri()->getPath(), $f['id']);
            self::assertSame('Bearer fixture-key', $r->getHeaderLine('Authorization'));
            parse_str($r->getUri()->getQuery(), $query);
            self::assertEquals($f['request']['query'] ?? [], $query, $f['id']);
            if (isset($f['request']['body'])) {
                self::assertEquals($f['request']['body'], json_decode((string) $r->getBody(), true), $f['id']);
            }
            if (isset($f['request']['multipart'])) {
                $body = (string) $r->getBody();
                foreach ($f['request']['multipart']['fields'] as $key => $value) {
                    self::assertMatchesRegularExpression('/name="' . preg_quote($key, '/') . '"[^\r\n]*\r\n(?:[^\r\n]+\r\n)*\r\n' . preg_quote((string) $value, '/') . '\r\n/', $body, $f['id']);
                }
                foreach ($f['request']['multipart']['files'] as $part) {
                    $bytes = file_get_contents(__DIR__ . '/../fixtures/' . $part['file']);
                    self::assertSame($part['sha256'], hash('sha256', $bytes));
                    self::assertSame($part['size'], strlen($bytes));
                    self::assertStringContainsString('name="' . $part['field'] . '"; filename="' . $part['filename'] . '"', $body);
                    self::assertStringContainsString('Content-Type: ' . $part['contentType'], $body);
                    self::assertStringContainsString($bytes, $body);
                }
            }
            ++$count;
        }
        self::assertGreaterThan(40, $count);
    }

    private function invoke(Client $c, string $op, array $a): object
    {
        if (isset($a['type'])) {
            $a['type'] = str_starts_with($op, 'bankAccounts') ? AccountType::from($a['type']) : QrType::from($a['type']);
        }
        if (isset($a['matchMode'])) {
            $a['matchMode'] = MatchMode::from($a['matchMode']);
        }
        if ($op === 'getInfo') {
            return $c->getInfo();
        }
        if ($op === 'banks.list') {
            return $c->banks->list();
        }
        if ($op === 'v1.getMe') {
            return $c->v1->getMe();
        }
        if ($op === 'v1.generateQr') {
            return $c->v1->generateQr(new QrRequest(...$a));
        }
        [$resource,$method] = explode('.', $op);
        if ($resource === 'bankAccounts') {
            $id = $a['id'] ?? null;
            unset($a['id']);
            return match ($method) {
                'get','link','unlink' => $c->bankAccounts->$method($id),
                'create' => $c->bankAccounts->create(new CreateBankAccountParams(...$a)),
                'update' => $c->bankAccounts->update($id, new UpdateBankAccountParams(...$a)),
                'list','listAll' => $c->bankAccounts->$method(new ListBankAccountsParams(...$a)),
            };
        }
        if ($resource === 'jobs') {
            if ($method === 'get') {
                return $c->jobs->get($a['jobId']);
            }
            if ($method === 'submitBankBatch') {
                $slips = array_map(fn($s) => new BatchSlip(...$s), $a['slips']);
                unset($a['slips']);
                return $c->jobs->submitBankBatch($slips, new BatchOptions(...$a));
            }
            $payload = $a['payload'];
            unset($a['payload']);
            return $c->jobs->submitBank($payload, new AsyncVerifyOptions(...$a));
        }
        $key = match (true) {
            str_ends_with($method, 'Image') => 'file',str_ends_with($method, 'Base64') => 'base64',str_ends_with($method, 'Url') => 'url',default => 'payload'
        };
        $value = $a[$key];
        unset($a[$key]);
        if ($key === 'file') {
            $value = FileInput::fromPath(__DIR__ . '/../fixtures/' . $value);
        }
        return $c->$resource->$method($value, $resource === 'v1' ? new V1VerifyOptions(...$a) : new VerifyOptions(...$a));
    }
}

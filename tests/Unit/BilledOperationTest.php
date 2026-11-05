<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Thunder\Client;
use Thunder\Input\BatchSlip;
use Thunder\Input\FileInput;

final class BilledOperationTest extends TestCase
{
    public static function operations(): iterable
    {
        foreach (['verify' => ['bankByPayload', 'bankByImage', 'bankByBase64', 'bankByUrl', 'truewalletByImage', 'truewalletByBase64', 'truewalletByUrl'], 'v1' => ['verifyBankByPayload', 'verifyBankByImage', 'verifyBankByBase64', 'verifyBankByUrl', 'verifyTruemoneyByImage'], 'jobs' => ['submitBank', 'submitBankBatch', 'submitBankMany']] as $resource => $methods) {
            foreach ($methods as $method) {
                foreach (['timeout', '500'] as $failure) {
                    yield "$resource.$method.$failure" => [$resource, $method, $failure];
                }
            }
        }
    }

    #[DataProvider('operations')]
    public function testSentBilledOperationIsNotRetried(string $resource, string $method, string $failure): void
    {
        $http = new class ($failure) implements ClientInterface {
            public int $calls = 0;
            public function __construct(private string $failure) {}
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                ++$this->calls;
                if ($this->failure === 'timeout') {
                    throw new ConnectException('Read timed out after sending', $request, null, ['errno' => 28]);
                }
                return new Response(500, [], '{"error":{"message":"Failed after send"}}');
            }
        };
        $client = new Client(apiKey: 'synthetic-key', httpClient: $http, maxRetries: 2);
        $input = match (true) {
            str_ends_with($method, 'Image') => FileInput::fromBytes("\x89PNGsynthetic"),
            str_ends_with($method, 'Base64') => base64_encode("\x89PNGsynthetic"),
            str_ends_with($method, 'Url') => 'https://example.test/slip.png',
            in_array($method, ['submitBankBatch', 'submitBankMany'], true) => [new BatchSlip('synthetic-payload')],
            default => 'synthetic-payload',
        };
        try {
            $client->$resource->$method($input);
            self::fail('Expected sent request to fail');
        } catch (\Thunder\Exception\ThunderError $error) {
            self::assertSame(1, $http->calls);
        }
    }
}

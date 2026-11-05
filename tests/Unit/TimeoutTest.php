<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Thunder\Exception\RequestTimeoutError;
use Thunder\Transport\Transport;

final class TimeoutTest extends TestCase
{
    public function testStreamHandlerTimeoutIsClassified(): void
    {
        $client = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new ConnectException('Operation timed out', $request);
            }
        };
        $this->expectException(RequestTimeoutError::class);
        (new Transport($client, new HttpFactory(), 'test-key'))->send('POST', 'https://example.test/billed');
    }

    public function testCurlTimeoutIsClassifiedAcrossGuzzleVersions(): void
    {
        $client = new class implements ClientInterface {
            public int $calls = 0;
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                ++$this->calls;
                // Guzzle 7 stores errno in context. Guzzle 8 uses the cURL prefix.
                if (method_exists(ConnectException::class, 'getHandlerContext')) {
                    throw new ConnectException('Connection failed', $request, null, ['errno' => 28]);
                }
                throw new ConnectException('cURL error 28: Connection failed', $request);
            }
        };
        try {
            (new Transport($client, new HttpFactory(), 'test-key'))->send('POST', 'https://example.test/billed');
            self::fail('Expected timeout');
        } catch (RequestTimeoutError) {
            self::assertSame(1, $client->calls);
        }
    }

    public function testDefaultClientTimeoutHasItsOwnSanitizedTypeAndNoBilledRetry(): void
    {
        $client = new class implements ClientInterface {
            public int $calls = 0;
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                ++$this->calls;
                throw new ConnectException('Operation timed out: credential-should-not-escape', $request);
            }
        };
        $transport = new Transport($client, new HttpFactory(), 'credential-should-not-escape');
        try {
            $transport->send('POST', 'https://example.test/billed');
            self::fail('Expected timeout');
        } catch (RequestTimeoutError $error) {
            self::assertInstanceOf(\Thunder\Transport\SanitizedCause::class, $error->getPrevious());
            self::assertNull($error->getPrevious()->getPrevious());
            self::assertStringNotContainsString('credential-should-not-escape', (string) $error);
        }
        self::assertSame(1, $client->calls);
    }
}

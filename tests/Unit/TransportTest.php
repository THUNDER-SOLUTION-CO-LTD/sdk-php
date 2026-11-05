<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Thunder\Exception\ApiError;
use Thunder\Tests\Support\SequenceClient;
use Thunder\Transport\Transport;

final class TransportTest extends TestCase
{
    public function testSafeReadRetriesAndHonoursRetryAfter(): void
    {
        $client = new SequenceClient([new Response(429, ['Retry-After' => '2']), new Response(200, [], '{"ok":true}')]);
        $delays = [];
        $transport = new Transport($client, new HttpFactory(), 'test-key', maxRetries: 2, sleep: function (float $seconds) use (&$delays): void {
            $delays[] = $seconds;
        });
        self::assertSame(200, $transport->send('GET', 'https://example.test/info', safe: true)->getStatusCode());
        self::assertCount(2, $client->requests);
        self::assertGreaterThanOrEqual(2, $delays[0]);
    }

    public function testBilledRequestIsNeverRetriedAndErrorsRedactSecrets(): void
    {
        $client = new SequenceClient([new Response(503, ['X-Request-Id' => 'req-1'], '{"code":"BUSY","message":"test-key"}'), new Response(200)]);
        $transport = new Transport($client, new HttpFactory(), 'test-key');
        try {
            $transport->send('POST', 'https://example.test/verify', safe: false);
            self::fail('Expected API exception');
        } catch (ApiError $error) {
            self::assertSame(503, $error->status);
            self::assertSame('req-1', $error->requestId);
            self::assertStringNotContainsString('test-key', (string) $error);
            self::assertStringNotContainsString('test-key', $error->rawBody);
            self::assertStringNotContainsString('test-key', print_r($transport, true));
        }
        self::assertCount(1, $client->requests);
    }
}

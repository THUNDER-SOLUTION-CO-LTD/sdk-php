<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Thunder\Exception\NetworkError;
use Thunder\Tests\Support\FailingClient;
use Thunder\Transport\Transport;

final class RetrySafetyTest extends TestCase
{
    public function testUnknownDeliveryIsNeverRetriedForBilledCalls(): void
    {
        $client = new FailingClient();
        $transport = new Transport($client, new HttpFactory(), 'secret-value', sleep: static function (float $seconds): void {});
        try {
            $transport->send('POST', 'https://example.test/verify');
            self::fail('Expected network failure');
        } catch (NetworkError $error) {
            self::assertInstanceOf(\Thunder\Transport\SanitizedCause::class, $error->getPrevious());
            self::assertStringNotContainsString('secret-value', (string) $error);
        }
        self::assertSame(1, $client->calls);
    }

    public function testSafeReadRetriesNetworkError(): void
    {
        $client = new FailingClient();
        $transport = new Transport($client, new HttpFactory(), 'secret-value', sleep: static function (float $seconds): void {});
        self::assertSame(200, $transport->send('GET', 'https://example.test/info', safe: true)->getStatusCode());
        self::assertSame(2, $client->calls);
    }
}

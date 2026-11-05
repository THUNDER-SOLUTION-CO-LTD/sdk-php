<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Thunder\Transport\TransportFactory;

final class FactoryTest extends TestCase
{
    public function testDefaultClientEnforcesTimeoutAndDoesNotFollowRedirects(): void
    {
        $client = TransportFactory::httpClient(3.5);
        self::assertSame(3.5, $client->getConfig('timeout'));
        self::assertSame(3.5, $client->getConfig('connect_timeout'));
        self::assertFalse($client->getConfig('allow_redirects'));
        self::assertFalse($client->getConfig('http_errors'));
    }

    public function testInvalidTimeoutIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TransportFactory::httpClient(0);
    }
}

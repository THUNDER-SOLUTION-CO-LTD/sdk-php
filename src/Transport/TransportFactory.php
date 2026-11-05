<?php

declare(strict_types=1);

namespace Thunder\Transport;

use GuzzleHttp\Client;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;

/** Creates a bounded default client or accepts a caller-configured PSR-18 client. */
final class TransportFactory
{
    /** A custom PSR-18 client must enforce its own connect/read timeout. */
    public static function create(
        #[\SensitiveParameter]
        string $apiKey,
        float $timeout = 30.0,
        int $maxRetries = 2,
        ?ClientInterface $client = null,
    ): Transport {
        return new Transport($client ?? self::httpClient($timeout), Psr17FactoryDiscovery::findRequestFactory(), $apiKey, $maxRetries, timeout: $timeout);
    }

    /** Default HTTP client with redirects disabled to protect authorization headers. */
    public static function httpClient(float $timeout): Client
    {
        if (!is_finite($timeout) || $timeout <= 0) {
            throw new \InvalidArgumentException('timeout must be a positive finite number of seconds');
        }
        return new Client([
            'timeout' => $timeout,
            'connect_timeout' => $timeout,
            'allow_redirects' => false,
            'http_errors' => false,
        ]);
    }
}

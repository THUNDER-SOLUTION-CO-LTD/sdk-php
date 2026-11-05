<?php

declare(strict_types=1);

namespace Thunder\Tests\Support;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Fails the first request with a connection error, then answers 200. */
final class FailingClient implements ClientInterface
{
    public int $calls = 0;
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (++$this->calls === 1) {
            throw new ConnectException('secret-value', $request);
        }
        return new Response(200);
    }
}

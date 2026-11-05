<?php

declare(strict_types=1);

namespace Thunder\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Returns the given responses in order and fails on any extra request. */
final class SequenceClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];
    /** @param list<ResponseInterface> $responses */
    public function __construct(private array $responses) {}
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        return array_shift($this->responses) ?? throw new \LogicException('Unexpected retry');
    }
}

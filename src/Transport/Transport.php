<?php

declare(strict_types=1);

namespace Thunder\Transport;

use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Thunder\Exception\InputError;
use Thunder\Exception\NetworkError;
use Thunder\Exception\RequestTimeoutError;

/** Internal PSR transport; callers explicitly classify retry-safe operations. */
final class Transport
{
    /** @var \Closure(float): void */
    private readonly \Closure $sleep;

    /** @param null|\Closure(float): void $sleep */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requests,
        #[\SensitiveParameter]
        private readonly string $apiKey,
        private readonly int $maxRetries = 2,
        ?\Closure $sleep = null,
        private readonly string $userAgent = 'thunder-slip-php/0.0.0', // x-release-please-version
        private readonly float $timeout = 30.0,
    ) {
        if ($maxRetries < 0 || $maxRetries > 10) {
            throw new InputError('INVALID_ARGUMENT', 'maxRetries must be between 0 and 10');
        }
        if ($apiKey === '' || preg_match('/[\r\n]/', $apiKey)) {
            throw new InputError('INVALID_ARGUMENT', 'A nonempty API key without line breaks is required');
        }
        $this->sleep = $sleep ?? static function (float $seconds): void {
            usleep((int) min($seconds * 1_000_000, PHP_INT_MAX));
        };
    }

    /** Send once unless the operation explicitly permits retries. */
    public function send(string $method, #[\SensitiveParameter] string $url, #[\SensitiveParameter] string $body = '', bool $safe = false, string $contentType = 'application/json', ?float $deadline = null): ResponseInterface
    {
        for ($attempt = 0; ; ++$attempt) {
            $remaining = $this->remaining($deadline);
            $limit = $remaining;
            $request = $this->requests->createRequest($method, $url)
                ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
                ->withHeader('Accept', 'application/json')
                ->withHeader('User-Agent', $this->userAgent . ' php/' . PHP_VERSION);
            if ($body !== '') {
                $request->getBody()->write($body);
                $request = $request->withHeader('Content-Type', $contentType);
            }
            $started = hrtime(true) / 1e9;
            $readingBody = false;
            try {
                $client = $this->client;
                if ($client instanceof \GuzzleHttp\ClientInterface) {
                    $configured = method_exists($client, 'getConfig') ? $client->getConfig('timeout') : null;
                    $limit = is_numeric($configured) && (float) $configured > 0 ? min($remaining, (float) $configured) : $remaining;
                    $response = $client->send($request, ['timeout' => $limit, 'connect_timeout' => $limit, 'allow_redirects' => false, 'http_errors' => false]);
                } elseif ($client instanceof \Symfony\Component\HttpClient\Psr18Client) {
                    $response = $client->withOptions(['timeout' => $remaining, 'max_duration' => $remaining])->sendRequest($request);
                } else {
                    $response = $client->sendRequest($request);
                }
                $readingBody = true;
                $stream = $response->getBody();
                if ($stream->isSeekable()) {
                    $stream->rewind();
                }
                // Buffer lazy PSR bodies before decoding, under the send attempt budget.
                $raw = $stream->getContents();
                $this->remaining($deadline);
                if (hrtime(true) / 1e9 - $started >= $limit) {
                    throw new RequestTimeoutError('HTTP response body timed out');
                }
                $response = $response->withBody(\GuzzleHttp\Psr7\Utils::streamFor($raw));
                $stream->close();
            } catch (\Throwable $error) {
                if (!$readingBody && !$error instanceof ClientExceptionInterface) {
                    throw $error;
                }
                $this->remaining($deadline);
                $timedOut = self::isGuzzleTimeout($error)
                    || ($readingBody && (
                        $error instanceof RequestTimeoutError
                    || hrtime(true) / 1e9 - $started >= $limit
                    || preg_match('/timed? ?out|timeout|max duration/i', $error->getMessage()) === 1
                    )) || ($error instanceof ConnectException && (
                        (self::connectionErrno($error)) === 28
                    || str_contains($error->getMessage(), 'timed out')
                    )) || ($error instanceof \Symfony\Component\HttpClient\Psr18NetworkException && (
                        $error->getPrevious() instanceof \Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface
                    || str_contains($error->getMessage(), 'Max duration was reached')
                    || str_contains($error->getMessage(), 'Timeout was reached')
                    || str_contains($error->getMessage(), 'timed out')
                    || str_contains($error->getMessage(), 'timeout reached')
                    ));
                // cURL rounds to milliseconds, so cancellation can precede the deadline.
                if ($timedOut && $deadline !== null && $limit >= $deadline - hrtime(true) / 1e9) {
                    // Only classify a request whose chosen timeout was the wait budget.
                    if ($remaining < $this->timeout && $limit === $remaining) {
                        throw new DeadlineExceeded('HTTP request deadline exceeded');
                    }
                }
                // Only explicit pre-send failures are safe to retry for a billed call.
                $preSend = $error instanceof ConnectException && in_array(self::connectionErrno($error), [5, 6, 7], true);
                if (($safe || $preSend) && $attempt < $this->maxRetries) {
                    $this->pause($this->backoff($attempt), $deadline);
                    continue;
                }
                if ($timedOut) {
                    throw new RequestTimeoutError('HTTP request timed out; the request may have reached the server', 0, new SanitizedCause($error, $this->apiKey));
                }
                throw new NetworkError('HTTP transport failed; the request may have reached the server', 0, new SanitizedCause($error, $this->apiKey));
            }
            $this->remaining($deadline);
            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return ResponseDecoder::redacted($response, $this->apiKey);
            }
            $delay = ResponseDecoder::retryAfter($response);
            $retryable = $status === 429 || ($safe && in_array($status, [500, 502, 503, 504], true));
            if ($retryable && $attempt < $this->maxRetries && ($delay === null || $delay <= 60)) {
                $this->pause($delay ?? $this->backoff($attempt), $deadline);
                continue;
            }
            throw ResponseDecoder::error($response, $this->apiKey);
        }
    }

    private static function isGuzzleTimeout(#[\SensitiveParameter] \Throwable $error): bool
    {
        // These exception types were introduced in Guzzle 8.
        foreach ([
            'GuzzleHttp\\Exception\\ConnectTimeoutException',
            'GuzzleHttp\\Exception\\NetworkTimeoutException',
            'GuzzleHttp\\Exception\\ResponseTimeoutException',
        ] as $class) {
            if (self::matchesAvailableException($error, $class)) {
                return true;
            }
        }
        return false;
    }

    private static function matchesAvailableException(#[\SensitiveParameter] \Throwable $error, string $class): bool
    {
        return class_exists($class) && is_a($error, $class);
    }

    private static function connectionErrno(#[\SensitiveParameter] \Throwable $error): ?int
    {
        // Version 7 exposes handler context; version 8 uses typed exceptions.
        if (method_exists($error, 'getHandlerContext')) {
            $context = $error->getHandlerContext();
            $errno = is_array($context) ? ($context['errno'] ?? null) : null;
            return is_int($errno) ? $errno : null;
        }
        if (preg_match('/^cURL error ([0-9]+):/', $error->getMessage(), $matches) === 1) {
            return (int) $matches[1];
        }
        return null;
    }

    /** Prevent debug output from exposing the API credential or client state. @return array<string, int> */
    public function __debugInfo(): array
    {
        return ['maxRetries' => $this->maxRetries];
    }

    private function remaining(?float $deadline): float
    {
        $remaining = $deadline === null ? $this->timeout : min($this->timeout, $deadline - hrtime(true) / 1e9);
        if ($remaining <= 0) {
            throw new DeadlineExceeded('HTTP request deadline exceeded');
        }
        return $remaining;
    }

    private function pause(float $seconds, ?float $deadline): void
    {
        ($this->sleep)(min($seconds, $this->remaining($deadline)));
        $this->remaining($deadline);
    }

    private function backoff(int $attempt): float
    {
        return min(8.0, 0.5 * (2 ** $attempt)) * (0.5 + random_int(0, 1000) / 2000);
    }
}

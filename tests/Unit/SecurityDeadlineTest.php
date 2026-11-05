<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Thunder\Client;
use Thunder\Exception\ApiError;
use Thunder\Exception\JobWaitTimeoutError;
use Thunder\Input\WaitOptions;

final class SecurityDeadlineTest extends TestCase
{
    public function testEscapedSecretsAreAbsentFromAllDiagnostics(): void
    {
        $key = 'fake-secret+/key';
        $escaped = implode('', array_map(static fn(string $c): string => sprintf('\\u%04x', ord($c)), str_split($key)));
        $raw = '{"error":{"code":"' . $escaped . '","message":"' . $escaped . '","details":{"nested":["' . $escaped . '","' . rawurlencode($key) . '"]}}}';
        $previous = ini_get('zend.exception_ignore_args');
        foreach (['0', '1'] as $capture) {
            ini_set('zend.exception_ignore_args', $capture);
            $http = new class ($raw, $key) implements ClientInterface {
                public function __construct(private string $raw, private string $key) {}
                public function sendRequest(RequestInterface $request): ResponseInterface
                {
                    return new Response(400, ['X-Echo' => $this->key, 'Authorization' => 'Bearer other-secret', 'Proxy-Authorization' => 'Basic other-secret'], $this->raw);
                }
            };
            try {
                (new Client(apiKey: $key, httpClient: $http, maxRetries: 0))->getInfo();
                self::fail('Expected error');
            } catch (ApiError $error) {
                ob_start();
                var_dump($error);
                $dump = (string) ob_get_clean();
                foreach ([$dump, print_r($error, true), var_export($error, true), json_encode($error, JSON_THROW_ON_ERROR), (string) $error, $error->getTraceAsString()] as $diagnostic) {
                    self::assertStringNotContainsString($key, $diagnostic);
                    self::assertStringNotContainsString(rawurlencode($key), $diagnostic);
                    self::assertStringNotContainsString('other-secret', $diagnostic);
                }
                self::assertNull($error->getPrevious());
                self::assertSame($raw, $error->sensitiveRawBody());
                self::assertSame('[REDACTED]', $error->getMessage());
                self::assertSame(['nested' => ['[REDACTED]', '[REDACTED]']], $error->details);
            } finally {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testLateTerminalJobFromUncancellableClientIsRejected(): void
    {
        $http = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                usleep(50000);
                return new Response(200, [], '{"data":{"jobId":"late","status":"done","attempts":1,"createdAt":"now","updatedAt":"now"}}');
            }
        };
        $client = new Client(apiKey: 'synthetic-key', httpClient: $http, maxRetries: 0);
        try {
            $client->jobs->waitForJob('late', new WaitOptions(timeout: 0.01));
            self::fail('A late terminal response must not be returned');
        } catch (JobWaitTimeoutError $error) {
            self::assertNull($error->lastJob);
        }
    }
    public function testRequestBearingTransportCauseIsNotReachable(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        foreach (['0', '1'] as $capture) {
            ini_set('zend.exception_ignore_args', $capture);
            $key = 'synthetic-secret-key';
            $http = new class implements ClientInterface {
                public function sendRequest(RequestInterface $request): ResponseInterface
                {
                    throw new \GuzzleHttp\Exception\ConnectException($request->getHeaderLine('Authorization'), $request);
                }
            };
            try {
                (new Client(apiKey: $key, httpClient: $http, maxRetries: 0))->getInfo();
                self::fail('Expected transport error');
            } catch (\Thunder\Exception\NetworkError $error) {
                ob_start();
                var_dump($error);
                $dump = (string) ob_get_clean();
                foreach ([$dump, print_r($error, true), var_export($error, true), json_encode($error), (string) $error, $error->getTraceAsString()] as $diagnostic) {
                    self::assertStringNotContainsString($key, $diagnostic);
                }
                self::assertInstanceOf(\Thunder\Transport\SanitizedCause::class, $error->getPrevious());
                self::assertNull($error->getPrevious()->getPrevious());
            } finally {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testSuccessfulDecodedValuesAndMetadataAreRedacted(): void
    {
        $key = 'synthetic+/secret';
        $raw = json_encode(['message' => $key, 'data' => ['nested' => [$key, rawurlencode($key), urlencode($key)]]], JSON_THROW_ON_ERROR);
        $clean = \Thunder\Transport\ResponseDecoder::redacted(new Response(200, ['X-Echo' => $key, 'Authorization' => 'unrelated'], $raw), $key);
        $meta = \Thunder\Transport\ResponseDecoder::meta($clean);
        self::assertSame('[REDACTED]', $meta->message);
        self::assertSame('[REDACTED]', $meta->headers['x-echo']);
        self::assertSame('[REDACTED]', $meta->headers['authorization']);
        self::assertSame(['[REDACTED]', '[REDACTED]', '[REDACTED]'], json_decode((string) $clean->getBody(), true)['data']['nested']);
    }

    public function testLastJobExcludesLateTerminalResponse(): void
    {
        $http = new class implements ClientInterface {
            private int $calls = 0;
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $status = ++$this->calls === 1 ? 'pending' : 'done';
                if ($status === 'done') {
                    usleep(50000);
                }
                return new Response(200, [], json_encode(['data' => ['jobId' => 'last', 'status' => $status, 'attempts' => 1, 'createdAt' => 'now', 'updatedAt' => 'now']], JSON_THROW_ON_ERROR));
            }
        };
        try {
            (new Client(apiKey: 'synthetic-key', httpClient: $http, sleep: static function (float $seconds): void {}))->jobs->waitForJob('last', new WaitOptions(timeout: 0.02, interval: 0.5));
            self::fail('Late terminal response returned');
        } catch (JobWaitTimeoutError $error) {
            self::assertSame('pending', $error->lastJob?->status);
        }
    }

}

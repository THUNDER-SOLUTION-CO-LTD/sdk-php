<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Thunder\Client;
use Thunder\Exception\JobWaitTimeoutError;
use Thunder\Exception\NetworkError;
use Thunder\Exception\RequestTimeoutError;
use Thunder\Input\WaitOptions;
use Thunder\Transport\SanitizedCause;

final class BodyDeadlineTest extends TestCase
{
    public static function cases(): iterable
    {
        foreach (['guzzle', 'symfony'] as $adapter) {
            foreach (['success', 'error'] as $status) {
                foreach (['wait', 'plain'] as $mode) {
                    yield "$adapter-$status-$mode" => [$adapter, $status, $mode];
                }
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cases')]
    public function testTricklingBodyUsesTypedDeadline(string $adapter, string $status, string $mode): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $server = proc_open([PHP_BINARY, '-d', 'output_buffering=0', '-S', $address, __DIR__ . '/../Support/body-loopback.php'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        fclose($pipes[0]);
        try {
            for ($i = 0; $i < 100; ++$i) {
                $ready = @stream_socket_client('tcp://' . $address, $errno, $message, 0.01);
                if ($ready) {
                    fclose($ready);
                    break;
                }
                usleep(10000);
            }
            $client = new Client(apiKey: 'synthetic-KeY+/test', v2BaseUrl: 'http://' . $address . '/' . $status, timeout: $mode === 'plain' ? 0.02 : 30, maxRetries: 0, httpClient: $adapter === 'guzzle' ? new \GuzzleHttp\Client() : new \Symfony\Component\HttpClient\Psr18Client());
            $start = hrtime(true);
            try {
                $mode === 'wait' ? $client->jobs->waitForJob('job-1', new WaitOptions(timeout: 0.02)) : $client->getInfo();
                self::fail('Late body accepted');
            } catch (\Throwable $error) {
                self::assertSame($mode === 'wait' ? JobWaitTimeoutError::class : RequestTimeoutError::class, $error::class);
                $elapsed = (hrtime(true) - $start) / 1e9;
                self::assertGreaterThan(0.014, $elapsed);
                self::assertLessThan(0.12, $elapsed);
                if ($error instanceof JobWaitTimeoutError) {
                    self::assertNull($error->lastJob);
                }
                AdapterRedactionTest::checkPublic($error);
            }
        } finally {
            proc_terminate($server);
            proc_close($server);
        }
    }

    public function testBrokenStreamHasOnlySanitizedCause(): void
    {
        foreach ([0, 1] as $capture) {
            foreach ([200, 400] as $status) {
                $before = ini_set('zend.exception_ignore_args', (string) $capture);
                $http = new class ($status) implements \Psr\Http\Client\ClientInterface {
                    public function __construct(private int $status) {}
                    public function sendRequest(#[\SensitiveParameter] \Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
                    {
                        $first = true;
                        $stream = new \GuzzleHttp\Psr7\PumpStream(static function () use (&$first): string {
                            if ($first) {
                                $first = false;
                                return '{';
                            }
                            throw new \RuntimeException('broken synthetic-KeY+/test');
                        });
                        return new \GuzzleHttp\Psr7\Response($this->status, [], $stream);
                    }
                };
                try {
                    (new Client(apiKey: 'synthetic-KeY+/test', httpClient: $http, maxRetries: 0))->getInfo();
                    self::fail('Broken stream accepted');
                } catch (\Throwable $error) {
                    self::assertSame(NetworkError::class, $error::class);
                    self::assertSame(SanitizedCause::class, $error->getPrevious()::class);
                    self::assertNull($error->getPrevious()->getPrevious());
                    AdapterRedactionTest::checkPublic($error);
                } finally {
                    ini_set('zend.exception_ignore_args', $before);
                }
            }
        }
    }
}

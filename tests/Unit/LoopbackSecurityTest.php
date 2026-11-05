<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use GuzzleHttp\Client as Guzzle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Psr18Client;
use Thunder\Client;
use Thunder\Exception\ApiError;
use Thunder\Exception\JobWaitTimeoutError;
use Thunder\Input\WaitOptions;

final class LoopbackSecurityTest extends TestCase
{
    /** @var resource|null */
    private $server = null;
    private string $base;

    protected function setUp(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->base = 'http://' . $address;
        $this->server = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/../Support/loopback.php'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        self::assertIsResource($this->server);
        fclose($pipes[0]);
        for ($i = 0; $i < 100; ++$i) {
            $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.01);
            if ($ready !== false) {
                fclose($ready);
                return;
            }
            usleep(10000);
        }
        self::fail('Loopback server did not start');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
    }

    public static function deadlineCases(): iterable
    {
        foreach (['default', 'guzzle', 'symfony', 'guzzle-no-retries', 'symfony-no-retries'] as $client) {
            foreach (['slow', 'stall', 'retry'] as $path) {
                if ($path !== 'retry' || !str_contains($client, 'no-retries')) {
                    yield "$client-$path" => [$client, $path];
                }
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deadlineCases')]
    public function testWholeWaitDeadline(string $adapter, string $job): void
    {
        $http = match ($adapter) {
            'guzzle', 'guzzle-no-retries' => new Guzzle(),
            'symfony', 'symfony-no-retries' => new Psr18Client(),
            default => null,
        };
        $client = new Client(apiKey: 'synthetic-key', v2BaseUrl: $this->base . '/v2', httpClient: $http, maxRetries: str_contains($adapter, 'no-retries') ? 0 : 2);
        $start = hrtime(true) / 1e9;
        try {
            $client->jobs->waitForJob($job, new WaitOptions(timeout: 0.02));
            self::fail('Late job returned');
        } catch (JobWaitTimeoutError $error) {
            $elapsed = hrtime(true) / 1e9 - $start;
            self::assertNull($error->lastJob);
            self::assertGreaterThanOrEqual(0.015, $elapsed);
            self::assertLessThan(0.1, $elapsed, 'Request was not cancelled at the deadline');
        }
    }

    public static function echoCases(): iterable
    {
        foreach (['guzzle', 'symfony'] as $adapter) {
            foreach (['0', '1'] as $capture) {
                foreach (['v1', 'v2'] as $version) {
                    yield "$adapter-$capture-$version" => [$adapter, $capture, $version];
                }
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('echoCases')]
    public function testRealEchoCannotLeak(string $adapter, string $capture, string $version): void
    {
        $previous = ini_set('zend.exception_ignore_args', $capture);
        $key = 'synthetic-key+/secret';
        $client = new Client(apiKey: $key, v1BaseUrl: $this->base . '/v1', v2BaseUrl: $this->base . '/v2', maxRetries: 0, httpClient: $adapter === 'guzzle' ? new Guzzle() : new Psr18Client());
        try {
            $version === 'v1' ? $client->v1->getMe() : $client->getInfo();
            self::fail('Expected API error');
        } catch (ApiError $error) {
            ob_start();
            var_dump($error);
            $dump = (string) ob_get_clean();
            foreach ([$dump, print_r($error, true), var_export($error, true), json_encode($error), (string) $error, $error->getTraceAsString()] as $diagnostic) {
                self::assertStringNotContainsString($key, $diagnostic);
                self::assertStringNotContainsString(rawurlencode($key), $diagnostic);
                self::assertStringNotContainsString('secret-proxy', $diagnostic);
            }
            self::assertNull($error->getPrevious());
            self::assertStringContainsString($key, json_encode(json_decode($error->sensitiveRawBody()), JSON_UNESCAPED_SLASHES));
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }
    public function testGenericClientIgnoringCancellationRejectsLateLoopbackResponse(): void
    {
        $http = new class implements \Psr\Http\Client\ClientInterface {
            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return (new Guzzle())->sendRequest($request);
            }
        };
        $client = new Client(apiKey: 'synthetic-key', v2BaseUrl: $this->base . '/v2', httpClient: $http);
        $start = hrtime(true) / 1e9;
        try {
            $client->jobs->waitForJob('slow', new WaitOptions(timeout: 0.02));
            self::fail('Generic client returned late data');
        } catch (JobWaitTimeoutError $error) {
            self::assertNull($error->lastJob);
            self::assertGreaterThan(0.19, hrtime(true) / 1e9 - $start);
        }
    }

}

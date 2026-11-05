<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use GuzzleHttp\Client as Guzzle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Psr18Client;
use Thunder\Client;
use Thunder\Exception\NetworkError;

final class AdapterRedactionTest extends TestCase
{
    public function testRealAdaptersNeverExposeAnEncodedKey(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $server = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/../Support/encoded-loopback.php'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        fclose($pipes[0]);
        $previous = ini_get('zend.exception_ignore_args');
        try {
            for ($i = 0; $i < 100; ++$i) {
                $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.01);
                if ($ready) {
                    fclose($ready);
                    break;
                }
                usleep(10000);
            }
            foreach (['0', '1'] as $capture) {
                ini_set('zend.exception_ignore_args', $capture);
                foreach (['guzzle', 'symfony'] as $adapter) {
                    foreach (EncodedRedactionTest::encodings() as [$encoded]) {
                        $http = $adapter === 'guzzle'
                            ? new Guzzle(['handler' => static function ($request) use ($encoded) {
                                return new \GuzzleHttp\Promise\RejectedPromise(new class ($encoded, $request) extends \GuzzleHttp\Exception\ConnectException {
                                    public array $requestHeaders;
                                    public function __construct(#[\SensitiveParameter] string $encoded, #[\SensitiveParameter] $request)
                                    {
                                        parent::__construct($encoded, $request);
                                        $this->code = $encoded;
                                        $this->requestHeaders = ['authorization' => 'synthetic-KeY+/test'];
                                    }
                                });
                            }])
                            : new Psr18Client(new \Symfony\Component\HttpClient\MockHttpClient(static function () use ($encoded) {
                                throw new class ($encoded) extends \Symfony\Component\HttpClient\Exception\TransportException {
                                    public array $headers;
                                    public function __construct(#[\SensitiveParameter] string $encoded)
                                    {
                                        parent::__construct($encoded);
                                        $this->code = $encoded;
                                        $this->headers = ['authorization' => 'synthetic-KeY+/test'];
                                    }
                                };
                            }));
                        try {
                            (new Client(apiKey: 'synthetic-KeY+/test', httpClient: $http, maxRetries: 0))->getInfo();
                            self::fail('Expected transport failure');
                        } catch (\Throwable $error) {
                            self::assertInstanceOf(NetworkError::class, $error);
                            self::checkPublic($error);
                            self::assertNotNull($error->getPrevious());
                            self::assertNull($error->getPrevious()->getPrevious());
                        }
                    }
                    foreach (array_keys(iterator_to_array(EncodedRedactionTest::encodings())) as $encoding) {
                        foreach (['v1', 'v2', 'plain', 'success'] as $shape) {
                            $base = 'http://' . $address . '/' . $encoding . '/' . $shape;
                            $client = new Client(apiKey: 'synthetic-KeY+/test', v1BaseUrl: $base, v2BaseUrl: $base, maxRetries: 0, httpClient: $adapter === 'guzzle' ? new Guzzle() : new Psr18Client());
                            try {
                                $value = $shape === 'v1' ? $client->v1->getMe() : $client->getInfo();
                                self::assertSame('success', $shape);
                                self::checkPublic($value);
                            } catch (\Throwable $error) {
                                if (!method_exists($error, 'sensitiveRawBody')) {
                                    throw $error;
                                }
                                self::assertNotSame('success', $shape);
                                self::checkPublic($error);
                                self::assertStringContainsString('synthetic-KeY+/test', self::decoded($error->sensitiveRawBody()));
                                self::assertNull($error->getPrevious());
                            }
                        }
                    }
                }
            }
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
            proc_terminate($server);
            proc_close($server);
        }
    }

    public static function checkPublic(object $value): void
    {
        ob_start();
        var_dump($value);
        $dump = (string) ob_get_clean();
        $forms = [$dump, print_r($value, true), var_export($value, true), json_encode($value, JSON_THROW_ON_ERROR)];
        if ($value instanceof \Throwable) {
            $forms[] = (string) $value;
            $forms[] = $value->getTraceAsString();
            if ($value->getPrevious()) {
                self::checkPublic($value->getPrevious());
            }
        }
        foreach ($forms as $form) {
            self::assertStringNotContainsString('synthetic-KeY+/test', self::decoded($form));
        }
    }

    public static function decoded(string $text): string
    {
        do {
            $before = $text;
            $text = str_replace('\\\\', '\\', $text);
            $text = rawurldecode($text);
            $text = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', static fn(array $m): string => chr(hexdec($m[1])), $text);
            $text = str_replace(['\\/', '\\\\'], ['/', '\\'], $text);
        } while ($before !== $text);
        return $text;
    }
}

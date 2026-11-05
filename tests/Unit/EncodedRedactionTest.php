<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Thunder\Transport\ResponseDecoder;

final class EncodedRedactionTest extends TestCase
{
    public static function encodings(): iterable
    {
        $key = 'synthetic-KeY+/test';
        yield 'raw' => [$key];
        foreach ([true, false] as $upper) {
            $percent = '';
            $unicode = '';
            $mixed = '';
            foreach (str_split($key) as $i => $char) {
                $percent .= sprintf($upper ? '%%%02X' : '%%%02x', ord($char));
                $unicode .= sprintf($upper ? '\\u%04X' : '\\u%04x', ord($char));
                $mixed .= $i % 2 ? $char : sprintf($upper ? '\\u%04X' : '\\u%04x', ord($char));
            }
            yield 'percent-' . (int) $upper => [$percent];
            yield 'unicode-' . (int) $upper => [$unicode];
            yield 'mixed-' . (int) $upper => [$mixed];
            yield 'partial-percent-' . (int) $upper => ['synthetic-' . substr($percent, 30)];
        }
        yield 'slash' => [str_replace('/', '\\/', $key)];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('encodings')]
    public function testEveryPublicResponseSurface(string $encoded): void
    {
        $key = 'synthetic-KeY+/test';
        foreach ([200, 400] as $status) {
            foreach (['message', 'error'] as $shape) {
                $data = ['message' => $encoded, 'details' => [$encoded => [$encoded]]];
                $body = json_encode($shape === 'error' ? ['error' => $data] : $data, JSON_THROW_ON_ERROR);
                $response = new Response($status, ['X-Echo' => 'prefix ' . $encoded . ' suffix', 'Authorization' => 'other', 'Proxy-Authorization' => 'other'], $body);
                $clean = ResponseDecoder::redacted($response, $key);
                self::assertSame('[REDACTED]', $clean->getHeaderLine('X-Echo'));
                self::assertSame('[REDACTED]', $clean->getHeaderLine('Authorization'));
                self::assertSame('[REDACTED]', $clean->getHeaderLine('Proxy-Authorization'));
                self::assertStringNotContainsString($key, self::decode((string) $clean->getBody()));
                $error = ResponseDecoder::error($response, $key);
                self::assertSame($body, $error->sensitiveRawBody());
                self::assertStringNotContainsString($key, self::decode(json_encode($error, JSON_THROW_ON_ERROR)));
            }
        }
        $plain = ResponseDecoder::redacted(new Response(400, [], 'not JSON: ' . $encoded), $key);
        self::assertStringNotContainsString($key, self::decode((string) $plain->getBody()));
    }

    public function testCleanJsonIsByteIdentical(): void
    {
        $body = "{\n  \"data\": {\"value\": 1.0, \"url\": \"https:\\/\\/example.test\"}\n}\n";
        self::assertSame($body, (string) ResponseDecoder::redacted(new Response(200, [], $body), 'synthetic-KeY+/test')->getBody());
    }

    private static function decode(string $value): string
    {
        return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', static fn(array $m): string => chr(hexdec($m[1])), rawurldecode(str_replace('\\/', '/', $value))) ?? $value;
    }
}

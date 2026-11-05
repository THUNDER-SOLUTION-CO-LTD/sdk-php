<?php

declare(strict_types=1);

namespace Thunder\Tests\Conformance;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Thunder\Transport\ResponseDecoder;

final class ErrorFixturesTest extends TestCase
{
    public function testSharedErrorResponses(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../fixtures'));
        $count = 0;
        foreach ($files as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }
            $fixture = json_decode(file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
            if (!isset($fixture['response'], $fixture['expect']['error'])) {
                continue;
            }
            $response = $fixture['response'];
            $body = $response['body'];
            $raw = is_array($body) && isset($body['_nonJson']) ? $body['_nonJson'] : json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $error = ResponseDecoder::error(new Response($response['status'], $response['headers'], $raw), 'unit-secret');
            $expected = $fixture['expect']['error'];
            self::assertSame('Thunder\\Exception\\' . $expected['class'], $error::class, $fixture['id']);
            foreach (['code' => 'apiCode', 'status' => 'status', 'requestId' => 'requestId'] as $key => $property) {
                if (array_key_exists($key, $expected)) {
                    self::assertSame($expected[$key], $error->$property, $fixture['id'] . ':' . $key);
                }
            }
            if (isset($expected['message'])) {
                self::assertSame($expected['message'], $error->getMessage(), $fixture['id']);
            }
            ++$count;
        }
        self::assertGreaterThan(30, $count);
    }

    public function testV1DetailsRemainAvailable(): void
    {
        $error = ResponseDecoder::error(new Response(400, [], '{"message":"invalid_type","details":"bad type"}'), 'unit-secret');
        self::assertSame('bad type', $error->details);
    }
}

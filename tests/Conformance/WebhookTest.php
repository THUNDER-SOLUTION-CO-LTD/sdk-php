<?php

declare(strict_types=1);

namespace Thunder\Tests\Conformance;

use PHPUnit\Framework\TestCase;
use Thunder\Exception\WebhookSignatureError;
use Thunder\Webhook;

final class WebhookTest extends TestCase
{
    public function testSharedOpenSslSignaturesOverThaiUtf8Bytes(): void
    {
        $root = __DIR__ . '/../fixtures/';
        $fixture = json_decode(file_get_contents($root . 'webhook/signatures.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('openssl', $fixture['source']);
        foreach ($fixture['cases'] as $case) {
            $body = file_get_contents($root . $case['body']);
            self::assertSame($case['valid'], Webhook::verifySignature($body, $case['signature'], $fixture['secret']), $case['id']);
            if ($case['valid']) {
                self::assertMatchesRegularExpression('/[ก-๙]/u', $body);
                self::assertSame(json_decode($body, true), Webhook::constructEvent($body, $case['signature'], $fixture['secret'])->jsonSerialize());
            }
        }
    }

    public function testSharedEventModels(): void
    {
        $root = __DIR__ . '/../fixtures/';
        $fixture = json_decode(file_get_contents($root . 'webhook/events.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($fixture['cases'] as $case) {
            self::assertSame($case['expect']['json'], Webhook::parseEvent(file_get_contents($root . $case['body']))->jsonSerialize(), $case['id']);
        }
    }

    public function testExactBytesAndStrictSignature(): void
    {
        $body = '{"jobId":"a","batchId":null,"status":"not_found","data":null,"timestamp":"2026-10-29T00:00:00Z"}';
        $signature = 'sha256=' . hash_hmac('sha256', $body, 'test-secret');
        self::assertTrue(Webhook::verifySignature($body, $signature, 'test-secret'));
        self::assertFalse(Webhook::verifySignature($body . ' ', $signature, 'test-secret'));
        self::assertFalse(Webhook::verifySignature($body, strtoupper($signature), 'test-secret'));
        self::assertSame(json_decode($body, true), Webhook::constructEvent($body, $signature, 'test-secret')->jsonSerialize());
        $this->expectException(WebhookSignatureError::class);
        Webhook::constructEvent($body, null, 'test-secret');
    }
}

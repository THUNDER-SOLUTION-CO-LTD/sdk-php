<?php

declare(strict_types=1);

namespace Thunder;

use Thunder\Exception\InputError;
use Thunder\Exception\WebhookSignatureError;
use Thunder\Model\WebhookEvent;

/** Verify the exact request bytes before decoding a callback. */
final class Webhook
{
    public const SIGNATURE_HEADER = 'X-Thunder-Signature';

    /** Missing, malformed and mismatching signatures are all rejected. */
    public static function verifySignature(string $rawBody, ?string $signature, #[\SensitiveParameter] string $secret): bool
    {
        return $signature !== null && $secret !== ''
            && preg_match('/\Asha256=[a-f0-9]{64}\z/', $signature) === 1
            && hash_equals('sha256=' . hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    /** Parse an already authenticated callback; this method does not verify it. */
    public static function parseEvent(string $rawBody): WebhookEvent
    {
        try {
            $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InputError('INVALID_ARGUMENT', 'Webhook body must be valid JSON');
        }
        if (!is_array($data) || !is_string($data['jobId'] ?? null) || !is_string($data['status'] ?? null) || !is_string($data['timestamp'] ?? null)) {
            throw new InputError('INVALID_ARGUMENT', 'Webhook body must contain jobId, status and timestamp');
        }
        /** @var array<string, mixed> $data */
        return new WebhookEvent($data);
    }

    /** Authenticate first, then parse without changing the signed bytes. */
    public static function constructEvent(string $rawBody, ?string $signature, #[\SensitiveParameter] string $secret): WebhookEvent
    {
        if (!self::verifySignature($rawBody, $signature, $secret)) {
            throw new WebhookSignatureError('Webhook signature is missing or invalid');
        }
        return self::parseEvent($rawBody);
    }
}

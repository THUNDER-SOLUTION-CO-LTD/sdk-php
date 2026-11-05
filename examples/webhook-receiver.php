<?php

declare(strict_types=1);

// A webhook endpoint in plain PHP. It checks the X-Thunder-Signature header against the
// secret in THUNDER_WEBHOOK_SECRET, answers 200 for a valid event and 400 for anything else.
// Usage: THUNDER_WEBHOOK_SECRET=... php -S localhost:8787 examples/webhook-receiver.php

use Thunder\Exception\InputError;
use Thunder\Exception\WebhookSignatureError;
use Thunder\Webhook;

require __DIR__ . '/../vendor/autoload.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo 'method not allowed';
    return;
}

// Read the raw body: the signature covers the exact bytes that were sent.
$rawBody = (string) file_get_contents('php://input');
$header = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', Webhook::SIGNATURE_HEADER))] ?? null;

try {
    $event = Webhook::constructEvent($rawBody, is_string($header) ? $header : null, (string) getenv('THUNDER_WEBHOOK_SECRET'));
} catch (WebhookSignatureError|InputError) {
    http_response_code(400);
    echo 'invalid webhook';
    return;
}

// The same event can arrive more than once, so de-duplicate on $event->jobId.
error_log($event->jobId . ' ' . $event->status . ' ' . ($event->data->rawSlip->transRef ?? ''));
echo 'ok';

<?php

declare(strict_types=1);

// Checks the signature, then parses the event. Throws WebhookSignatureError when it does not
// match. The secret comes from the third argument, else from THUNDER_WEBHOOK_SECRET.
// Usage: php examples/webhook-construct-event.php <body file> <signature header value> [secret]

use Thunder\Webhook;

require __DIR__ . '/bootstrap.php';

$usage = '<body file> <signature header value> [secret]';
$rawBody = readLocalFile(arg(0, $usage));
$signature = arg(1, $usage);
$secret = optionalArg(2) ?? (string) getenv('THUNDER_WEBHOOK_SECRET');

run(fn(): mixed => Webhook::constructEvent($rawBody, $signature, $secret));

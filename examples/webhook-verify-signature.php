<?php

declare(strict_types=1);

// Checks a webhook signature against the raw body. No client or API key needed. The secret
// comes from the third argument, else from THUNDER_WEBHOOK_SECRET.
// Usage: php examples/webhook-verify-signature.php <body file> <signature header value> [secret]

use Thunder\Webhook;

require __DIR__ . '/bootstrap.php';

$usage = '<body file> <signature header value> [secret]';
$rawBody = readLocalFile(arg(0, $usage));
$signature = arg(1, $usage);
$secret = optionalArg(2) ?? (string) getenv('THUNDER_WEBHOOK_SECRET');

$valid = Webhook::verifySignature($rawBody, $signature, $secret);
echo $valid ? 'valid signature' : 'invalid signature', PHP_EOL;
exit($valid ? 0 : 1);

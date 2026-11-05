<?php

declare(strict_types=1);

// Queues one slip for background verification. The result is posted to the callback URL.
// Usage: php examples/jobs-submit-bank.php <QR payload> <https callback URL>

use Thunder\Client;
use Thunder\Input\AsyncVerifyOptions;

require __DIR__ . '/bootstrap.php';

$usage = '<QR payload> <https callback URL>';
$payload = arg(0, $usage);
$callbackUrl = arg(1, $usage);

run(fn(): mixed => (new Client())->jobs->submitBank($payload, new AsyncVerifyOptions(checkDuplicate: true, callbackUrl: $callbackUrl)));

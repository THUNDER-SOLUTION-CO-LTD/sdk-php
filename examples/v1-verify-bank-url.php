<?php

declare(strict_types=1);

// v1: verifies a bank slip image that the API downloads from a public URL.
// Usage: php examples/v1-verify-bank-url.php <image URL>

use Thunder\Client;

require __DIR__ . '/bootstrap.php';

$url = arg(0, '<image URL>');

run(fn(): mixed => (new Client())->v1->verifyBankByUrl($url));

<?php

declare(strict_types=1);

// Verifies a TrueMoney Wallet slip image from a public URL (up to 255 characters).
// Usage: php examples/verify-truewallet-url.php <image URL>

use Thunder\Client;

require __DIR__ . '/bootstrap.php';

$url = arg(0, '<image URL>');

run(fn(): mixed => (new Client())->verify->truewalletByUrl($url));

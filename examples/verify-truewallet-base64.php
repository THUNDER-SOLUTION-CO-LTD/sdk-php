<?php

declare(strict_types=1);

// Verifies a TrueMoney Wallet slip image sent as base64.
// Usage: php examples/verify-truewallet-base64.php <image file>

use Thunder\Client;

require __DIR__ . '/bootstrap.php';

$base64 = base64_encode(readLocalFile(arg(0, '<image file>')));

run(fn(): mixed => (new Client())->verify->truewalletByBase64($base64));

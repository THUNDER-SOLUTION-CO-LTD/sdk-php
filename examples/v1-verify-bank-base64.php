<?php

declare(strict_types=1);

// v1: verifies a bank slip image sent as base64.
// Usage: php examples/v1-verify-bank-base64.php <image file>

use Thunder\Client;

require __DIR__ . '/bootstrap.php';

$base64 = base64_encode(readLocalFile(arg(0, '<image file>')));

run(fn(): mixed => (new Client())->v1->verifyBankByBase64($base64));

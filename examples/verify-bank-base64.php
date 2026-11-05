<?php

declare(strict_types=1);

// Verifies a bank slip image sent as base64.
// Usage: php examples/verify-bank-base64.php <image file>

use Thunder\Client;
use Thunder\Input\VerifyOptions;

require __DIR__ . '/bootstrap.php';

$base64 = base64_encode(readLocalFile(arg(0, '<image file>')));

run(fn(): mixed => (new Client())->verify->bankByBase64($base64, new VerifyOptions(checkDuplicate: true)));

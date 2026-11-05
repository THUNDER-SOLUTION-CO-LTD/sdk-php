<?php

declare(strict_types=1);

// v1: verifies a bank slip image.
// Usage: php examples/v1-verify-bank-image.php <image file>

use Thunder\Client;
use Thunder\Input\FileInput;

require __DIR__ . '/bootstrap.php';

$path = arg(0, '<image file>');

run(fn(): mixed => (new Client())->v1->verifyBankByImage(FileInput::fromPath($path)));

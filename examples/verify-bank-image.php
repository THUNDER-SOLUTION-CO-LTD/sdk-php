<?php

declare(strict_types=1);

// Verifies a bank slip image (JPEG, PNG, GIF or WebP, up to 4 MB) and matches the receiver
// against your registered bank accounts.
// Usage: php examples/verify-bank-image.php <image file>

use Thunder\Client;
use Thunder\Input\FileInput;
use Thunder\Input\VerifyOptions;

require __DIR__ . '/bootstrap.php';

$path = arg(0, '<image file>');

run(function () use ($path): mixed {
    $result = (new Client())->verify->bankByImage(FileInput::fromPath($path), new VerifyOptions(matchAccount: true));
    echo 'matched account: ', $result->matchedAccount->bankNumber ?? 'none', PHP_EOL;
    return $result;
});

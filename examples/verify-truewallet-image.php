<?php

declare(strict_types=1);

// Verifies a TrueMoney Wallet slip image.
// Usage: php examples/verify-truewallet-image.php <image file>

use Thunder\Client;
use Thunder\Input\FileInput;
use Thunder\Input\VerifyOptions;

require __DIR__ . '/bootstrap.php';

$path = arg(0, '<image file>');

run(function () use ($path): mixed {
    $result = (new Client())->verify->truewalletByImage(FileInput::fromPath($path), new VerifyOptions(checkDuplicate: true));
    echo 'transaction: ', $result->rawSlip->transactionId, ' amount: ', $result->rawSlip->amount, PHP_EOL;
    return $result;
});

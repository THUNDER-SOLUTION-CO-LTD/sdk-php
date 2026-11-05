<?php

declare(strict_types=1);

// Verifies a bank slip from the text of its QR code, and checks for a duplicate.
// Usage: php examples/verify-bank-payload.php <QR payload> [expected amount]

use Thunder\Client;
use Thunder\Input\VerifyOptions;

require __DIR__ . '/bootstrap.php';

$payload = arg(0, '<QR payload> [expected amount]');
$amount = optionalArg(1);

run(function () use ($payload, $amount): mixed {
    $result = (new Client())->verify->bankByPayload($payload, new VerifyOptions(
        matchAmount: $amount === null ? null : (float) $amount,
        checkDuplicate: true,
    ));
    echo 'transRef: ', $result->rawSlip->transRef, ' duplicate: ', var_export($result->isDuplicate, true), PHP_EOL;
    return $result;
});

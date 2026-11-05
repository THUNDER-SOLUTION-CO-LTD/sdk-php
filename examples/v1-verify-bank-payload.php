<?php

declare(strict_types=1);

// v1: verifies a bank slip from its QR payload. A slip you verified before throws
// DuplicateSlipError carrying the original slip (v1 does not charge for it).
// Usage: php examples/v1-verify-bank-payload.php <QR payload>

use Thunder\Client;
use Thunder\Exception\DuplicateSlipError;
use Thunder\Input\V1VerifyOptions;

require __DIR__ . '/bootstrap.php';

$payload = arg(0, '<QR payload>');

run(function () use ($payload): mixed {
    try {
        return (new Client())->v1->verifyBankByPayload($payload, new V1VerifyOptions(checkDuplicate: true));
    } catch (DuplicateSlipError $error) {
        return ['duplicateOf' => $error->slip];
    }
});

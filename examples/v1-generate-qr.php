<?php

declare(strict_types=1);

// v1: generates a PromptPay QR for a phone number and writes the PNG. Uses no quota.
// Usage: php examples/v1-generate-qr.php <phone number> [amount] [output.png]

use Thunder\Client;
use Thunder\Enum\QrType;
use Thunder\Input\QrRequest;

require __DIR__ . '/bootstrap.php';

$msisdn = arg(0, '<phone number> [amount] [output.png]');
$amount = optionalArg(1);
$out = optionalArg(2) ?? 'qr.png';

run(function () use ($msisdn, $amount, $out): mixed {
    $qr = (new Client())->v1->generateQr(new QrRequest(
        type: QrType::PROMPTPAY,
        msisdn: $msisdn,
        amount: $amount === null ? null : (float) $amount,
    ));
    file_put_contents($out, $qr->imageBytes());
    return ['payload' => $qr->payload, 'mime' => $qr->mime, 'written' => $out];
});

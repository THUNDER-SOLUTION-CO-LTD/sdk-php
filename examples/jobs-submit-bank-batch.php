<?php

declare(strict_types=1);

// Queues 1 to 100 slips in one call, one job per slip.
// Usage: php examples/jobs-submit-bank-batch.php <https callback URL> <QR payload> [QR payload ...]

use Thunder\Client;
use Thunder\Input\BatchOptions;
use Thunder\Input\BatchSlip;

require __DIR__ . '/bootstrap.php';

$usage = '<https callback URL> <QR payload> [QR payload ...]';
$callbackUrl = arg(0, $usage);
$slips = [new BatchSlip(arg(1, $usage), checkDuplicate: true)];
for ($i = 2; ($payload = optionalArg($i)) !== null; ++$i) {
    $slips[] = new BatchSlip($payload, checkDuplicate: true);
}

run(fn(): mixed => (new Client())->jobs->submitBankBatch($slips, new BatchOptions(callbackUrl: $callbackUrl)));

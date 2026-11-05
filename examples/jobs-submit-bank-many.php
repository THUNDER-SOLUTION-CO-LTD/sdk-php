<?php

declare(strict_types=1);

// Queues any number of slips from a text file (one QR payload per line), 100 per call.
// Usage: php examples/jobs-submit-bank-many.php <payload file> <https callback URL>

use Thunder\Client;
use Thunder\Exception\BatchSubmitError;
use Thunder\Input\BatchOptions;
use Thunder\Input\BatchSlip;

require __DIR__ . '/bootstrap.php';

$usage = '<payload file> <https callback URL>';
$lines = array_filter(array_map(trim(...), explode("\n", readLocalFile(arg(0, $usage)))), static fn(string $line): bool => $line !== '');
$slips = array_values(array_map(static fn(string $payload): BatchSlip => new BatchSlip($payload), $lines));
$callbackUrl = arg(1, $usage);

run(function () use ($slips, $callbackUrl): mixed {
    try {
        $batches = (new Client())->jobs->submitBankMany($slips, new BatchOptions(callbackUrl: $callbackUrl));
    } catch (BatchSubmitError $error) {
        fwrite(STDERR, 'chunk ' . $error->failedChunk . ' failed; ' . count($error->submitted) . ' accepted' . PHP_EOL);
        throw $error;
    }
    return array_map(static fn($batch): array => ['batchId' => $batch->batchId, 'jobs' => count($batch->jobs)], $batches);
});

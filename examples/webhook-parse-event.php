<?php

declare(strict_types=1);

// Parses a webhook body into a typed event, without checking the signature.
// Usage: php examples/webhook-parse-event.php <body file>

use Thunder\Webhook;

require __DIR__ . '/bootstrap.php';

$rawBody = readLocalFile(arg(0, '<body file>'));

run(function () use ($rawBody): mixed {
    $event = Webhook::parseEvent($rawBody);
    echo $event->jobId, ' ', $event->status, ' ', $event->data->rawSlip->transRef ?? $event->error->code ?? '', PHP_EOL;
    return null;
});

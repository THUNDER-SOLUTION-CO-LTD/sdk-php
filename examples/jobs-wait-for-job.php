<?php

declare(strict_types=1);

// Polls a job every 2 seconds until it is done or failed, for up to 10 minutes.
// Usage: php examples/jobs-wait-for-job.php <job id>

use Thunder\Client;
use Thunder\Exception\JobWaitTimeoutError;
use Thunder\Input\WaitOptions;

require __DIR__ . '/bootstrap.php';

$jobId = arg(0, '<job id>');

run(function () use ($jobId): mixed {
    try {
        $job = (new Client())->jobs->waitForJob($jobId, new WaitOptions(interval: 2.0, timeout: 600.0));
    } catch (JobWaitTimeoutError $error) {
        return ['stillRunning' => $error->lastJob];
    }
    echo 'status: ', $job->status, ' result: ', $job->result->status ?? 'none', PHP_EOL;
    return $job;
});

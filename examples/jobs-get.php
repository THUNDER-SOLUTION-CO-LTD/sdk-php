<?php

declare(strict_types=1);

// Gets a background job by id.
// Usage: php examples/jobs-get.php <job id>

use Thunder\Client;

require __DIR__ . '/bootstrap.php';

$jobId = arg(0, '<job id>');

run(fn(): mixed => (new Client())->jobs->get($jobId));

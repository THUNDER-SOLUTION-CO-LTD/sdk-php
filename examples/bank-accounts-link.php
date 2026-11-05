<?php

declare(strict_types=1);

// Links an existing account of the service to this branch. Linking twice is fine.
// Usage: php examples/bank-accounts-link.php <id>

use Thunder\Client;

require __DIR__ . '/bootstrap.php';

$id = (int) arg(0, '<id>');

run(fn(): mixed => (new Client())->bankAccounts->link($id));

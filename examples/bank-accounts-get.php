<?php

declare(strict_types=1);

// Gets one bank account linked to this branch.
// Usage: php examples/bank-accounts-get.php <id>

use Thunder\Client;

require __DIR__ . '/bootstrap.php';

$id = (int) arg(0, '<id>');

run(fn(): mixed => (new Client())->bankAccounts->get($id));

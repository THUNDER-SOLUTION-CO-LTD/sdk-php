<?php

declare(strict_types=1);

// Unlinks an account from this branch. The account stays on the service.
// Usage: php examples/bank-accounts-unlink.php <id>

use Thunder\Client;

require __DIR__ . '/bootstrap.php';

$id = (int) arg(0, '<id>');

run(fn(): mixed => (new Client())->bankAccounts->unlink($id));

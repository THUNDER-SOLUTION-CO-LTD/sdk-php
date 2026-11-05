<?php

declare(strict_types=1);

// One page of the bank accounts linked to this branch.
// Usage: php examples/bank-accounts-list.php [page] [limit]

use Thunder\Client;
use Thunder\Input\ListBankAccountsParams;

require __DIR__ . '/bootstrap.php';

$page = (int) (optionalArg(0) ?? 1);
$limit = (int) (optionalArg(1) ?? 20);

run(fn(): mixed => (new Client())->bankAccounts->list(new ListBankAccountsParams(page: $page, limit: $limit)));

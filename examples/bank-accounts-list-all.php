<?php

declare(strict_types=1);

// One page of every bank account of the service, linked to this branch or not.
// Usage: php examples/bank-accounts-list-all.php [bank code]

use Thunder\Client;
use Thunder\Input\ListBankAccountsParams;

require __DIR__ . '/bootstrap.php';

$bankCode = optionalArg(0);

run(fn(): mixed => (new Client())->bankAccounts->listAll(new ListBankAccountsParams(bankCode: $bankCode)));

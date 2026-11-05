<?php

declare(strict_types=1);

// Walks every page of the accounts linked to this branch.
// Usage: php examples/bank-accounts-iterate.php

use Thunder\Client;

require __DIR__ . '/bootstrap.php';

run(function (): mixed {
    $count = 0;
    foreach ((new Client())->bankAccounts->iterate() as $account) {
        echo $account->id, ' ', $account->bankCode, ' ', $account->bankNumber, ' ', $account->nameEn, PHP_EOL;
        ++$count;
    }
    return ['count' => $count];
});

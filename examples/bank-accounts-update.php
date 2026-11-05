<?php

declare(strict_types=1);

// Changes the English name of a bank account. Pass "clear" as the second argument to clear
// its extraVerify instead.
// Usage: php examples/bank-accounts-update.php <id> <new English name | clear>

use Thunder\Client;
use Thunder\Input\UpdateBankAccountParams;

require __DIR__ . '/bootstrap.php';

$id = (int) arg(0, '<id> <new English name | clear>');
$value = arg(1, '<id> <new English name | clear>');

run(fn(): mixed => (new Client())->bankAccounts->update(
    $id,
    $value === 'clear' ? new UpdateBankAccountParams(extraVerify: null) : new UpdateBankAccountParams(nameEn: $value),
));

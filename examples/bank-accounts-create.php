<?php

declare(strict_types=1);

// Registers a receiving bank account and links it to this branch.
// Usage: php examples/bank-accounts-create.php <bank code> <account number> <Thai name> <English name> [NATURAL|JURISTIC]

use Thunder\Client;
use Thunder\Enum\AccountType;
use Thunder\Exception\ConflictError;
use Thunder\Input\CreateBankAccountParams;

require __DIR__ . '/bootstrap.php';

$usage = '<bank code> <account number> <Thai name> <English name> [NATURAL|JURISTIC]';
$params = new CreateBankAccountParams(
    bankCode: arg(0, $usage),
    bankNumber: arg(1, $usage),
    nameTh: arg(2, $usage),
    nameEn: arg(3, $usage),
    type: AccountType::from(optionalArg(4) ?? 'NATURAL'),
);

run(function () use ($params): mixed {
    try {
        return (new Client())->bankAccounts->create($params);
    } catch (ConflictError $error) {
        return 'already registered: ' . $error->getMessage();
    }
});

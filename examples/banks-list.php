<?php

declare(strict_types=1);

// Lists the bank codes you can register accounts with.
// Usage: php examples/banks-list.php [bank code]

use Thunder\Client;

require __DIR__ . '/bootstrap.php';

$code = optionalArg(0);

run(function () use ($code): mixed {
    foreach ((new Client())->banks->list() as $bank) {
        if ($code === null) {
            echo $bank->code, ' ', $bank->shortCode, ' ', $bank->nameEn, PHP_EOL;
        } elseif ($bank->code === $code) {
            return $bank;
        }
    }
    return $code === null ? null : 'no bank with code ' . $code;
});

<?php

declare(strict_types=1);

// Application, branch and quota of the API key.
// Usage: php examples/get-info.php

use Thunder\Client;

require __DIR__ . '/bootstrap.php';

run(function (): mixed {
    $info = (new Client())->getInfo();
    echo 'request id: ', $info->meta->requestId ?? 'none', PHP_EOL;
    return $info;
});

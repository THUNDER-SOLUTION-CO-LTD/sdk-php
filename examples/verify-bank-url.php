<?php

declare(strict_types=1);

// Verifies a bank slip image that the API downloads from a public URL.
// Usage: php examples/verify-bank-url.php <image URL>

use Thunder\Client;
use Thunder\Input\VerifyOptions;

require __DIR__ . '/bootstrap.php';

$url = arg(0, '<image URL>');

run(fn(): mixed => (new Client())->verify->bankByUrl($url, new VerifyOptions(remark: 'example')));

<?php

declare(strict_types=1);

// v1: quota and credit of the application.
// Usage: php examples/v1-get-me.php

use Thunder\Client;

require __DIR__ . '/bootstrap.php';

run(fn(): mixed => (new Client())->v1->getMe());

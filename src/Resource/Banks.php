<?php

declare(strict_types=1);

namespace Thunder\Resource;

use Thunder\Model\BankList;
use Thunder\Transport\Api;

/** Bank catalogue. */
final readonly class Banks
{
    public function __construct(private Api $api) {}
    /** Read supported banks and extra verification choices. */
    public function list(): BankList
    {
        return $this->api->banks();
    }
}

<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Integer rate-limit headers supplied together by the server. */
final readonly class RateLimit
{
    public function __construct(public int $limit, public int $remaining, public int $reset) {}
}

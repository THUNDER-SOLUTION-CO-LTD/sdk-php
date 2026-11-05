<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Transport metadata, separate from the API data's JSON representation. */
final readonly class ResponseMeta
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $status = 0,
        public ?string $requestId = null,
        public ?string $message = null,
        public ?RateLimit $rateLimit = null,
        public array $headers = [],
    ) {}
}

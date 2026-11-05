<?php

declare(strict_types=1);

namespace Thunder\Exception;

/** An unsuccessful API response, with sanitized diagnostic metadata. */
class ApiException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly ?string $apiCode,
        string $message,
        public readonly ?string $requestId,
        public readonly string $rawBody,
    ) {
        parent::__construct($message, $status);
    }
}

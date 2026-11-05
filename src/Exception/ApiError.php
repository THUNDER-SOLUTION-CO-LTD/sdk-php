<?php

declare(strict_types=1);

namespace Thunder\Exception;

use Thunder\Model\RateLimit;

/** HTTP failure. The string API code is available as apiCode and through $error->code.
 * @property-read ?string $code
 * @property-read string $message
 */
class ApiError extends ThunderError
{
    /** @var \WeakMap<self, string>|null */
    private static ?\WeakMap $sensitiveBodies = null;

    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        public readonly ?string $apiCode,
        string $message,
        public readonly ?string $requestId = null,
        public readonly string $rawBody = '',
        public readonly array $headers = [],
        public readonly mixed $details = null,
        public readonly ?float $retryAfter = null,
        public readonly ?RateLimit $rateLimit = null,
        #[\SensitiveParameter]
        ?string $originalBody = null,
    ) {
        parent::__construct($message, $status);
        self::$sensitiveBodies ??= new \WeakMap();
        self::$sensitiveBodies[$this] = $originalBody ?? $rawBody;
    }

    /** Explicit opt-in to the original response, which may contain secrets. */
    public function sensitiveRawBody(): string
    {
        return self::$sensitiveBodies[$this] ?? $this->rawBody;
    }

    /** Preserve PHP's integer getCode() while exposing the API's string code. */
    public function __get(string $name): ?string
    {
        return match ($name) {
            'code' => $this->apiCode,
            'message' => $this->getMessage(),
            default => throw new \LogicException('Unknown error property'),
        };
    }

    /** A concise diagnostic that excludes credentials and request bodies. */
    public function __toString(): string
    {
        return '[' . $this->status . ($this->apiCode === null ? '' : ' ' . $this->apiCode) . '] ' . $this->getMessage();
    }
}

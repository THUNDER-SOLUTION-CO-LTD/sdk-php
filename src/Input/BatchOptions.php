<?php

declare(strict_types=1);

namespace Thunder\Input;

/** Immutable request parameters; omitted fields are not sent. */
final readonly class BatchOptions implements \JsonSerializable
{
    /** Validate local arguments before sending a request. */
    public function __construct(
        public ?string $callbackUrl = null,
    ) {
        if ($this->callbackUrl !== null) {
            Validation::text($this->callbackUrl, 'callbackUrl', 255);
        }
        if ($this->callbackUrl !== null) {
            Validation::url($this->callbackUrl, 255, true);
        }
    }

    /** @return array<string, scalar|null> */
    public function jsonSerialize(): array
    {
        $result = [];
        if ($this->callbackUrl !== null) {
            $result['callbackUrl'] = $this->callbackUrl;
        }
        return $result;
    }
}

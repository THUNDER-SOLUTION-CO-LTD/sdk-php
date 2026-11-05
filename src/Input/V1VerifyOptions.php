<?php

declare(strict_types=1);

namespace Thunder\Input;

/** Immutable request parameters; omitted fields are not sent. */
final readonly class V1VerifyOptions implements \JsonSerializable
{
    /** Validate local arguments before sending a request. */
    public function __construct(
        public ?bool $checkDuplicate = null,
    ) {}

    /** @return array<string, scalar|null> */
    public function jsonSerialize(): array
    {
        $result = [];
        if ($this->checkDuplicate !== null) {
            $result['checkDuplicate'] = $this->checkDuplicate;
        }
        return $result;
    }
}

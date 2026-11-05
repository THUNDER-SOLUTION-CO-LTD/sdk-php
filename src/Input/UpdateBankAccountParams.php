<?php

declare(strict_types=1);

namespace Thunder\Input;

use Thunder\Enum\AccountType;
use Thunder\Enum\MatchMode;

/** Immutable request parameters; omitted fields are not sent. */
final readonly class UpdateBankAccountParams implements \JsonSerializable
{
    /** Validate local arguments before sending a request. */
    public function __construct(
        public ?string $bankCode = null,
        public ?string $bankNumber = null,
        public ?string $nameTh = null,
        public ?string $nameEn = null,
        public ?AccountType $type = null,
        public string|UnsetValue|null $extraVerify = UnsetValue::Omitted,
        public ?MatchMode $matchMode = null,
    ) {}

    /** @return array<string, scalar|null> */
    public function jsonSerialize(): array
    {
        $result = [];
        if ($this->bankCode !== null) {
            $result['bankCode'] = $this->bankCode;
        }
        if ($this->bankNumber !== null) {
            $result['bankNumber'] = $this->bankNumber;
        }
        if ($this->nameTh !== null) {
            $result['nameTh'] = $this->nameTh;
        }
        if ($this->nameEn !== null) {
            $result['nameEn'] = $this->nameEn;
        }
        if ($this->type !== null) {
            $result['type'] = $this->type->value;
        }
        if ($this->extraVerify !== UnsetValue::Omitted) {
            $result['extraVerify'] = $this->extraVerify;
        }
        if ($this->matchMode !== null) {
            $result['matchMode'] = $this->matchMode->value;
        }
        return $result;
    }
}

<?php

declare(strict_types=1);

namespace Thunder\Input;

use Thunder\Enum\AccountType;
use Thunder\Enum\MatchMode;

/** Immutable request parameters; omitted fields are not sent. */
final readonly class CreateBankAccountParams implements \JsonSerializable
{
    /** Validate local arguments before sending a request. */
    public function __construct(
        public string $bankCode,
        public string $bankNumber,
        public string $nameTh,
        public string $nameEn,
        public AccountType $type,
        public ?string $extraVerify = null,
        public ?MatchMode $matchMode = null,
    ) {}

    /** @return array<string, scalar|null> */
    public function jsonSerialize(): array
    {
        $result = [];
        $result['bankCode'] = $this->bankCode;
        $result['bankNumber'] = $this->bankNumber;
        $result['nameTh'] = $this->nameTh;
        $result['nameEn'] = $this->nameEn;
        $result['type'] = $this->type->value;
        if ($this->extraVerify !== null) {
            $result['extraVerify'] = $this->extraVerify;
        }
        if ($this->matchMode !== null) {
            $result['matchMode'] = $this->matchMode->value;
        }
        return $result;
    }
}

<?php

declare(strict_types=1);

namespace Thunder\Input;

use Thunder\Enum\AccountType;

/** Immutable request parameters; omitted fields are not sent. */
final readonly class ListBankAccountsParams implements \JsonSerializable
{
    /** Validate local arguments before sending a request. */
    public function __construct(
        public ?AccountType $type = null,
        public ?string $bankCode = null,
        public ?int $page = null,
        public ?int $limit = null,
    ) {
        if ($page !== null && $page < 1) {
            Validation::fail('page must be at least 1');
        }
        if ($limit !== null && ($limit < 1 || $limit > 100)) {
            Validation::fail('limit must be between 1 and 100');
        }
    }

    /** @return array<string, scalar|null> */
    public function jsonSerialize(): array
    {
        $result = [];
        if ($this->type !== null) {
            $result['type'] = $this->type->value;
        }
        if ($this->bankCode !== null) {
            $result['bankCode'] = $this->bankCode;
        }
        if ($this->page !== null) {
            $result['page'] = $this->page;
        }
        if ($this->limit !== null) {
            $result['limit'] = $this->limit;
        }
        return $result;
    }
}

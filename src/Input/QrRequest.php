<?php

declare(strict_types=1);

namespace Thunder\Input;

use Thunder\Enum\QrType;

/** Immutable request parameters; omitted fields are not sent. */
final readonly class QrRequest implements \JsonSerializable
{
    /** Validate local arguments before sending a request. */
    public function __construct(
        public QrType $type,
        public ?string $msisdn = null,
        public ?string $natId = null,
        public ?string $eWalletId = null,
        public ?string $billerId = null,
        public ?string $ref1 = null,
        public ?string $ref2 = null,
        public ?string $merchantName = null,
        public ?float $amount = null,
    ) {
        if ($this->amount !== null && !is_finite($this->amount)) {
            Validation::fail('amount must be finite');
        }
    }

    /** @return array<string, scalar|null> */
    public function jsonSerialize(): array
    {
        $result = [];
        $result['type'] = $this->type->value;
        if ($this->msisdn !== null) {
            $result['msisdn'] = $this->msisdn;
        }
        if ($this->natId !== null) {
            $result['natId'] = $this->natId;
        }
        if ($this->eWalletId !== null) {
            $result['eWalletId'] = $this->eWalletId;
        }
        if ($this->billerId !== null) {
            $result['billerId'] = $this->billerId;
        }
        if ($this->ref1 !== null) {
            $result['ref1'] = $this->ref1;
        }
        if ($this->ref2 !== null) {
            $result['ref2'] = $this->ref2;
        }
        if ($this->merchantName !== null) {
            $result['merchantName'] = $this->merchantName;
        }
        if ($this->amount !== null) {
            $result['amount'] = $this->amount;
        }
        return $result;
    }
}

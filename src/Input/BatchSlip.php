<?php

declare(strict_types=1);

namespace Thunder\Input;

/** Immutable request parameters; omitted fields are not sent. */
final readonly class BatchSlip implements \JsonSerializable
{
    /** Validate local arguments before sending a request. */
    public function __construct(
        public string $payload,
        public ?string $remark = null,
        public ?bool $matchAccount = null,
        public ?float $matchAmount = null,
        public ?bool $checkDuplicate = null,
    ) {
        Validation::text($this->payload, 'payload', 128);
        if ($this->remark !== null) {
            Validation::text($this->remark, 'remark', 255);
        }
        if ($this->matchAmount !== null && !is_finite($this->matchAmount)) {
            Validation::fail('matchAmount must be finite');
        }
    }

    /** @return array<string, scalar|null> */
    public function jsonSerialize(): array
    {
        $result = [];
        $result['payload'] = $this->payload;
        if ($this->remark !== null) {
            $result['remark'] = $this->remark;
        }
        if ($this->matchAccount !== null) {
            $result['matchAccount'] = $this->matchAccount;
        }
        if ($this->matchAmount !== null) {
            $result['matchAmount'] = $this->matchAmount;
        }
        if ($this->checkDuplicate !== null) {
            $result['checkDuplicate'] = $this->checkDuplicate;
        }
        return $result;
    }
}

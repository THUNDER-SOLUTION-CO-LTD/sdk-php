<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class TrueWalletResult extends Model
{
    public ?string $remark;
    public ?bool $isDuplicate;
    public ?MatchedAccount $matchedAccount;
    public ?float $amountInOrder;
    public ?bool $isAmountMatched;
    public float $amountInSlip;
    public TrueWalletSlip $rawSlip;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->remark = !isset($data['remark']) ? null : $this->string($data, 'remark');
        $this->isDuplicate = !isset($data['isDuplicate']) ? null : $this->bool($data, 'isDuplicate');
        $this->matchedAccount = !isset($data['matchedAccount']) ? null : new MatchedAccount($this->object($data, 'matchedAccount'));
        $this->amountInOrder = !isset($data['amountInOrder']) ? null : $this->float($data, 'amountInOrder');
        $this->isAmountMatched = !isset($data['isAmountMatched']) ? null : $this->bool($data, 'isAmountMatched');
        $this->amountInSlip = $this->float($data, 'amountInSlip');
        $this->rawSlip = new TrueWalletSlip($this->object($data, 'rawSlip'));
    }
}

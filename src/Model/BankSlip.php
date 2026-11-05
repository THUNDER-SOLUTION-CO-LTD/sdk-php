<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class BankSlip extends Model
{
    public string $payload;
    public string $transRef;
    public string $date;
    public string $countryCode;
    public SlipAmount $amount;
    public ?float $fee;
    public ?string $ref1;
    public ?string $ref2;
    public ?string $ref3;
    public SlipParty $sender;
    public SlipReceiver $receiver;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->payload = $this->string($data, 'payload');
        $this->transRef = $this->string($data, 'transRef');
        $this->date = $this->string($data, 'date');
        $this->countryCode = $this->string($data, 'countryCode');
        $this->amount = new SlipAmount($this->object($data, 'amount'));
        $this->fee = !isset($data['fee']) ? null : $this->float($data, 'fee');
        $this->ref1 = !isset($data['ref1']) ? null : $this->string($data, 'ref1');
        $this->ref2 = !isset($data['ref2']) ? null : $this->string($data, 'ref2');
        $this->ref3 = !isset($data['ref3']) ? null : $this->string($data, 'ref3');
        $this->sender = new SlipParty($this->object($data, 'sender'));
        $this->receiver = new SlipReceiver($this->object($data, 'receiver'));
    }
}

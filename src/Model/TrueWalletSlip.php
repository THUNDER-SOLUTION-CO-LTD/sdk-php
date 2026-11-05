<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class TrueWalletSlip extends Model
{
    public string $transactionId;
    public string $date;
    public float $amount;
    public TrueWalletSender $sender;
    public TrueWalletReceiver $receiver;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->transactionId = $this->string($data, 'transactionId');
        $this->date = $this->string($data, 'date');
        $this->amount = $this->float($data, 'amount');
        $this->sender = new TrueWalletSender($this->object($data, 'sender'));
        $this->receiver = new TrueWalletReceiver($this->object($data, 'receiver'));
    }
}

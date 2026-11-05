<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class SlipParty extends Model
{
    public ?SlipBank $bank;
    public ?SlipAccount $account;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->bank = !isset($data['bank']) ? null : new SlipBank($this->object($data, 'bank'));
        $this->account = !isset($data['account']) ? null : new SlipAccount($this->object($data, 'account'));
    }
}

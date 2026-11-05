<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class SlipAccount extends Model
{
    public AccountName $name;
    public ?AccountNumber $bank;
    public ?AccountNumber $proxy;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->name = new AccountName($this->object($data, 'name'));
        $this->bank = !isset($data['bank']) ? null : new AccountNumber($this->object($data, 'bank'));
        $this->proxy = !isset($data['proxy']) ? null : new AccountNumber($this->object($data, 'proxy'));
    }
}

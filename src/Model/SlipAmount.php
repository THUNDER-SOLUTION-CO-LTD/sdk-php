<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class SlipAmount extends Model
{
    public float $amount;
    public LocalAmount $local;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->amount = $this->float($data, 'amount');
        $this->local = new LocalAmount($this->object($data, 'local'));
    }
}

<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class LocalAmount extends Model
{
    public ?float $amount;
    public ?string $currency;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->amount = !isset($data['amount']) ? null : $this->float($data, 'amount');
        $this->currency = !isset($data['currency']) ? null : $this->string($data, 'currency');
    }
}

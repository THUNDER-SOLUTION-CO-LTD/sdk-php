<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class InfoAccount extends Model
{
    public string $email;
    public float $credit;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->email = $this->string($data, 'email');
        $this->credit = $this->float($data, 'credit');
    }
}

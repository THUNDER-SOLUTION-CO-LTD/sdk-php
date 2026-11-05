<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class AccountNumber extends Model
{
    public string $type;
    public string $account;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->type = $this->string($data, 'type');
        $this->account = $this->string($data, 'account');
    }
}

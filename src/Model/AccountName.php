<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class AccountName extends Model
{
    public ?string $th;
    public ?string $en;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->th = !isset($data['th']) ? null : $this->string($data, 'th');
        $this->en = !isset($data['en']) ? null : $this->string($data, 'en');
    }
}

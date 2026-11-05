<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class ExtraVerifyOption extends Model
{
    public string $value;
    public string $label;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->value = $this->string($data, 'value');
        $this->label = $this->string($data, 'label');
    }
}

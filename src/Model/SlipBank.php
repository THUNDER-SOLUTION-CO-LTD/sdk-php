<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class SlipBank extends Model
{
    public ?string $id;
    public ?string $name;
    public ?string $short;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->id = !array_key_exists('id', $data) ? null : $this->string($data, 'id');
        $this->name = !isset($data['name']) ? null : $this->string($data, 'name');
        $this->short = !isset($data['short']) ? null : $this->string($data, 'short');
    }
}

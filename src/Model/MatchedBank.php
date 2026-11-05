<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class MatchedBank extends Model
{
    public ?string $nameTh;
    public ?string $nameEn;
    public ?string $code;
    public ?string $shortCode;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->nameTh = !isset($data['nameTh']) ? null : $this->string($data, 'nameTh');
        $this->nameEn = !isset($data['nameEn']) ? null : $this->string($data, 'nameEn');
        $this->code = !isset($data['code']) ? null : $this->string($data, 'code');
        $this->shortCode = !isset($data['shortCode']) ? null : $this->string($data, 'shortCode');
    }
}

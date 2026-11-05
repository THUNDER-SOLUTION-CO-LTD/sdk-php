<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class MatchedAccount extends Model
{
    public MatchedBank $bank;
    public string $nameTh;
    public string $nameEn;
    public string $type;
    public string $bankNumber;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->bank = new MatchedBank($this->object($data, 'bank'));
        $this->nameTh = $this->string($data, 'nameTh');
        $this->nameEn = $this->string($data, 'nameEn');
        $this->type = $this->string($data, 'type');
        $this->bankNumber = $this->string($data, 'bankNumber');
    }
}

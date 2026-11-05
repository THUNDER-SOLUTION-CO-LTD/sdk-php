<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class BankAccount extends Model
{
    public int $id;
    public string $bankCode;
    public string $bankNumber;
    public string $nameTh;
    public string $nameEn;
    public string $type;
    public ?string $extraVerify;
    public ?string $matchMode;
    public string $createdAt;
    public string $updatedAt;
    public ?string $deletedAt;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->id = $this->int($data, 'id');
        $this->bankCode = $this->string($data, 'bankCode');
        $this->bankNumber = $this->string($data, 'bankNumber');
        $this->nameTh = $this->string($data, 'nameTh');
        $this->nameEn = $this->string($data, 'nameEn');
        $this->type = $this->string($data, 'type');
        $this->extraVerify = !isset($data['extraVerify']) ? null : $this->string($data, 'extraVerify');
        $this->matchMode = !isset($data['matchMode']) ? null : $this->string($data, 'matchMode');
        $this->createdAt = $this->string($data, 'createdAt');
        $this->updatedAt = $this->string($data, 'updatedAt');
        $this->deletedAt = !isset($data['deletedAt']) ? null : $this->string($data, 'deletedAt');
    }
}

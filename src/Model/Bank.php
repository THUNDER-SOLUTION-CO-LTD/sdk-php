<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class Bank extends Model
{
    public string $code;
    public string $nameTh;
    public string $nameEn;
    public string $shortCode;
    public string $swiftCode;
    /** @var null|list<ExtraVerifyOption> */
    public ?array $extraVerify;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->code = $this->string($data, 'code');
        $this->nameTh = $this->string($data, 'nameTh');
        $this->nameEn = $this->string($data, 'nameEn');
        $this->shortCode = $this->string($data, 'shortCode');
        $this->swiftCode = $this->string($data, 'swiftCode');
        $this->extraVerify = !isset($data['extraVerify']) ? null : $this->models($data, 'extraVerify', ExtraVerifyOption::class);
    }
}

<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class JobResult extends Model
{
    public string $status;
    public ?BankSlipResult $data;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->status = $this->string($data, 'status');
        $this->data = !isset($data['data']) ? null : new BankSlipResult($this->object($data, 'data'));
    }
}

<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class WebhookEvent extends Model
{
    public string $jobId;
    public ?string $batchId;
    public string $status;
    public ?BankSlipResult $data;
    public ?WebhookError $error;
    public string $timestamp;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->jobId = $this->string($data, 'jobId');
        $this->batchId = !isset($data['batchId']) ? null : $this->string($data, 'batchId');
        $this->status = $this->string($data, 'status');
        $this->data = !isset($data['data']) ? null : new BankSlipResult($this->object($data, 'data'));
        $this->error = !isset($data['error']) ? null : new WebhookError($this->object($data, 'error'));
        $this->timestamp = $this->string($data, 'timestamp');
    }
}

<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class Job extends Model
{
    public string $jobId;
    public string $status;
    public int $attempts;
    public string $createdAt;
    public string $updatedAt;
    public ?string $batchId;
    public ?JobResult $result;
    public ?JobError $error;
    public ?int $branchId;
    public ?int $serviceId;
    public ?string $callbackEventId;
    public ?int $callbackProtocol;
    public ?CallbackDelivery $callbackDelivery;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->jobId = $this->string($data, 'jobId');
        $this->status = $this->string($data, 'status');
        $this->attempts = $this->int($data, 'attempts');
        $this->createdAt = $this->string($data, 'createdAt');
        $this->updatedAt = $this->string($data, 'updatedAt');
        $this->batchId = !isset($data['batchId']) ? null : $this->string($data, 'batchId');
        $this->result = !isset($data['result']) ? null : new JobResult($this->object($data, 'result'));
        $this->error = !isset($data['error']) ? null : new JobError($this->object($data, 'error'));
        $this->branchId = !isset($data['branchId']) ? null : $this->int($data, 'branchId');
        $this->serviceId = !isset($data['serviceId']) ? null : $this->int($data, 'serviceId');
        $this->callbackEventId = !isset($data['callbackEventId']) ? null : $this->string($data, 'callbackEventId');
        $this->callbackProtocol = !isset($data['callbackProtocol']) ? null : $this->int($data, 'callbackProtocol');
        $this->callbackDelivery = !isset($data['callbackDelivery']) ? null : new CallbackDelivery($this->object($data, 'callbackDelivery'));
    }
}

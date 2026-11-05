<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class BatchSubmission extends Model
{
    public string $batchId;
    /** @var list<BatchJob> */
    public array $jobs;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->batchId = $this->string($data, 'batchId');
        $this->jobs = $this->models($data, 'jobs', BatchJob::class);
    }
}

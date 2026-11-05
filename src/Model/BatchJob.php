<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class BatchJob extends Model
{
    public string $jobId;
    public int $index;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->jobId = $this->string($data, 'jobId');
        $this->index = $this->int($data, 'index');
    }
}

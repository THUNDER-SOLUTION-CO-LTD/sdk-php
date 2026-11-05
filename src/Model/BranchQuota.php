<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class BranchQuota extends Model
{
    public int $used;
    public int $totalUsed;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->used = $this->int($data, 'used');
        $this->totalUsed = $this->int($data, 'totalUsed');
    }
}

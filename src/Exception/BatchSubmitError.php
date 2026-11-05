<?php

declare(strict_types=1);

namespace Thunder\Exception;

use Thunder\Model\BatchSubmission;

/** Earlier batches may have spent quota; retry only the failed remainder. */
class BatchSubmitError extends ThunderError
{
    /** @param list<BatchSubmission> $submitted */
    public function __construct(public readonly array $submitted, public readonly int $failedChunk, public readonly ThunderError $cause)
    {
        parent::__construct('Batch submission stopped at chunk ' . $failedChunk, 0, $cause);
    }
}

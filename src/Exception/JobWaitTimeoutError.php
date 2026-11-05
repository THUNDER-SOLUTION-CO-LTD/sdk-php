<?php

declare(strict_types=1);

namespace Thunder\Exception;

use Thunder\Model\Job;

/** Polling deadline expired; the server job continues independently. */
class JobWaitTimeoutError extends ThunderError
{
    public function __construct(public readonly ?Job $lastJob)
    {
        parent::__construct('Timed out waiting for the job');
    }
}

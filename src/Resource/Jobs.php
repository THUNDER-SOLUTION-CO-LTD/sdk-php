<?php

declare(strict_types=1);

namespace Thunder\Resource;

use Thunder\Exception\{BatchSubmitError, InputError, JobWaitTimeoutError, RequestTimeoutError, ThunderError};
use Thunder\Input\{AsyncVerifyOptions, BatchOptions, BatchSlip, Validation, WaitOptions};
use Thunder\Model\{BatchSubmission, Job, JobSubmission};
use Thunder\Transport\Api;

final readonly class Jobs
{
    /** @param null|\Closure(float): void $sleep */
    public function __construct(private Api $api, private ?\Closure $sleep = null) {}
    /** Enqueue a bank verification; may spend quota. */
    public function submitBank(string $payload, ?AsyncVerifyOptions $options = null): JobSubmission
    {
        Validation::text($payload, 'payload', 128);
        return $this->api->call(JobSubmission::class, 'POST', '/verify/bank/async', ['payload' => $payload] + ($options?->jsonSerialize() ?? []));
    }
    /** @param list<BatchSlip> $slips */
    public function submitBankBatch(array $slips, ?BatchOptions $options = null): BatchSubmission
    {
        if ($slips === []) {
            throw new InputError('EMPTY_BATCH', 'At least one slip is required');
        }
        if (count($slips) > 100) {
            throw new InputError('BATCH_TOO_LARGE', 'At most 100 slips per batch');
        }
        return $this->api->call(BatchSubmission::class, 'POST', '/verify/bank/batch', ['slips' => array_map(static fn(BatchSlip $slip): array => $slip->jsonSerialize(), $slips)] + ($options?->jsonSerialize() ?? []));
    }
    /** @param list<BatchSlip> $slips
     * @return list<BatchSubmission>
     */
    public function submitBankMany(array $slips, ?BatchOptions $options = null): array
    {
        $submitted = [];
        foreach (array_chunk($slips, 100) as $index => $chunk) {
            try {
                $submitted[] = $this->submitBankBatch($chunk, $options);
            } catch (ThunderError $error) {
                throw new BatchSubmitError($submitted, $index, $error);
            }
        }
        return $submitted;
    }
    /** Read the current job state. */
    public function get(string $jobId): Job
    {
        Validation::text($jobId, 'jobId', 255);
        return $this->api->call(Job::class, 'GET', '/verify/bank/jobs/' . rawurlencode($jobId), safe: true);
    }
    /** Poll until done or failed; timeout carries the last observed job. */
    public function waitForJob(string $jobId, ?WaitOptions $options = null): Job
    {
        $options ??= new WaitOptions();
        $deadline = hrtime(true) / 1e9 + $options->timeout;
        $last = null;
        while (hrtime(true) / 1e9 < $deadline) {
            try {
                Validation::text($jobId, 'jobId', 255);
                $job = $this->api->call(Job::class, 'GET', '/verify/bank/jobs/' . rawurlencode($jobId), safe: true, deadline: $deadline);
            } catch (RequestTimeoutError $error) {
                if ($error instanceof \Thunder\Transport\DeadlineExceeded || hrtime(true) / 1e9 >= $deadline) {
                    break;
                }
                throw $error;
            }
            if (hrtime(true) / 1e9 >= $deadline) {
                break;
            }
            $last = $job;
            if ($last->status === 'done' || $last->status === 'failed') {
                return $last;
            }
            $remaining = $deadline - hrtime(true) / 1e9;
            if ($remaining <= 0) {
                break;
            }
            $seconds = min($remaining, $options->interval);
            if ($this->sleep !== null) {
                ($this->sleep)($seconds);
            } else {
                usleep((int) ($seconds * 1000000));
            }
        }
        throw new JobWaitTimeoutError($last);
    }
}

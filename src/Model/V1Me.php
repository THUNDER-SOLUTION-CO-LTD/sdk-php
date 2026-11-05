<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class V1Me extends Model
{
    public string $application;
    public int $usedQuota;
    public ?int $maxQuota;
    public ?int $remainingQuota;
    public ?string $expiredAt;
    public float $currentCredit;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->application = $this->string($data, 'application');
        $this->usedQuota = $this->int($data, 'usedQuota');
        $this->maxQuota = !isset($data['maxQuota']) ? null : $this->int($data, 'maxQuota');
        $this->remainingQuota = !isset($data['remainingQuota']) ? null : $this->int($data, 'remainingQuota');
        $this->expiredAt = !isset($data['expiredAt']) ? null : $this->string($data, 'expiredAt');
        $this->currentCredit = $this->float($data, 'currentCredit');
    }
}

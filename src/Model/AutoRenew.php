<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class AutoRenew extends Model
{
    public bool $expired;
    public bool $quota;
    public string $createdAt;
    public string $expiresAt;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->expired = $this->bool($data, 'expired');
        $this->quota = $this->bool($data, 'quota');
        $this->createdAt = $this->string($data, 'createdAt');
        $this->expiresAt = $this->string($data, 'expiresAt');
    }
}

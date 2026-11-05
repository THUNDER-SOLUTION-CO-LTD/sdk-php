<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class InfoApplication extends Model
{
    public string $name;
    public AutoRenew $autoRenew;
    public ApplicationQuota $quota;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->name = $this->string($data, 'name');
        $this->autoRenew = new AutoRenew($this->object($data, 'autoRenew'));
        $this->quota = new ApplicationQuota($this->object($data, 'quota'));
    }
}

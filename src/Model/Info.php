<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class Info extends Model
{
    public InfoApplication $application;
    public InfoBranch $branch;
    public InfoAccount $account;
    public InfoProduct $product;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->application = new InfoApplication($this->object($data, 'application'));
        $this->branch = new InfoBranch($this->object($data, 'branch'));
        $this->account = new InfoAccount($this->object($data, 'account'));
        $this->product = new InfoProduct($this->object($data, 'product'));
    }
}

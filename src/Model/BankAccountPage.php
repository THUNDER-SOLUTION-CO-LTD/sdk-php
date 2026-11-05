<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class BankAccountPage extends Model
{
    /** @var list<BankAccount> */
    public array $items;
    public int $total;
    public int $page;
    public int $limit;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->items = $this->models($data, 'items', BankAccount::class);
        $this->total = $this->int($data, 'total');
        $this->page = $this->int($data, 'page');
        $this->limit = $this->int($data, 'limit');
    }
}

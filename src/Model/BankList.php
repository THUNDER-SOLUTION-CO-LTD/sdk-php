<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Bank directory. Serializes to the API's plain array.
 * @implements \IteratorAggregate<int, Bank> */
final readonly class BankList implements \JsonSerializable, \IteratorAggregate, \Countable
{
    /** @var list<Bank> */
    public array $items;
    public ResponseMeta $meta;

    /** @param list<array<string, mixed>> $data */
    public function __construct(private array $data, ?ResponseMeta $meta = null)
    {
        $this->items = array_map(static fn(array $row): Bank => new Bank($row), $data);
        $this->meta = $meta ?? new ResponseMeta();
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /** @return \Traversable<int, Bank> */
    public function getIterator(): \Traversable
    {
        yield from $this->items;
    }

    /** Number of banks returned. */
    public function count(): int
    {
        return count($this->items);
    }
}

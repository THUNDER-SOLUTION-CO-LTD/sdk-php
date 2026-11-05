<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Retains the original wire object, including unknown and explicitly null fields. */
abstract readonly class Model implements \JsonSerializable
{
    public ResponseMeta $meta;

    /** @param array<string, mixed> $raw */
    public function __construct(private array $raw, ?ResponseMeta $meta = null)
    {
        $this->meta = $meta ?? new ResponseMeta();
    }

    /** Whether a field was supplied, even if its value was null. */
    public function has(string $field): bool
    {
        return array_key_exists($field, $this->raw);
    }

    /** Serialize without metadata or invented defaults.
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->raw;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function object(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            throw new \UnexpectedValueException('Expected object at ' . $key);
        }
        foreach ($value as $k => $_) {
            if (!is_string($k)) {
                throw new \UnexpectedValueException('Expected object keys at ' . $key);
            }
        }
        return $value;
    }

    /** @template T of Model
     * @param array<string, mixed> $data
     * @param class-string<T> $class
     * @return list<T>
     */
    protected function models(array $data, string $key, string $class): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \UnexpectedValueException('Expected list at ' . $key);
        }
        $result = [];
        foreach ($value as $row) {
            $result[] = new $class($this->object(['row' => $row], 'row'));
        }
        return $result;
    }

    /** @param array<string, mixed> $data */
    protected function string(array $data, string $key): string
    {
        $v = $data[$key] ?? null;
        if (!is_string($v)) {
            throw new \UnexpectedValueException('Expected string at ' . $key);
        }
        return $v;
    }

    /** @param array<string, mixed> $data */
    protected function int(array $data, string $key): int
    {
        $v = $data[$key] ?? null;
        if (!is_int($v)) {
            throw new \UnexpectedValueException('Expected integer at ' . $key);
        }
        return $v;
    }

    /** @param array<string, mixed> $data */
    protected function float(array $data, string $key): float
    {
        $v = $data[$key] ?? null;
        if (!is_int($v) && !is_float($v)) {
            throw new \UnexpectedValueException('Expected number at ' . $key);
        }
        return (float) $v;
    }

    /** @param array<string, mixed> $data */
    protected function bool(array $data, string $key): bool
    {
        $v = $data[$key] ?? null;
        if (!is_bool($v)) {
            throw new \UnexpectedValueException('Expected boolean at ' . $key);
        }
        return $v;
    }
}

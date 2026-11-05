<?php

declare(strict_types=1);

namespace Thunder\Input;

use Thunder\Exception\InputError;

/** Shared request validation, never includes supplied values in errors. */
final class Validation
{
    public static function fail(string $message): never
    {
        throw new InputError('INVALID_ARGUMENT', $message);
    }
    public static function text(string $value, string $field, int $max): void
    {
        $count = preg_match_all('/./us', $value);
        if ($count === false || $count < 1 || $count > $max) {
            self::fail($field . ' has an invalid length');
        }
    }
    public static function url(string $value, int $max = 2048, bool $httpsOnly = false): void
    {
        self::text($value, 'url', $max);
        $parts = parse_url($value);
        if ($parts === false || empty($parts['host']) || !in_array($parts['scheme'] ?? '', $httpsOnly ? ['https'] : ['http', 'https'], true)) {
            self::fail('Expected an HTTP URL');
        }
    }
    public static function id(int $id): void
    {
        if ($id < 1) {
            self::fail('id must be positive');
        }
    }
}

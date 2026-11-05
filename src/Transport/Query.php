<?php

declare(strict_types=1);

namespace Thunder\Transport;

/** Encodes API query values without PHP's implicit boolean-to-integer conversion. */
final class Query
{
    /** @param array<string, scalar|null> $values */
    public static function encode(array $values): string
    {
        $normalized = [];
        foreach ($values as $name => $value) {
            if ($value === null) {
                continue;
            }
            $normalized[$name] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }
        return http_build_query($normalized, '', '&', PHP_QUERY_RFC3986);
    }
}

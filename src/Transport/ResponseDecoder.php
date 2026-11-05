<?php

declare(strict_types=1);

namespace Thunder\Transport;

use Psr\Http\Message\ResponseInterface;
use Thunder\Exception\ApiError;
use Thunder\Exception\DuplicateSlipError;
use Thunder\Exception\ErrorCodes;
use Thunder\Exception\ServerError;
use Thunder\Model\BankSlip;
use Thunder\Model\RateLimit;
use Thunder\Model\ResponseMeta;
use Thunder\Model\TrueWalletSlip;

final class ResponseDecoder
{
    /** Sanitize decoded strings too: JSON escapes must not bypass masking. */
    public static function redact(#[\SensitiveParameter] mixed $value, #[\SensitiveParameter] string $key): mixed
    {
        if (is_string($value)) {
            return self::decodedText($value, $key);
        }
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $name => $item) {
                $clean[is_string($name) ? self::decodedText($name, $key) : $name] = self::redact($item, $key);
            }
            return $clean;
        }
        if ($value instanceof \stdClass) {
            $clean = new \stdClass();
            foreach (get_object_vars($value) as $name => $item) {
                $clean->{self::decodedText($name, $key)} = self::redact($item, $key);
            }
            return $clean;
        }
        return $value;
    }

    /** Decode views until stable so mixed escaping cannot hide a credential. */
    private static function view(#[\SensitiveParameter] string $value): string
    {
        do {
            $before = $value;
            $value = rawurldecode($value);
            $value = preg_replace_callback('/(?:\\\\u[0-9a-fA-F]{4})+|\\\\\//', static function (array $match): string {
                $decoded = json_decode('"' . $match[0] . '"');
                return is_string($decoded) ? $decoded : $match[0];
            }, $value) ?? $value;
        } while ($before !== $value);
        return $value;
    }

    public static function carries(#[\SensitiveParameter] string $value, #[\SensitiveParameter] string $key): bool
    {
        return strlen($key) >= 4 && (str_contains($value, $key) || str_contains(self::view($value), $key));
    }

    private static function decodedText(#[\SensitiveParameter] string $value, #[\SensitiveParameter] string $key): string
    {
        if (!self::carries($value, $key)) {
            return $value;
        }
        return str_replace($key, '[REDACTED]', self::view(str_replace($key, '[REDACTED]', $value)));
    }

    /** Free-form diagnostics cannot preserve a potentially encoded secret. */
    public static function text(#[\SensitiveParameter] string $value, #[\SensitiveParameter] string $key): string
    {
        if (strlen($key) < 4) {
            return $value;
        }
        $clean = str_replace($key, '[REDACTED]', $value);
        return self::carries($clean, $key) ? '[REDACTED]' : $clean;
    }

    public static function redacted(#[\SensitiveParameter] ResponseInterface $response, #[\SensitiveParameter] string $key): ResponseInterface
    {
        $raw = (string) $response->getBody();
        $decoded = json_decode($raw);
        $body = $raw;
        if (json_last_error() === JSON_ERROR_NONE) {
            $redacted = self::redact($decoded, $key);
            if ($redacted != $decoded) {
                $body = json_encode($redacted, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            }
        } else {
            $body = self::text($raw, $key);
        }
        $clean = $response->withBody(\GuzzleHttp\Psr7\Utils::streamFor($body));
        foreach ($response->getHeaders() as $name => $values) {
            $clean = $clean->withoutHeader($name)->withHeader(
                self::text($name, $key),
                in_array(strtolower($name), ['authorization', 'proxy-authorization'], true)
                    ? '[REDACTED]' : array_map(static fn(string $value): string => self::carries($value, $key) ? '[REDACTED]' : $value, $values)
            );
        }
        return $clean->withStatus($clean->getStatusCode(), self::text($response->getReasonPhrase(), $key));
    }

    /** @return array<string, string> */
    public static function headers(#[\SensitiveParameter] ResponseInterface $response): array
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower($name)] = implode(', ', $values);
        }
        return $headers;
    }

    public static function retryAfter(#[\SensitiveParameter] ResponseInterface $response): ?float
    {
        $value = $response->getHeaderLine('Retry-After');
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (float) $value;
        }
        $time = strtotime($value);
        return $time === false ? null : (float) max(0, $time - time());
    }

    public static function rateLimit(#[\SensitiveParameter] ResponseInterface $response): ?RateLimit
    {
        $values = [];
        foreach (['limit', 'remaining', 'reset'] as $field) {
            $value = $response->getHeaderLine('X-RateLimit-' . $field);
            if (!ctype_digit($value)) {
                return null;
            }
            $values[] = (int) $value;
        }
        return new RateLimit($values[0], $values[1], $values[2]);
    }

    public static function meta(#[\SensitiveParameter] ResponseInterface $response): ResponseMeta
    {
        $body = json_decode((string) $response->getBody(), true);
        $message = is_array($body) && is_string($body['message'] ?? null) ? $body['message'] : null;
        $id = $response->getHeaderLine('X-Request-Id') ?: $response->getHeaderLine('CF-Ray');
        return new ResponseMeta($response->getStatusCode(), $id === '' ? null : $id, $message, self::rateLimit($response), self::headers($response));
    }

    public static function error(#[\SensitiveParameter] ResponseInterface $response, #[\SensitiveParameter] string $key): ApiError
    {
        $original = (string) $response->getBody();
        $response = self::redacted($response, $key);
        $raw = (string) $response->getBody();
        $body = json_decode($raw, true);
        $body = is_array($body) ? $body : [];
        $nested = is_array($body['error'] ?? null) ? $body['error'] : [];
        $code = $nested['code'] ?? $body['code'] ?? $body['message'] ?? null;
        $code = is_string($code) ? $code : null;
        $message = $nested['message'] ?? $body['message'] ?? 'HTTP ' . $response->getStatusCode();
        $message = is_string($message) ? $message : 'API request failed';
        $status = $response->getStatusCode();
        $class = ErrorCodes::CODES[$code ?? ''] ?? ErrorCodes::STATUS[$status] ?? ($status >= 500 ? ServerError::class : ApiError::class);
        $meta = self::meta($response);
        $headers = $meta->headers;
        $error = new $class($status, $code, $message, $meta->requestId, $raw, $headers, $nested['details'] ?? $body['details'] ?? null, self::retryAfter($response), $meta->rateLimit, $original);
        if ($error instanceof DuplicateSlipError && is_array($body['data'] ?? null)) {
            /** @var array<string, mixed> $data */
            $data = $body['data'];
            $error->slip = isset($data['transactionId']) ? new TrueWalletSlip($data) : new BankSlip($data);
        }
        return $error;
    }
}

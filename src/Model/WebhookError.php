<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class WebhookError extends Model
{
    public ?string $code;
    public ?string $message;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->code = !isset($data['code']) ? null : $this->string($data, 'code');
        $this->message = !isset($data['message']) ? null : $this->string($data, 'message');
    }
}

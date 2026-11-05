<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class JobError extends Model
{
    public ?string $code;
    public ?string $message;
    public ?string $webhook;
    public ?string $url;
    public ?string $at;
    public ?int $status;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->code = !isset($data['code']) ? null : $this->string($data, 'code');
        $this->message = !isset($data['message']) ? null : $this->string($data, 'message');
        $this->webhook = !isset($data['webhook']) ? null : $this->string($data, 'webhook');
        $this->url = !isset($data['url']) ? null : $this->string($data, 'url');
        $this->at = !isset($data['at']) ? null : $this->string($data, 'at');
        $this->status = !isset($data['status']) ? null : $this->int($data, 'status');
    }
}

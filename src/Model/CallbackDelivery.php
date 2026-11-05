<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class CallbackDelivery extends Model
{
    public ?string $state;
    public ?int $httpStatus;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->state = !isset($data['state']) ? null : $this->string($data, 'state');
        $this->httpStatus = !isset($data['httpStatus']) ? null : $this->int($data, 'httpStatus');
    }
}

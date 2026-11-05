<?php

declare(strict_types=1);

namespace Thunder\Model;

/** Typed API data; has() distinguishes omitted fields from explicit null. */
final readonly class QrCode extends Model
{
    public string $image;
    public string $mime;
    public string $payload;

    /** @param array<string, mixed> $data */
    public function __construct(array $data, ?ResponseMeta $meta = null)
    {
        parent::__construct($data, $meta);
        $this->image = $this->string($data, 'image');
        $this->mime = $this->string($data, 'mime');
        $this->payload = $this->string($data, 'payload');
    }

    /** Decode the API's PNG base64 image. */
    public function imageBytes(): string
    {
        $bytes = base64_decode($this->image, true);
        if ($bytes === false) {
            throw new \UnexpectedValueException('Invalid QR image base64');
        }
        return $bytes;
    }
}

<?php

declare(strict_types=1);

namespace Thunder\Transport;

/** A diagnostic copy, never a link to the transport exception or its properties. */
final class SanitizedCause extends \RuntimeException
{
    public readonly string $sourceType;
    public readonly int|string $sourceCode;

    public function __construct(#[\SensitiveParameter] \Throwable $cause, #[\SensitiveParameter] string $key)
    {
        $this->sourceType = ResponseDecoder::text($cause::class, $key);
        $code = $cause->getCode();
        $this->sourceCode = is_string($code) ? ResponseDecoder::text($code, $key) : $code;
        parent::__construct(ResponseDecoder::text($cause->getMessage(), $key));
    }
}

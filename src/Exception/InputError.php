<?php

declare(strict_types=1);

namespace Thunder\Exception;

/** Local validation failure before a request is sent. @property-read string $code */
class InputError extends ThunderError
{
    public function __construct(public readonly string $inputCode, string $message)
    {
        parent::__construct($message);
    }

    /** Stable validation code, independent of PHP's integer getCode(). */
    public function __get(string $name): string
    {
        return $name === 'code' ? $this->inputCode : throw new \LogicException('Unknown error property');
    }
}

<?php

declare(strict_types=1);

namespace Thunder\Input;

/** Immutable request parameters; omitted fields are not sent. */
final readonly class WaitOptions implements \JsonSerializable
{
    /** Validate local arguments before sending a request. */
    public function __construct(
        public float $interval = 2.0,
        public float $timeout = 600.0,
    ) {
        if (!is_finite($interval) || $interval < 0.5) {
            Validation::fail('Invalid interval');
        }
        if (!is_finite($timeout) || $timeout < 0.001) {
            Validation::fail('Invalid timeout');
        }
    }

    /** @return array<string, scalar|null> */
    public function jsonSerialize(): array
    {
        $result = [];
        $result['interval'] = $this->interval;
        $result['timeout'] = $this->timeout;
        return $result;
    }
}

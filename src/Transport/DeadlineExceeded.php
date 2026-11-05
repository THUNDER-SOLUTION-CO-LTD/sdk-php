<?php

declare(strict_types=1);

namespace Thunder\Transport;

use Thunder\Exception\RequestTimeoutError;

/** @internal The request consumed the enclosing job wait budget. */
final class DeadlineExceeded extends RequestTimeoutError {}

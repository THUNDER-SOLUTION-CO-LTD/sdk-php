<?php

declare(strict_types=1);

namespace Thunder\Exception;

/** ServiceUnavailableError: see the API contract for retry and handling semantics. */
class ServiceUnavailableError extends ServerError {}

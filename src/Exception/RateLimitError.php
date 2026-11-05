<?php

declare(strict_types=1);

namespace Thunder\Exception;

/** RateLimitError: see the API contract for retry and handling semantics. */
class RateLimitError extends ApiError {}

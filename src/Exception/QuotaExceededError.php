<?php

declare(strict_types=1);

namespace Thunder\Exception;

/** QuotaExceededError: see the API contract for retry and handling semantics. */
class QuotaExceededError extends PermissionDeniedError {}

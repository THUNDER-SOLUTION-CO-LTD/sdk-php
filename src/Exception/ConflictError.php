<?php

declare(strict_types=1);

namespace Thunder\Exception;

/** ConflictError: see the API contract for retry and handling semantics. */
class ConflictError extends ApiError {}

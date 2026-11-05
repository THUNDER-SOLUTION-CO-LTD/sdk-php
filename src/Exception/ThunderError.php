<?php

declare(strict_types=1);

namespace Thunder\Exception;

/** Root of every error this SDK throws. Catch it to handle any SDK failure in one place. */
class ThunderError extends \RuntimeException {}

<?php

declare(strict_types=1);

namespace Thunder\Exception;

/** Transport failure; deliberately does not retain a request-bearing exception. */
class NetworkException extends \RuntimeException {}

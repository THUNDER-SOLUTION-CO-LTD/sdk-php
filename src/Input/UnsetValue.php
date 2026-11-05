<?php

declare(strict_types=1);

namespace Thunder\Input;

/** Distinguishes an omitted field from an explicit null. */
enum UnsetValue
{
    case Omitted;
}

<?php

declare(strict_types=1);

namespace Thunder\Exception;

use Thunder\Model\BankSlip;
use Thunder\Model\TrueWalletSlip;

/** v1 duplicate response with the original typed slip when supplied. */
class DuplicateSlipError extends InvalidRequestError
{
    public BankSlip|TrueWalletSlip|null $slip = null;
}

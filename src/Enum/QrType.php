<?php

declare(strict_types=1);

namespace Thunder\Enum;

/** Fixed request values. Response values remain open strings. */
enum QrType: string
{
    case PROMPTPAY = 'PROMPTPAY';
    case BILL_PAYMENT = 'BILL_PAYMENT';
    case KSHOP = 'KSHOP';
    case MAE_MANEE = 'MAE_MANEE';
    case TUNGNGERN = 'TUNGNGERN';
}

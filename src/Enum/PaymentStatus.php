<?php

namespace App\Enum;

enum PaymentStatus: string
{
    case PAID = 'paid';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
}

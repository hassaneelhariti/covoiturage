<?php

namespace App\Enum;

enum BookingStatus: string
{
    case CONFIRMED = 'confirmed';
    case CANCELLED_BY_PASSENGER = 'cancelled_by_passenger';
    case CANCELLED_BY_DRIVER = 'cancelled_by_driver';
    case COMPLETED = 'completed';
}

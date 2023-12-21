<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enum;

enum ParkingTicketSellingStatus
{
    use Enum;

    case NOT_YET_TIME;
    case SOLD_OUT;
    case AVAILABLE;
}

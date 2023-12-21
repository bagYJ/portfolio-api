<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enum;

enum ParkingType
{
    use Enum;

    case WEB;
    case AUTO;
}

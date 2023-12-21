<?php
declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enum;

enum BenefitKind: string
{
    use Enum;

    case OIL = '주유';
    case CHARGE = '충전';
}
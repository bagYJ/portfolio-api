<?php
declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enum;

enum BenefitType: string
{
    use Enum;

    case FNB = '식사/음료';
    case WASH = '세차';
    case PARKING = '주차';
    case SEND = '전달비';

    public static function benefit(): array
    {
        return [
            self::FNB,
            self::WASH,
            self::PARKING
        ];
    }
}
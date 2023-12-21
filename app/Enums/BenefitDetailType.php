<?php
declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enum;

enum BenefitDetailType: string
{
    use Enum;

    case COUPON = '쿠폰';
    case SALE = '상시할인';

    public static function couponUse(?array $type = []): bool
    {
        return in_array(self::COUPON->name, $type ?? []);
    }

    public static function saleUse(?array $type = []): bool
    {
        return in_array(self::SALE->name, $type ?? []);
    }
}
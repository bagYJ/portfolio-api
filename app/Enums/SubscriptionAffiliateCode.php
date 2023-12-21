<?php
declare(strict_types=1);

namespace App\Enums;

use App\Traits\Enum;
use Illuminate\Support\Collection;

enum SubscriptionAffiliateCode: string
{
    use Enum;

    case OWIN = '오윈';
    case HANA = '하나카드';
    case HYUNDAI = '현대캐피탈';

    public static function affiliateList(): Collection
    {
        return collect([
//            self::HYUNDAI,
            self::OWIN
        ]);
    }
}

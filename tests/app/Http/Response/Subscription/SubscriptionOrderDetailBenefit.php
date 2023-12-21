<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Subscription;

use OpenApi\Attributes as OA;

#[OA\Schema]
class SubscriptionOrderDetailBenefit
{
    #[OA\Property(description: '혜택 종류 (OIL: 주유, CHARGE: 충전, FNB: 식사/음료, WASH: 세차, PARKING: 주차)')]
    public string $benefit_type;
    #[OA\Property(description: '혜택 타입 (COUPON: 쿠폰, SALE: 상시할인)', items: new OA\Items(type: 'string'))]
    public array $type;
    #[OA\Property(description: '상시할인 혜택 정보')]
    public SubscriptionOrderDetailBenefitInfo $SALE;
    #[OA\Property(description: '쿠폰 혜택 정보', type: 'array', items: new OA\Items('#/components/schemas/SubscriptionOrderDetailBenefitInfo'))]
    public SubscriptionOrderDetailBenefitInfo $COUPON;
}

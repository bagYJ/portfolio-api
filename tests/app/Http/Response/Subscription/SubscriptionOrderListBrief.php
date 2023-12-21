<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Subscription;

use OpenApi\Attributes as OA;

#[OA\Schema]
class SubscriptionOrderListBrief
{
    #[OA\Property(description: '성공 여부')]
    public bool $result;
    #[OA\Property(description: '성공 여부', type: 'array', items: new OA\Items('#/components/schemas/SubscriptionOrderListBriefDetail'))]
    public SubscriptionOrderListBriefDetail $list;
}

#[OA\Schema]
class SubscriptionOrderListBriefDetail
{
    #[OA\Property(description: '구독주문키')]
    public int $no;
    #[OA\Property(description: '구독년도')]
    public string $year;
    #[OA\Property(description: '구독주문번호')]
    public string $no_order;
    #[OA\Property(description: '구독일')]
    public string $subscription_date;
    #[OA\Property(description: '구독상품번호')]
    public string $title;
    #[OA\Property(description: '구독상품금액')]
    public int $amount;
    #[OA\Property(description: '구독상품 (OIL: 주유/CHARGE: 충전)')]
    public string $kind;
    #[OA\Property(description: '제휴사코드')]
    public string $affiliate_code;
    #[OA\Property(description: '제휴사코드명')]
    public string $affiliate_code_name;
}

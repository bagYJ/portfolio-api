<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Subscription;

use OpenApi\Attributes as OA;

#[OA\Schema]
class SubscriptionOrderDetail
{
    #[OA\Property(description: '구독주문키')]
    public int $no;
    #[OA\Property(description: '주문번호')]
    public string $no_order;
    #[OA\Property(description: '구독상품번호')]
    public string $title;
    #[OA\Property(description: '제휴사코드 (OWIN: 오윈, HANA: 하나카드, HYUNDAI: 현대캐피탈)')]
    public string $affiliate_code;
    #[OA\Property(description: '구독혜택', type: 'array', items: new OA\Items('#/components/schemas/SubscriptionOrderDetailBenefit'))]
    public SubscriptionOrderDetailBenefit $benefit;
    #[OA\Property(description: '구독등록일')]
    public string $subscription_date;
    #[OA\Property(description: '구독종료일')]
    public string $subscription_end_date;
    #[OA\Property(description: '변경 여부')]
    public bool $is_changed;
    #[OA\Property(description: '다음 구독 상품번호')]
    public int $next_no_subsciption_product;
    #[OA\Property(description: '다음 구독일')]
    public string $next_subscription_date;
    #[OA\Property(description: '구독금액')]
    public int $amount;
    #[OA\Property(description: '카드번호')]
    public int $no_card;
    #[OA\Property(description: '사용자카드번호(카드번호뒤4자리)')]
    public string $no_card_user;
    #[OA\Property(description: '카드회사명')]
    public string $card_corp;
}

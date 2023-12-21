<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Subscription;

use OpenApi\Attributes as OA;

#[OA\Schema]
class SubscriptionPayment
{
    #[OA\Property(description: '성공 여부')]
    public bool $result;
    #[OA\Property(description: '주문번호')]
    public string $no_order;
    #[OA\Property(description: '주문상품명')]
    public string $nm_order;
    #[OA\Property(description: 'pg 메시지')]
    public int $pg_msg;
    #[OA\Property(description: '결과메시지')]
    public int $msg;
    #[OA\Property(description: '상품정보')]
    public SubscriptionProduct $product;
}

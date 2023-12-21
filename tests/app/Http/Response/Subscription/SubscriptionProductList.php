<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Subscription;

use OpenApi\Attributes as OA;

#[OA\Schema]
class SubscriptionProductList
{
    #[OA\Property(description: '성공 여부')]
    public bool $result;
    #[OA\Property(description: '구독상품', type: 'array', items: new OA\Items('#/components/schemas/SubscriptionProduct'))]
    public SubscriptionProduct $list;
}
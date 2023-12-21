<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Subscription;

use OpenApi\Attributes as OA;

#[OA\Schema]
class SubscriptionOrder
{
    #[OA\Property(description: '성공 여부')]
    public bool $result;
    #[OA\Property(description: '구독상태 (NOT_USE: 미사용, USE: 사용중, USED: 사용(현재 미사용))')]
    public string $status;
    #[OA\Property(description: '구독정보')]
    public SubscriptionOrderDetail $subscription;
}

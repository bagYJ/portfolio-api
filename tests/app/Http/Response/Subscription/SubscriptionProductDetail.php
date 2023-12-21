<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Subscription;

use OpenApi\Attributes as OA;

#[OA\Schema]
class SubscriptionProductDetail extends SubscriptionProduct
{
    #[OA\Property(description: '성공 여부')]
    public bool $result;
}

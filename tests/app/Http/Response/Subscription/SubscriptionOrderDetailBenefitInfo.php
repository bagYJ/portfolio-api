<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Subscription;

use OpenApi\Attributes as OA;

#[OA\Schema]
class SubscriptionOrderDetailBenefitInfo
{
    #[OA\Property(description: '혜택명')]
    public string $name;
    #[OA\Property(description: '혜택정보')]
    public string $benefit;
    #[OA\Property(description: '혜택사용건수')]
    public int $count;
    #[OA\Property(description: '혜택사용금액')]
    public int $amount;
    #[OA\Property(description: '혜택사용여부')]
    public bool $is_used;
}

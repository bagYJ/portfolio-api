<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Subscription;

use OpenApi\Attributes as OA;

#[OA\Schema]
class Benefit
{
    #[OA\Property(description: '할인방법 (PERCENT: 할인율, WON: 할인금액)')]
    public string $unit;
    #[OA\Property(description: 'PERCENT: 할인율, WON: 할인금액')]
    public int $price;
}

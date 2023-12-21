<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Product;

use OpenApi\Attributes as OA;

#[OA\Schema]
class CartResponse
{
    #[OA\Property(description: '상점 번호')]
    public int $no_shop;
    #[OA\Property(description: '업종')]
    public string $biz_kind;
    #[OA\Property(description: '매장명')]
    public string $nm_shop;
    #[OA\Property(description: '픽업방식')]
    public string $pickup_type;
    #[OA\Property(description: '결제금액')]
    public int $at_price_total;
    #[OA\Property(ref: '#/components/schemas/CartProduct')]
    public CartProduct $list_product;
}

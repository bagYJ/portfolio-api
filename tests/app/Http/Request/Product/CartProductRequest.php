<?php
declare(strict_types=1);

namespace Tests\app\Http\Request\Product;

use OpenApi\Attributes as OA;
use Tests\app\Http\Request\Order\OrderProduct;

#[OA\Schema(schema: 'CartProductRequest')]
class CartProductRequest
{
    #[OA\Property(description: '업종명', nullable: false)]
    public string $biz_kind;
    #[OA\Property(description: '픽업 타입 (CAR: 차안픽업, SHOP: 매장픽업)', nullable: false)]
    public string $pickup_type;
    #[OA\Property(description: '매장번호', nullable: false)]
    public int $no_shop;
    #[OA\Property(description: '결제금액', nullable: false)]
    public int $at_price_total;
    #[OA\Property(description: '주문 상품', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderProduct'), nullable: false)]
    public OrderProduct $list_product;
}

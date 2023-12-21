<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Product;

use OpenApi\Attributes as OA;

#[OA\Schema]
class ProductCartResponse
{
    #[OA\Property(description: '성공 여부')]
    public bool $result;
    #[OA\Property(description: '상점 번호')]
    public int $no_shop;
    #[OA\Property(ref: '#/components/schemas/CartProduct')]
    public CartProduct $product;
}

#[OA\Schema]
class CartProduct
{
    #[OA\Property(description: '상품 번호')]
    public int $no_product;
    #[OA\Property(description: '상품명')]
    public int $nm_product;
    #[OA\Property(description: '상품금액')]
    public int $at_price;
    #[OA\Property(description: '상품 현재금액')]
    public int $current_price;
    #[OA\Property(description: '상품 품절여부(Y:품절, N:판매중)')]
    public string $yn_soldout;
    #[OA\Property(type: 'array', items: new OA\Items(ref: '#/components/schemas/OptionGroup'))]
    public OptionGroup $option_groups;
}
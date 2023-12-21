<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Retail;

use OpenApi\Attributes as OA;

#[OA\Schema]
class RetailProductCartResponse
{
    #[OA\Property(description: '성공 여부')]
    public bool $result;
    #[OA\Property(description: '상점 번호')]
    public int $no_shop;
    #[OA\Property(ref: '#/components/schemas/CartRetailProduct')]
    public CartRetailProduct $product;
}

#[OA\Schema]
class CartRetailProduct
{
    #[OA\Property(description: '상품 번호')]
    public int $no_product;
    #[OA\Property(description: '상품명')]
    public int $nm_product;
    #[OA\Property(description: '상품금액')]
    public int $at_price;
    #[OA\Property(description: '옵션그룹', type: 'array', items: new OA\Items('#/components/schemas/ProductOptionGroups'))]
    public ProductOptionGroups $product_option_groups;
    #[OA\Property(description: '2+1 상품', type: 'array', items: new OA\Items('#/components/schemas/TwoPlusOneOption'))]
    public TwoPlusOneOption $two_plus_one_option;
}
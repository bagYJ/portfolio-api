<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Wash;

use OpenApi\Attributes as OA;
use Tests\app\Http\Response\OrderOil\MemberCarInfo;

#[OA\Schema]
class WashProductResponse
{
    #[OA\Property(description: '상태')]
    public bool $status;
    #[OA\Property(description: '사용자 차량 정보')]
    public MemberCarInfo $car_info;
    #[OA\Property(description: '상품 정보')]
    public HandWashProduct $products;
}

#[OA\Schema]
class HandWashProduct
{
    #[OA\Property(description: '상품 번호')]
    public int $no_product;
    #[OA\Property(description: '상세 ')]
    public int $cd_biz_kind_detail;
    #[OA\Property(description: '제휴사번호')]
    public int $no_partner;
    #[OA\Property(description: '매장번호')]
    public int $no_shop;
    #[OA\Property(description: '상품명')]
    public int $nm_product;
    #[OA\Property(description: '세차 최소 금액')]
    public int $min_price;
    #[OA\Property(description: '세차 최대 금액')]
    public int $max_price;
    #[OA\Property(description: '등록된 차량 정보의 세차 금액')]
    public int $price;
}

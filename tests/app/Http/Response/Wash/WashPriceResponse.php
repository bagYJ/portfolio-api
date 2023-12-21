<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Wash;

use OpenApi\Attributes as OA;
use Tests\app\Http\Response\OrderOil\MemberCarInfo;

#[OA\Schema]
class WashPriceResponse
{
    #[OA\Property(description: '상태')]
    public bool $result;
    #[OA\Property(description: '상품 정보')]
    public HandWashProductInfo $product;
    #[OA\Property(description: '가격정보 정보')]
    public HandWashCar $cars;
}


#[OA\Schema]
class HandWashProductInfo
{
    #[OA\Property(description: '상품 번호')]
    public int $no_product;
    #[OA\Property(description: '상세 업종코드')]
    public string $cd_biz_kind_detail;
    #[OA\Property(description: '제휴사번호')]
    public int $no_partner;
    #[OA\Property(description: '매장번호')]
    public int $no_shop;
    #[OA\Property(description: '상품명')]
    public string $nm_product;
    #[OA\Property(description: '세차 최소 금액')]
    public int $min_price;
    #[OA\Property(description: '세차 최대 금액')]
    public int $max_price;
}

#[OA\Schema]
class HandWashCar
{
    #[OA\Property(description: '시퀀스 번호')]
    public int $seq;
    #[OA\Property(description: '차량번호')]
    public string $car_number;
    #[OA\Property(description: '차량번호 4자리')]
    public string $car_search;
    #[OA\Property(description: '국산차량여부')]
    public string $yn_korea;
    #[OA\Property(description: '차량회사명')]
    public string $ds_maker;
    #[OA\Property(description: '차종')]
    public string $ds_kind;
    #[OA\Property(description: '출장세차 코드')]
    public string $cd_wash_carnpeople;
    #[OA\Property(description: '금액')]
    public int $price;
}
<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Subscription;

use OpenApi\Attributes as OA;

#[OA\Schema]
class SubscriptionProduct
{
    #[OA\Property(description: '상품번호')]
    public int $no;
    #[OA\Property(description: '상품코드')]
    public string $product_code;
    #[OA\Property(description: '상품명')]
    public string $title;
    #[OA\Property(description: '상품정보')]
    public string $content;
    #[OA\Property(description: '태그', items: new OA\Items(type: 'string'))]
    public array $tag;
    #[OA\Property(description: '상품이미지')]
    public string $detail_image_url;
    #[OA\Property(description: '구독상품정보', type: 'array', items: new OA\Items('#/components/schemas/BenefitText'))]
    public int $benefit_text;
    #[OA\Property(description: '상품금액')]
    public int $amount;
}

#[OA\Schema]
class BenefitText
{
    #[OA\Property(description: '구독혜택명')]
    public string $title;
    #[OA\Property(description: '구독혜택정보')]
    public string $content;
}

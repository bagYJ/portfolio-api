<?php
declare(strict_types=1);

namespace Tests\app\Http\Response\Retail;

use OpenApi\Attributes as OA;

#[OA\Schema]
class RetailEnvelopeResponse
{
    #[OA\Property(description: '성공 여부')]
    public bool $result;
    #[OA\Property(description: '봉투리스트', type: 'array', items: new OA\Items(ref: '#/components/schemas/EnvelopeList'))]
    public EnvelopeList $envelope;
}

#[OA\Schema]
class EnvelopeList
{
    #[OA\Property(description: '상품번호')]
    public int $no_product;
    #[OA\Property(description: '상품명')]
    public string $nm_product;
    #[OA\Property(description: '상품금액')]
    public int $at_price;
}

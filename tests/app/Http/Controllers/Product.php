<?php

declare(strict_types=1);

namespace Tests\app\Http\Controllers;

use OpenApi\Attributes as OA;

class Product extends Controller
{
    #[OA\Get(
        path: '/product/get_list/{noShop}',
        description: '상품 리스트',
        security: [['bearerAuth' => []]],
        tags: ['product'],
        parameters: [
            new OA\Parameter(
                name: 'noShop', description: '상점번호', in: 'path', required: true, schema: new OA\Schema(
                type: 'integer', example: 10921000
            )
            ),
            new OA\Parameter(
                name: 'noCategory', description: '카테고리번호', in: 'query', schema: new OA\Schema(
                type: 'integer'
            )
            ),
            new OA\Parameter(
                name: 'type',
                description: 'CAR:차량픽업,SHOP:매장픽업',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string',)
            ),

        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'success',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/GetList'
                )
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function getList_test()
    {
    }

    #[OA\Get(
        path: '/product/{noShop}/{noProduct}',
        description: '상품 상세',
        tags: ['product'],
        parameters: [
            new OA\Parameter(
                name: 'noShop', description: '상점번호', in: 'path', required: true, schema: new OA\Schema(
                type: 'integer', example: 10921000
            )
            ),
            new OA\Parameter(
                name: 'noProduct', description: '상품번호', in: 'path', required: true, schema: new OA\Schema(
                type: 'integer', example: 10921001
            )
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'success',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ProductInfo',
                )
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function product_test()
    {
    }

    #[OA\Put(
        path: '/product/cart/{noShop}',
        description: '장바구니 상품 정보',
        requestBody: new OA\RequestBody(
            description: '',
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        required: ['no_products'],
                        properties: [
                            new OA\Property(
                                property: 'no_products',
                                description: '상품번호',
                                type: 'array',
                                items: new OA\Items(type: 'integer')
                            )
                        ]
                    )
                )
            ]
        ),
        tags: ['product'],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(
                ref: '#/components/schemas/ProductCartResponse'
            )
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function cart_test()
    {}

    #[OA\Put(
        path: '/product/cart',
        description: '장바구니 상품 정보 (전체)',
        requestBody: new OA\RequestBody(
            description: '',
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(oneOf: [
                        new OA\Property(schema: 'CartRequest', type: 'array', items: new OA\Items(ref: '#/components/schemas/CartProductRequest'))
                    ])
                )
            ]
        ),
        tags: ['product'],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'result', type: 'boolean'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CartResponse'))
                ]
            )
            ),
            new OA\Response(response: 500, description: 'api failed')
        ],
    )]
    public function getCart_test()
    {}
}

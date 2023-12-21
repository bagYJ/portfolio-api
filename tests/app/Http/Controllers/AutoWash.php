<?php
declare(strict_types=1);

namespace Tests\app\Http\Controllers;

use OpenApi\Attributes as OA;

class AutoWash extends Controller
{
    #[OA\Get(
        path: '/auto_wash/info/{noShop}',
        description: '매장소개정보',
        tags: ['auto_wash'],
        parameters: [
            new OA\Parameter(name: 'noShop', in: 'path', required: true, schema: new OA\Schema(type: 'integer',)),
            new OA\Parameter(name: 'pickup_type', description: 'CAR:차량픽업,SHOP:매장픽업', in: 'query', required: false, schema: new OA\Schema(type: 'string',)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'success',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/Shop'
                )
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function info_test()
    {
    }

    #[OA\Get(
        path: '/auto_wash/intro',
        description: '세차 주문정보 요청',
        security: [['bearerAuth' => []]],
        tags: ['auto_wash'],
        parameters: [
            new OA\Parameter(
                name: 'no_shop',
                in: 'query',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                )
            ),
        ], responses: [
        new OA\Response(
            response: 200,
            description: 'success',
            content: new OA\JsonContent(ref: '#/components/schemas/WashIntroResponse')
        ),
        new OA\Response(response: 500, description: 'api failed')
    ]
    )]
    public function intro_test()
    {
    }

    #[OA\Post(
        path: '/auto_wash/payment',
        description: '주문 결제',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: '',
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(ref: '#/components/schemas/OrderPaymentRequest')
                )
            ]
        ),
        tags: ['auto_wash'],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(
                ref: '#/components/schemas/OrderPaymentResponse'
            )
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function payment_test()
    {
    }

    #[OA\Post(
        path: '/auto_wash/order_complete',
        description: '세차요청처리 - 결과메세지 전달 (세차직원확인->세차요청으로 변경)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: '',
            content: [
                new OA\MediaType(
                    mediaType: 'application/x-www-form-urlencoded',
                    schema: new OA\Schema(
                        required: ['no_order'],
                        properties: [
                            new OA\Property(property: 'no_order', description: '주문번호', type: 'string'),
                        ]
                    )
                )
            ]
        ),
        tags: ['auto_wash'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'success',
                content: new OA\JsonContent(ref: '#/components/schemas/WashOrderCompleteResponse')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function orderComplete_test()
    {
    }
}

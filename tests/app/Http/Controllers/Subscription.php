<?php
declare(strict_types=1);

namespace Tests\app\Http\Controllers;

use OpenApi\Attributes as OA;

class Subscription extends Controller
{
    #[OA\Get(
        path: '/subscription',
        description: '구독상품목록',
        tags: ['subscription'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'success',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SubscriptionProductList'
                )
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function list_test()
    {
    }

    #[OA\Get(
        path: '/subscription/{no}',
        description: '구독상품상세',
        tags: ['subscription'],
        parameters: [
            new OA\Parameter(name: 'no', description: '상품번호', in: 'path', required: true, schema: new OA\Schema(type: 'integer',)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'success',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SubscriptionProductDetail'
                )
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function detail_test()
    {
    }

    #[OA\Post(
        path: '/subscription/payment',
        description: '구독결제',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: '',
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        required: ['no_subscription', 'no_card'],
                        properties: [
                            new OA\Property(property: 'no_subscription', description: '구독상품번호', type: 'integer'),
                            new OA\Property(property: 'no_card', description: '결제카드번호', type: 'integer')
                        ]
                    )
                )
            ]
        ),
        tags: ['subscription'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'success',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SubscriptionPayment'
                )
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function payment_test()
    {
    }

    #[OA\Get(
        path: '/subscription/order/brief',
        description: '내 구독상품리스트',
        security: [['bearerAuth' => []]],
        tags: ['subscription'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'success',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SubscriptionOrderListBrief'
                )
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function orderListBrief_test()
    {
    }

    #[OA\Get(
        path: '/subscription/order/me',
        description: '현재 구독상품정보',
        security: [['bearerAuth' => []]],
        tags: ['subscription'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'success',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SubscriptionOrder'
                )
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function me_test()
    {
    }

    #[OA\Get(
        path: '/subscription/order/{no}',
        description: '구독상품정보',
        security: [['bearerAuth' => []]],
        tags: ['subscription'],
        parameters: [
            new OA\Parameter(name: 'no', description: '구독주문키', in: 'path', required: true, schema: new OA\Schema(type: 'integer',)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'success',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/SubscriptionOrder'
                )
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function orderDetail_test()
    {
    }

    #[OA\Get(
        path: '/subscription/order/refund',
        description: '구독상품해지',
        security: [['bearerAuth' => []]],
        tags: ['subscription'],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool'),
                new OA\Property(property: 'message', description: '해지 메시지', type: 'string')
            ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function refund_test()
    {
    }

    #[OA\Post(
        path: '/subscription/order/change',
        description: '구독상품변경',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: '',
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        required: ['no_subscription'],
                        properties: [
                            new OA\Property(property: 'no_subscription', description: '구독상품번호', type: 'integer')
                        ]
                    )
                )
            ]
        ),
        tags: ['subscription'],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool')
            ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function change_test()
    {
    }

    #[OA\Get(
        path: '/subscription/affiliate',
        description: '제휴사 리스트',
        tags: ['subscription'],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool'),
                new OA\Property(property: 'list', description: '제휴사 리스트', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'code', description: '제휴사코드', type: 'string'),
                    new OA\Property(property: 'name', description: '제휴사이름', type: 'string')
                ]))
            ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function affiliate_test()
    {
    }

    #[OA\Post(
        path: '/subscription/affiliate',
        description: '쿠폰상품등록',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: '',
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        required: ['affiliate_code', 'expression_no'],
                        properties: [
                            new OA\Property(property: 'affiliate_code', description: '제휴사코드 (HYUNDAI: 현대캐피탈)', type: 'string'),
                            new OA\Property(property: 'expression_no', description: '쿠폰번호', type: 'string', maxLength: 12, minLength: 12),
                        ]
                    )
                )
            ]
        ),
        tags: ['subscription'],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool')
            ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function couponRegist_test()
    {
    }

    #[OA\Put(
        path: '/subscription/payment',
        description: '구독상품 결제수단 변경',
        security: [['bearerAuth' => []]],
        tags: ['subscription'],
        parameters: [
            new OA\Parameter(name: 'no_card', description: '카드번호', in: 'query', required: true, schema: new OA\Schema(type: 'integer',)),
            new OA\Property(property: 'agree1', description: '약관동의정보 (0: 구독 멤버십 정기결제 동의, 1: 구독 멤버십 이용약관, 2: 결제 및 멤버십 유의 사항, 3: 개인정보 동의 등', type: 'array', items: new OA\Items()),
            new OA\Property(property: 'agree1', description: 'GS 포인트 멤버십 신규발급 및 적용 동의 (0: 개인정보 제3자 제공, 1: GS&Point 서비스 약관, 2: GS&Point 개인정보 수집 및 활용 동의, 3: GS&Point 서비스 제공을 위한 제3자 제공에 대한 동의', type: 'array', items: new OA\Items())
        ],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool')
            ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function paymentChange_test()
    {
    }

    #[OA\Post(
        path: '/subscription/admin-affiliate',
        description: '이벤트쿠폰상품등록',
        requestBody: new OA\RequestBody(
            description: '',
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        required: ['no_user', 'affiliate_code', 'expression_no'],
                        properties: [
                            new OA\Property(property: 'no_user', description: '회원번호', type: 'integer'),
                            new OA\Property(property: 'affiliate_code', description: '제휴사코드 (HYUNDAI: 현대캐피탈)', type: 'string'),
                            new OA\Property(property: 'expression_no', description: '쿠폰번호', type: 'string', maxLength: 12, minLength: 12),
                        ]
                    )
                )
            ]
        ),
        tags: ['subscription'],
        parameters: [
            new OA\Parameter(name: 'admin-auth-token', description: '관리자 인증키', in: 'header', required: true, schema: new OA\Schema(type: 'string',)),
        ],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool')
            ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function registCouponAdmin_test()
    {
    }
}

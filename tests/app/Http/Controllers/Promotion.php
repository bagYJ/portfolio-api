<?php

declare(strict_types=1);

namespace Tests\app\Http\Controllers;

use OpenApi\Attributes as OA;

class Promotion extends Controller
{
    #[OA\Get(
        path: '/promotion/point_card',
        description: 'gs 포인트카드 리스트',
        security: [['bearerAuth' => []]],
        tags: ['promotion'],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool'),
                new OA\Property(
                    property: 'card_list', description: '포인트카드 리스트', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id_pointcard', description: '포인트카드 번호', type: 'string'),
                    new OA\Property(property: 'cd_point_cp', description: '포인트제휴사 구분 코드', type: 'string'),
                    new OA\Property(property: 'point_cp', description: '포인트제휴사', type: 'string')
                ])
                )
            ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function pointCardList_test()
    {
    }

    #[OA\Post(
        path: '/promotion/point_card',
        description: 'gs 포인트카드 등록',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: '',
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        required: ['agree_result'],
                        properties: [
                            new OA\Property(property: 'agree_result', description: '약관동의정보 (01: [필수] GS&POINT 서비스 약관, 02, 09: [필수] GS&POINT 개인정보 수집 및 활용 동의, 71, 81: [필수] GS&POINT 서비스 제공을 위한 제3자 제공에 대한 동의(GS리테일), 94: [선택] 마케팅 목적 개인정보 수집 및 활용동의, 72, 73, 82, 83: [선택] GS&POINT 참여사의 상품/서비스 마케팅, 고객응대를 위한 제3자 제공 및 광고성 정보수신 동의(GS리테일), 61: [선택] GS&POINT 상품/서비스 마케팅을 위한 제3자 제공 및 광고성 정보수신 동의 (GS엠비즈), 67: [선택] GS&POINT 상품/서비스 마케팅을 위한 제3자 제공 및 광고성 정보수신 동의(한국신용데이터)', type: 'array', items: new OA\Items())
                        ]
                    )
                )
            ]
        ),
        tags: ['promotion'],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool')
            ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function pointCardRegist()
    {
    }

    #[OA\Get(
        path: '/promotion/point_card/point/{idPointcard}',
        description: '포인트 조회',
        security: [['bearerAuth' => []]],
        tags: ['promotion'],
        parameters: [
            new OA\Parameter(name: 'idPointcard', description: '포인트카드번호', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool'),
                new OA\Property(property: 'point', description: '포인트', type: 'integer')
            ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function cardPoint_test()
    {
    }

    #[OA\Delete(
        path: '/promotion/point_card/{idPointcard}',
        description: '포인트카드 삭제',
        security: [['bearerAuth' => []]],
        tags: ['promotion'],
        parameters: [
            new OA\Parameter(name: 'idPointcard', description: '포인트카드번호', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
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
    public function removePointCard_test()
    {
    }

    #[OA\Post(
        path: '/promotion/coupon',
        description: 'owin 쿠폰 등록',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: '',
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        required: ['no_event'],
                        properties: [
                            new OA\Property(property: 'no_event', description: '쿠폰번호', type: 'string')
                        ]
                    )
                )
            ]
        ),
        tags: ['promotion'],
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

    #[OA\Post(
        path: '/promotion/coupon/gs',
        description: 'GS 쿠폰 등록',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: '',
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        required: ['no_event'],
                        properties: [
                            new OA\Property(property: 'no_event', description: '쿠폰번호', type: 'string')
                        ]
                    )
                )
            ]
        ),
        tags: ['promotion'],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool')
            ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function gsCouponRegist_test()
    {
    }

    #[OA\Get(
        path: '/promotion/coupon/gs/{noEvent}',
        description: 'gs 쿠폰 등록 후 쿠폰 등록 체크 (정상쿠폰 확인 후 사용불가 쿠폰일 경우 삭제)',
        security: [['bearerAuth' => []]],
        tags: ['promotion'],
        parameters: [
            new OA\Parameter(name: 'noEvent', description: 'gs 쿠폰 번호', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
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
    public function gsCouponDetail_test()
    {
    }

    #[OA\Delete(
        path: '/promotion/coupon/gs/{noEvent}',
        description: 'gs 쿠폰 삭제',
        security: [['bearerAuth' => []]],
        tags: ['promotion'],
        parameters: [
            new OA\Parameter(name: 'noEvent', description: 'gs 쿠폰 번호', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
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
    public function gsCouponRemove_test()
    {
    }

    #[OA\Get(
        path: '/promotion/coupon/gs/search/{no}',
        description: 'gs 쿠폰 사용여부 조회',
        security: [['bearerAuth' => []]],
        tags: ['promotion'],
        parameters: [
            new OA\Parameter(name: 'no', description: 'gs 쿠폰 번호', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool'),
                new OA\Property(property: 'is_used', description: '쿠폰 사용 여부', type: 'bool')
            ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function search_test()
    {
    }
}

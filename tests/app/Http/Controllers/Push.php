<?php
declare(strict_types=1);

namespace Tests\app\Http\Controllers;

use OpenApi\Attributes as OA;

class Push extends Controller
{
    #[OA\Post(
        path: '/push',
        description: '푸시 발송',
        requestBody: new OA\RequestBody(
            description: '',
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        required: ['no_users', 'title', 'body', 'biz_kind'],
                        properties: [
                            new OA\Property(property: 'no_users', description: '회원번호', type: 'array', items: new OA\Items(type: 'integer')),
                            new OA\Property(property: 'title', description: '발송제목', type: 'string'),
                            new OA\Property(property: 'body', description: '발송내용', type: 'string'),
                            new OA\Property(property: 'biz_kind', description: '발송타입 (FNB: 식사/음료, WASH: 세차, PARKING: 주차, RETAIL: 리테일, PERSONAL: 개인공지, NOTICE: 공지, EVENT: 이벤트)', type: 'string', enum: ['FNB', 'WASH', 'PARKING', 'RETAIL', 'PERSONAL', 'NOTICE', 'EVENT']),
                            new OA\Property(property: 'biz_kind_detail', description: '상세업종타입', type: 'string'),
                            new OA\Property(property: 'no_shop', description: '상점번호', type: 'integer'),
                            new OA\Property(property: 'no_order', description: '주문번호', type: 'string'),
                            new OA\Property(property: 'status', description: '주문상태', type: 'string'),
                            new OA\Property(property: 'is_ordering', description: '주문진행여부', type: 'string', enum: ['Y', 'N'])
                        ]
                    )
                )
            ]
        ),
        tags: ['push'],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool')
            ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function send_test()
    {
    }
}

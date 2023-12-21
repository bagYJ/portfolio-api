<?php

declare(strict_types=1);

namespace Tests\app\Http\Controllers;

use OpenApi\Attributes as OA;

class Event extends Controller
{

    #[OA\Post(
        path: '/event/issue_fnb_member_coupon',
        description: '이벤트 쿠폰 발급',
        requestBody: new OA\RequestBody(
            description: '',
            content: [
                new OA\MediaType(
                    mediaType: 'application/x-www-form-urlencoded',
                    schema: new OA\Schema(
                        required: ['no_user', 'no_bbs_event'],
                        properties: [
                            new OA\Property(property: 'no_user', description: 'member 의 no_user', type: 'string'),
                            new OA\Property(property: 'no_bbs_event', description: 'bbs_event 의 no', type: 'string'),
                        ]
                    )
                )
            ]
        ),
        tags: ['event'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'success',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'result', description: '성공 여부', type: 'bool'),
                    new OA\Property(property: 'message', description: '성공 메세지', type: 'string')
                ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function coupon_test()
    {
    }
}

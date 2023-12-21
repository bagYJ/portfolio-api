<?php

namespace Tests\app\Http\Controllers;

use OpenApi\Attributes as OA;

class Action extends Controller
{
    #[OA\Get(
        path: '/action/uptime_check/{article}',
        description: '주유주문관련 시간체크',
        security: [['bearerAuth' => []]],
        tags: ['action'],
        parameters: [
            new OA\Parameter(
                name: 'article', in: 'path', required: true, schema: new OA\Schema(
                type: 'string',
                enum: ['S', 'O']
            )
            ),
        ], responses: [
        new OA\Response(
            response: 200,
            description: 'success',
            content: new OA\JsonContent(ref: '#/components/schemas/UptimeCheck')
        ),
        new OA\Response(response: 500, description: 'api failed')
    ]
    )]
    public function uptimeCheck_test()
    {
    }

    #[OA\Post(
        path: '/action/location_save',
        description: '주유주문관련 현재위치 체크',
        security: [['bearerAuth' => []]],
        tags: ['action'],
        parameters: [
            new OA\Parameter(name: 'no_order', description: '주문번호', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'user_lat', description: '위도', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'user_lng', description: '경도', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'yn_inside', description: '150m 이내 여부', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['Y', 'N'])),
            new OA\Parameter(name: 'ds_addr', description: '페이지 경로', in: 'query', schema: new OA\Schema(type: 'string'))
        ], responses: [
        new OA\Response(
            response: 200,
            description: 'success',
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool')
            ], type: 'object')
        ),
        new OA\Response(response: 500, description: 'api failed')
    ]
    )]
    public function locationSave_test()
    {
    }

    #[OA\Delete(
        path: '/action/cache_clear/{key}',
        description: '캐시 파일 삭제',
        tags: ['action'],
        parameters: [
            new OA\Parameter(
                name: 'key', description: '캐시 key 값', in: 'path', required: true, schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'success',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'result', description: '캐시 삭제 여부', type: 'bool')], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ],
    )]
    public function cacheClear_test()
    {
    }
}

<?php

declare(strict_types=1);

namespace Tests\app\Http\Controllers;

use OpenApi\Attributes as OA;

class Partner extends Controller
{
    #[OA\Get(
        path: '/partner/filter/{bizKind}',
        description: '브랜드필터',
        tags: ['partner'],
        parameters: [
            new OA\Parameter(name: 'bizKind', in: 'path', required: true, schema: new OA\Schema(type: 'string',)),
            new OA\Parameter(name: 'biz_kind_detail', in: 'query', required: true, description: 'CAFE, SPC, RESTAURANT, OIL, RETAIL', schema: new OA\Schema(type: 'string',)),
        ],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool'),
                new OA\Property(property: 'count', description: '브랜드 필터 수', type: 'integer'),
                new OA\Property(
                    property: 'rows', description: '브랜드 필터 리스트', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'no_partner', description: '브랜드 번호', type: 'integer'),
                    new OA\Property(property: 'nm_partner', description: '브랜드명', type: 'string'),
                    new OA\Property(property: 'biz_kind', description: '업종', type: 'string'),
                    new OA\Property(property: 'ds_bi', description: '브랜드BI경로', type: 'string'),
                    new OA\Property(property: 'ds_pin', description: '브랜드PIN경로', type: 'string'),
                ])
                )
            ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function get_filters_test()
    {
    }

    #[OA\Get(
        path: '/partner/group_filter/{bizKind}',
        description: '브랜드필터 grouping',
        tags: ['partner'],
        parameters: [
            new OA\Parameter(name: 'bizKind', description: 'FNB, FNBNR, CAFE, FOOD', in: 'path', required: true, schema: new OA\Schema(type: 'string',)),
        ],
        responses: [
            new OA\Response(
                response: 200, description: 'success', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'result', description: '성공 여부', type: 'bool'),
                new OA\Property(property: 'count', description: '브랜드 필터 수', type: 'integer'),
                new OA\Property(
                    property: 'rows', description: '브랜드 필터 리스트', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'detail_biz_kind', description: '업종 (FOOD, CAFE, RETAIL)', type: 'string'),
                    new OA\Property(property: 'biz_kind', description: '검색 업종', type: 'string'),
                    new OA\Property(property: 'no_partner', description: '브랜드 번호', type: 'integer'),
                    new OA\Property(property: 'nm_partner', description: '브랜드명', type: 'string'),
                    new OA\Property(property: 'ds_bi', description: '브랜드BI경로', type: 'string'),
                    new OA\Property(property: 'ds_pin', description: '브랜드PIN경로', type: 'string'),
                ])
                )
            ], type: 'object')
            ),
            new OA\Response(response: 500, description: 'api failed')
        ]
    )]
    public function get_group_filters_test()
    {
    }
}
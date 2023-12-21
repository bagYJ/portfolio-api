<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MainTitleService;
use App\Services\NoticeService;
use App\Services\SearchService;
use App\Utils\Common;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Main extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     *
     * 메인
     */
    public function main(Request $request): JsonResponse
    {
        $body = $request->toArray();
        Validator::make([
            'radius' => $body['radius'],
            'position' => $body['position'],
        ], [
            'radius' => 'required',
            'position' => 'required|array'
        ])->validate();

        $lists = (new SearchService())->homeShopList(
            $body['radius'],
            $body['position'],
        );

        return response()->json([
            'result' => true,
            'image_path' => env('IMAGE_PATH'),
            'recommend_shop' => $lists->map(function ($list) {
                return [
                    'no_shop' => $list->no_shop,
                    'nm_shop' => $list->nm_shop,
                    'distance' => $list->distance,
                    'is_car_pickup' => $list->yn_car_pickup == 'Y',
                    'is_shop_pickup' => $list->yn_shop_pickup == 'Y',
                    'at_send_price' => $list->at_order_send_price,
                    'at_send_disct' => min($list->at_order_send_price, $list->at_send_disct),
                    'product' => $list->productIgnoreExcept->forPage(0, 5)->map(function ($product) {
                        return [
                            'no_product' => $product->no_product,
                            'nm_product' => $product->nm_product,
                            'at_price_before' => $product->at_price_before,
                            'at_price' => $product->at_price,
                            'ds_image_path' => $product->ds_image_path,
                            'at_ratio' => Common::getSaleRatio($product->at_price_before, $product->at_price),
                            'is_car_pickup' => $product->yn_car_pickup == 'Y',
                            'is_shop_pickup' => $product->yn_shop_pickup == 'Y',
                        ];
                    })
                ];
            })->forPage(0, 3),
        ]);
    }

    /**
     * @return JsonResponse
     *
     * 메인 공지사항 리스트
     */
    public function notice(): JsonResponse
    {
        return response()->json([
            'result' => true,
            'notice_list' => NoticeService::getMainNotice()
        ]);
    }

    /**
     * @return JsonResponse
     *
     * 메인 상단 텍스트
     */
    public function header(): JsonResponse
    {
        return response()->json([
            'result' => true,
            'header' => MainTitleService::getRandomTitle()
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Pickup;
use App\Exceptions\OwinException;
use App\Exceptions\TMapException;
use App\Services\ReviewService;
use App\Services\ShopService;
use App\Services\WashService;
use App\Utils\Code;
use App\Utils\Spc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class Shop extends Controller
{
    /**
     * @param int $noShop
     * @return JsonResponse
     * @throws OwinException
     * @throws TMapException
     *
     * 매장소개정보
     */
    public function info(Request $request, int $noShop): JsonResponse
    {
        $request->validate([
            'pickup_type' => ['nullable', Rule::in(Pickup::keys())],
        ]);

        //shop 조회수 증가
        ShopService::updateCtView($noShop);

        $shop = ShopService::shop($noShop)->first();
        if (!empty($shop->cd_spc_store)) {
            Spc::productsStock($shop->partner->cd_spc_brand, $shop->cd_spc_store, $shop->partnerCategory->pluck('no_partner_category'));
        }

        //shop 조회
        $result = ShopService::getShopInfo($noShop, $request->pickup_type);

        return response()->json([
            'result' => true,
            ...$result->toArray()
        ]);
    }

    /**
     * @param int $noShop
     * @return JsonResponse
     * @throws OwinException
     * @throws TMapException
     *
     * 매장주문가능여부
     */
    public function isOrderAvailable(int $noShop): JsonResponse
    {
        $shop = ShopService::getShop($noShop);

        return response()->json([
            'result' => !($shop->shopOptTime?->first()?->is_order !== true || $shop->shopOptTime?->first()?->is_order_available !== true || empty($shop->shopHolidayExists) !== true),
            'message' => match (empty($shop->shopHolidayExists) === false) {
                true => Code::message('P2055'),
                default => match ($shop->shopOptTime?->first()?->is_order !== true) {
                    true => Code::message('M1306'),
                    default => match ($shop->shopOptTime?->first()?->is_order_available !== true) {
                        true => Code::message('M1307'),
                        default => null
                    }
                }
            }
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 매장리뷰
     */
    public function review(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'required|integer',
            'size' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:0',
        ]);
        $noShop = intval($request->get('no_shop'));
        $size = (int)$request->get('size') ?: Code::conf('default_size');
        $offset = (int)$request->get('offset') ?: 0;

        $reviewTotal = ReviewService::getReviewTotal($noShop);
        $reviews = ReviewService::getReviews($noShop, $offset, $size);

        $response = [
            'result' => true,
        ];

        if ($reviewTotal && count($reviews)) {
            $response['at_grade'] = $reviewTotal['at_grade'];
            $response['ct_review'] = $reviewTotal['ct_review'];
            $response['per_page'] = $reviews->perPage();
            $response['current_page'] = $reviews->currentPage();
            $response['last_page'] = $reviews->lastPage();
            return response()->json([
                ...$response,
                'list_review' => $reviews->items()
            ]);
        }
        return response()->json(null, 404);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * 리뷰 작성
     */
    public function reviewRegist(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'required|integer',
            'no_order' => 'required',
            'ds_content' => 'required|string',
            'at_grade' => 'required|numeric|min:0|max:5',
        ]);

        Auth::user()->orderList->where('no_shop', $request->no_shop)->where('no_order', $request->no_order)->whenEmpty(function () {
            throw new OwinException(Code::message('P2120'));
        }, function ($orderList) {
            ReviewService::getReview([
                'no_shop' => $orderList->first()->no_shop,
                'no_order' => $orderList->first()->no_order,
                'no_user' => Auth::id()
            ])->whenNotEmpty(function () {
                throw new OwinException(Code::message('PI004'));
            });
        });

        ReviewService::create([
            'no_user' => Auth::id(),
            'no_shop' => $request->get('no_shop'),
            'no_order' => $request->no_order,
            'nm_nick' => Auth::user()->nm_nick,
            'ds_content' => $request->get('ds_content'),
            'at_grade' => $request->get('at_grade'),
            'ds_userip' => $request->server('REMOTE_ADDR'),
            'yn_status' => 'Y'
        ]);

        return response()->json([
            'result' => true,
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     * @throws TMapException
     *
     * 메인 매장소개정보 - 업데이트
     */
    public function mainInfo(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'required|integer',
            'size' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:0',
        ]);
        $noShop = intval($request->get('no_shop'));
        $size = $request->get('size') ?: Code::conf('default_size');
        $offset = $request->get('offset') ?: 0;

        //매장정보 반환
        $response['shop_info'] = ShopService::getShop($noShop);
        if (!$response['shop_info']) {
            throw new OwinException(Code::message('M1303'));
        }

        //조회수 증가
        ShopService::updateCtView($noShop);

        //매장 영업시간
        $response['shop_opt_time'] = ShopService::getInfoOptTimeAll($noShop);

        //매장 휴무
        $response['shop_holiday'] = ShopService::getShopHoliday($noShop);

        if ($response['shop_info']['partner']['cd_biz_kind'] === Code::conf('biz_kind.oil')) {
            $response['oil_info'] = ShopService::getShopOilPrices($noShop);
        }

        if ($response['shop_info']['partner']['cd_biz_kind'] === Code::conf('biz_kind.wash')) {
            $response['wash_products'] = WashService::getWashProductList($noShop);
        }

        // 리뷰 총건수,평점
        $response['review_total'] = ReviewService::getReviewTotal($noShop);

        // 주문주문예약방식 - 매장에 주문방식설정된 내역 반환 [QR 개발]
        $response['list_cd_booking_type'] = isset($response['shop_info']['list_cd_booking_type']) ? explode(
            ',',
            $response['shop_info']['list_cd_booking_type']
        ) : null;

        $response['yn_open'] = ShopService::getYnShopOpen($response['shop_info']);
        $dsBtnNotice = match ($response['yn_open']) {
            "Y" => "주유하기",
            "N" => "운영종료",
            "T" => "임시휴일",
            default => "점검중",
        };
        $response['ds_btn_notice'] = $response['shop_info']['ds_btn_notice'] ?: $dsBtnNotice;

        $response['yn_wash_oilshop'] = WashService::getWashShopInShopInfo($noShop);

        return response()->json([
            'result' => true,
            ...$response
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * 매장 수수료정보(장바구니)
     */
    public function commissionInfo(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'required|integer',
        ]);
        $commissionInfo = ShopService::getInfoCommission(intval($request->get('no_shop')));
        if (!$commissionInfo) {
            throw new OwinException(Code::message('404'));
        }
        return response()->json([
            'result' => true,
            'commission_info' => $commissionInfo
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 매장리뷰평점조회
     */
    public function getReviewGrade(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'required|array',
            'no_shop.*' => 'required|integer'
        ]);

        return response()->json([
            'result' => true,
            'review' => ReviewService::getShopReview($request->no_shop)->map(function ($review) {
                return [
                    'no_shop' => $review->no_shop,
                    'ct_review' => $review->ct_review,
                    'at_grade' => round($review->at_grade, 1)
                ];
            })
        ]);
    }

    /**
     * @param int $noReview
     * @return JsonResponse
     *
     * 매장리뷰 삭제
     */
    public function reviewRemove(int $noReview): JsonResponse
    {
        return response()->json([
            'result' => ReviewService::remove(Auth::id(), $noReview)
        ]);
    }

    /**
     * @param int $noReview
     * @return JsonResponse
     *
     * 매장리뷰 신고
     */
    public function reviewSiren(int $noReview): JsonResponse
    {
        $noShop = ReviewService::getReview(['no' => $noReview])->whenEmpty(function () {
            throw new OwinException(Code::message('9990'));
        })->map(function ($collect) {
            return (int)$collect->no_shop;
        })->first();

        ReviewService::getReviewSiren([
            'no_user' => Auth::id(),
            'no_review' => $noReview,
            'no_shop' => $noShop,
        ])->whenNotEmpty(function () {
            throw new OwinException(Code::message('PI001'));
        });

        ReviewService::siren([
            'no_user' => Auth::id(),
            'no_shop' => $noShop,
            'no_review' => $noReview,
            'ds_userip' => env('REMOTE_ADDR')
        ]);

        return response()->json([
            'result' => true
        ]);
    }
}

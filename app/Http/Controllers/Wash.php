<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BenefitDetailType;
use App\Enums\BenefitType;
use App\Enums\SearchBizKindDetail;
use App\Exceptions\OwinException;
use App\Services\CouponService;
use App\Services\HandWashService;
use App\Services\OrderService;
use App\Services\ShopService;
use App\Services\WashService;
use App\Utils\Ark;
use App\Utils\Code;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Wash extends Controller
{
    /**
     * 출장세차 상품정보
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function products(): JsonResponse
    {
        $carInfo = Auth::user()?->memberCarInfo;

        $products = HandWashService::getProducts([
            'yn_status' => 'Y'
        ])->map(function ($collect) use ($carInfo) {
            return [
                'no_product' => $collect->no_product,
                'ds_bi' => $collect->partner->ds_bi,
                'ds_pin' => $collect->partner->ds_pin,
                'cd_biz_kind_detail' => $collect->cd_biz_kind_detail,
                'no_partner' => $collect->no_partner,
                'no_shop' => $collect->no_shop,
                'nm_product' => $collect->nm_product,
                'min_price' => $collect->prices->min('at_price'),
                'max_price' => $collect->prices->max('at_price'),
                'price' => $collect->prices->where('cd_wash_carnpeople', $carInfo?->carList?->cd_wash_carnpeople)->first()?->at_price ?? null
            ];
        });

        return response()->json([
            'result' => true,
            'carInfo' => $carInfo,
            'products' => $products,
        ]);
    }


    public function prices(int $noShop, int $noProduct)
    {
        $product = HandWashService::getProducts([
            'no_shop' => $noShop,
            'no_product' => $noProduct,
            'yn_status' => 'Y'
        ])->map(function ($collect){
            return [
                'no_product' => $collect->no_product,
                'cd_biz_kind_detail' => $collect->cd_biz_kind_detail,
                'no_partner' => $collect->no_partner,
                'no_shop' => $collect->no_shop,
                'nm_product' => $collect->nm_product,
                'min_price' => $collect->prices->min('at_price'),
                'max_price' => $collect->prices->max('at_price'),
                'prices' => $collect->prices,
            ];
        })->first();

        $cars = Auth::user()?->memberCarInfoAll->map(function ($collect) use ($product) {
            return [
                'seq' => $collect->seq,
                'yn_main_car' => $collect->yn_main_car,
                'car_number'=> $collect->ds_car_number,
                'car_search' => $collect->ds_car_search,
                'yn_korea' => $collect->carList->yn_korea,
                'ds_maker' => $collect->carList->ds_maker,
                'ds_kind' => $collect->carList->ds_kind,
                'cd_wash_carnpeople' => $collect->carList->cd_wash_carnpeople,
                'price' => $product['prices']->where('cd_wash_carnpeople', $collect->carList?->cd_wash_carnpeople)->first()?->at_price,
            ];
        })->sortByDesc('yn_main_car');

        unset($product['prices']);

        return response()->json([
            'result' => true,
            'product' => $product,
            'cars' => $cars,
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 세차주문정보 요청
     */
    public function intro(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'required|integer',
            'at_price_total' => 'nullable|numeric',
            'car_number' => 'nullable|string',
            'list_product' => 'nullable|array',
        ]);

        $member = Auth::user();
        $noShop = intval($request->get('no_shop'));
        $shop = ShopService::getShop($noShop);

        $couponService = new CouponService();

        $cdBizKind = Code::conf('biz_kind.wash');
        //이전주문내역
        $orderInfo = OrderService::getUserOrderInfo([
            ['a.no_user', '=', $member['no_user']],
            ['a.no_shop', '=', $noShop],
            ['b.cd_biz_kind', '=', $cdBizKind],
            ['a.cd_pickup_status', '<', 602400],
            ['a.cd_order_status', '=', '601200'],
            ['a.cd_payment_status', '=', '603300'],
        ]);

        $unUseCards = array_unique(
            ShopService::getShopUnUseCards($noShop)->pluck('cd_card_corp')->all()
        );
        $response = [
            'no_order' => $orderInfo?->no_order,
            'cars' => match (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail)) {
                SearchBizKindDetail::HANDWASH => $member->memberCarInfoAll->where('ds_car_number', $request->car_number),
                default => $member->memberCarInfoAll->sortByDesc('yn_main_car'),
            },
            'cards' => $member->memberCard->filter(
                function ($query) use ($unUseCards) {
                    return !in_array($query['cd_card_corp'], $unUseCards)
                        && $query['cd_pg'] === '500100';
                }
            )->map(function ($collect) {
                return [
                    'no_seq' => $collect->no_seq,
                    'cd_card_corp' => $collect->cd_card_corp, //const 로 변경 필요
                    'no_card' => $collect->no_card,
                    'no_card_user' => $collect->no_card_user,
                    'nm_card' => $collect->nm_card,
                    'yn_main_card' => $collect->yn_main_card,
                    'yn_credit' => $collect->yn_credit,
                ];
            })->sortByDesc('yn_main_card')->values(),
            'coupons' => match (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail)) {
                SearchBizKindDetail::HANDWASH => $couponService->getHandWashUsableCoupon(Auth::id(), $noShop, $request->at_price_total, collect($request->list_product)),
                default => $couponService->getMyWashCoupon(Auth::id(), 'Y')
            },
            'products' =>  match (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail)) {
                SearchBizKindDetail::HANDWASH => HandWashService::getProducts([
                    'yn_status' => 'Y'
                ], [
                    'no_product' => collect($request->list_product)->pluck('no_product')->all(),
                ]),
                default => WashService::getWashProductList($noShop)
            },
            'benefit' => match (BenefitDetailType::saleUse(Auth::user()->useSubscription?->benefit->{BenefitType::WASH->name}->type)) {
                true => Auth::user()->useSubscription?->benefit->{BenefitType::WASH->name}->{BenefitDetailType::SALE->name},
                default => null
            }
        ];

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * [처리] 세차요청처리 - 결과메세지 전달 (세차직원확인->세차요청으로 변경)
     */
    public function orderComplete(Request $request): JsonResponse
    {
        $request->validate([
            'no_order' => 'required|string'
        ]);

        $orderInfo = OrderService::getOrder($request->get('no_order'));
        if (!$orderInfo) {
            throw new OwinException(Code::message('P2120'));
        }

        if ($orderInfo['cd_order_status'] > 601200) {
            // 취소된 주문인경우
            throw new OwinException(Code::message('P2401'));
        } elseif ($orderInfo['cd_pickup_status'] != '602100') {
            // 대기상태주문이 아닌경우
            throw new OwinException(Code::message('P2407'));
        }

        WashService::washComplete(Auth::user(), $orderInfo);

        Ark::client(env('ARK_API_PATH_WASH'), [
            'body' => sprintf('WK%s%s', $orderInfo->shop->oilInShop->no_shop_in, $orderInfo->no_order)
        ]);

        return response()->json([
            'result' => true
        ]);
    }
}

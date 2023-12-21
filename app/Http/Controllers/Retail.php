<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MemberLevel;
use App\Enums\OptionType;
use App\Enums\Pg;
use App\Exceptions\OwinException;
use App\Exceptions\TMapException;
use App\Queues\Fcm\Fcm;
use App\Queues\Rkm\Rkm;
use App\Response\Retail\ProductInfo;
use App\Services\CodeService;
use App\Services\MemberService;
use App\Services\OrderRetailService;
use App\Services\OrderService;
use App\Services\PartnerService;
use App\Services\ProductService;
use App\Services\RetailProductService;
use App\Services\RetailService;
use App\Services\ReviewService;
use App\Services\SearchService;
use App\Services\ShopService;
use App\Utils\Code;
use App\Utils\Common;
use App\Utils\Cu;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Owin\OwinCommonUtil\CodeUtil;
use Throwable;

class Retail extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     *
     * [5.주문 자동취소 전 알림]  ( Owin (batch) -> retail(CU) )
     */
    public function cancelCheck(Request $request): JsonResponse
    {
        $request->validate([
            'no_partner' => 'required|integer',
            'no_shop' => 'required|integer',
            'no_order' => 'required|string'
        ]);

        $serviceSchemaEnum = CodeUtil::getServiceSchemaEnumFromOrderCode($request->no_order);
        DB::statement('use ' . $serviceSchemaEnum->value);

        $storeCd = RetailService::getRetailStoreCd($request->no_partner, $request->no_shop, $request->all());
        $orderInfo = RetailService::getOrderInfo($request->no_order, $request->all());

        $apiResponse = Cu::client(env('CU_API_PATH_CANCEL_CHECK'), [
            'shop_code' => $storeCd,
            'no_order' => $orderInfo->no_order,
            'nm_order' => $orderInfo->nm_order,
            'nm_nick' => $orderInfo->member->nm_nick,
            'dt_order' => $orderInfo->dt_reg->format('YmdHis'),
            'dt_pickup' => $orderInfo->dt_pickup->format('YmdHis'),
            'dt_pickup_type' => $orderInfo['ds_pickup_type']
        ]);
        OrderRetailService::registMemberShopRetailLog([
            'no_user' => $orderInfo->no_user,
            'no_shop' => $orderInfo->no_shop,
            'no_order' => $orderInfo->no_order,
            'cd_alarm_event_type' => '607910',
        ]);

        return response()->json([
            'result' => $apiResponse['result_code'] == '0000'
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * [6.주문취소]  ( Owin -> retail(CU) )
     * todo 프록시 변경 후 삭제 예정
     */
    public function orderCancel(Request $request): JsonResponse
    {
        $request->validate([
            'no_partner' => 'required|integer',
            'no_shop' => 'required|integer',
            'no_order' => 'required|string',
            'cd_reject_reason' => 'required|string',
        ]);

        $serviceSchemaEnum = CodeUtil::getServiceSchemaEnumFromOrderCode($request->no_order);
        DB::statement('use ' . $serviceSchemaEnum->value);

        $shop = ShopService::shop((int)$request->no_shop)->first();
        if (!$shop?->store_cd) {
            RetailService::insertRetailExternalResultLog($request->all(), [
                'result' => false,
                'result_code' => 'M1303',
            ]);
            throw new OwinException(Code::message('M1303'));
        }

        $orderInfo = RetailService::getOrderInfo($request->no_order, $request->all());
        $user = MemberService::getMember([
            'no_user' => $orderInfo->no_user
        ])->first();

        $cdPg = match ($orderInfo->cd_pg) {
            500600 => Pg::incarpayment_kcp,
            default => Pg::from($orderInfo->cd_pg)
        };
        $response = (new OrderService())->refund(
            $user,
            $shop,
            $orderInfo->no_order,
            '601950',
            $cdPg->name,
            CodeService::getCode($request->cd_reject_reason)?->nm_code
        );

        try {
            $nmShop = sprintf('%s %s', $shop->partner->nm_partner, $shop->nm_shop);
            $fcmData = array("ordering" => 'N', "nm_shop" => $nmShop);
            (new Fcm("RETAIL", $orderInfo->no_shop, $orderInfo->no_order, $fcmData, true, 'user', $orderInfo->no_user, "cancel_etc"))->init();

            if ($orderInfo->member->cd_mem_level == MemberLevel::AVN->value && empty($orderInfo->member->memberDetail->ds_access_vin_rsm) === false) {
                (new Rkm(
                    vin: $orderInfo->member->memberDetail->ds_access_vin_rsm,
                    title: sprintf(Code::fcm('user.RETAIL.cancel_etc.title'), ''),
                    body: $nmShop . ' ' . Code::fcm('user.RETAIL.cancel_etc.body')
                ))->init();
            }
        } catch (Throwable $t) {
            Log::channel('slack')->critical('FCM: ', [$t->getMessage()]);
        }

        return response()->json([
            'result' => $response['res_cd'] == '0000',
            'message' => $response['res_msg'],
            'partner_code' => Code::conf('cu.partner_code'),
            'shop_code' => $shop->store_cd,
            'result_code' => $response['res_cd'],
            'result_msg' => $response['res_msg']
        ]);
    }

    /**
     * @param string $noOrder
     * @return JsonResponse
     * @throws GuzzleException
     *
     * [9.매장도착알림]    ( Owin -> retail(CU) )
     */
    public function arrivalAlarm(string $noOrder): JsonResponse
    {
        $parameter = [
            'no_order' => $noOrder,
        ];

        $orderInfo = RetailService::getOrderInfo($noOrder, $parameter);
        $storeCd = RetailService::getRetailStoreCd($orderInfo->no_partner, $orderInfo->no_shop, $parameter);
        $apiResponse = Cu::client(env('CU_API_PATH_ARRIVAL_ALARM'), [
            'shop_code' => $storeCd,
            'no_order' => $noOrder,
            'nm_order' => $orderInfo->nm_order,
            'yn_complete' => 'Y',
            'at_distance' => 0,
            'dt_order' => $orderInfo->dt_reg->format('YmdHis'),
            'dt_pickup' => $orderInfo->dt_pickup->format('YmdHis'),
            'ds_pickup_type' => $orderInfo->ds_pickup_type,
            'nm_nick' => $orderInfo->member->nm_nick,
            'ds_phone' => $orderInfo->ds_safe_number,
            'ds_car_number' => $orderInfo->ds_car_number
        ]);

        if ($apiResponse['result_code'] == '0000') {
            RetailService::updateOrderInfo([
                'no_user' => $orderInfo->no_user,
                'no_shop' => $orderInfo->no_shop,
                'no_order' => $orderInfo->no_order,
                'cd_alarm_event_type' => '607350',
            ]);
        }

        return response()->json([
            'result' => $apiResponse['result_code'] == '0000'
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     *
     * [11.전달완료 요청알림]   ( Owin -> retail(CU) )
     */
    public function deliveryAlarm(Request $request): JsonResponse
    {
        $request->validate([
            'no_partner' => 'required|integer',
            'no_shop' => 'required|integer',
            'no_order' => 'required|string'
        ]);

        $storeCd = RetailService::getRetailStoreCd($request->no_partner, $request->no_shop, $request->all());
        $orderInfo = RetailService::getOrderInfo($request->no_order, $request->all());

        $apiResponse = Cu::client(env('CU_API_PATH_DELIVERY_ALARM'), [
            'shop_code' => $storeCd,
            'no_order' => $orderInfo->no_order,
            'dt_pickup' => $orderInfo->dt_pickup->format('YmdHis')
        ]);

        if ($apiResponse['result_code'] == '0000') {
            RetailService::updateOrderInfo([
                'no_user' => $orderInfo->no_user,
                'no_shop' => $orderInfo->no_shop,
                'no_order' => $orderInfo->no_order,
                'cd_alarm_event_type' => '607420',
            ]);
        }

        return response()->json([
            'result' => $apiResponse['result_code'] == '0000'
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException|TMapException
     *
     * 리테일 매장 기본정보
     */
    public function info(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'required|integer',
        ]);
        $noUser = Auth::id(); // 테스트 계정 처리는 어떻게 해야할까용?
        $noShop = $request->get('no_shop');

        //shop 조회
        $response['shop_info'] = ShopService::getShop($noShop);

        //shop 조회수 증가
        ShopService::updateCtView($noShop);

        $response['shop_holiday'] = ShopService::getShopHoliday($noShop);
        $response['yn_open'] = $response['shop_holiday']['yn_open'];

        $posError = SearchService::getPosError($noShop);
        if ($posError) {
            $response['yn_open'] = 'N';
        }

        if ($response['shop_info']['ds_status'] === 'N') {
            $response['yn_open'] = 'N';
        }

        if ($response['shop_info']['cd_pause_type']) {
            $response['yn_open'] = 'E';
        }

        $response['ds_btn_notice'] = match ($response['yn_open']) {
            "Y" => "주유하기",
            "N" => "운영종료",
            "T" => "임시휴일",
            default => "점검중",
        };

        return response()->json($response);
    }


    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     * @throws TMapException
     *
     * 오윈주문 - 브랜드 카테고리 리스트
     * 브랜드에 등록된 메인카테고리 정보만 전달 - 서브카테고리 제외
     */
    public function category(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'nullable|string',
            'no_partner' => 'nullable|string',
            'offset' => 'nullable|integer|min:0',
        ]);
        $response = [];

        $noShop = $request->get('no_shop');
        $noPartner = $request->get('no_partner');

        if (!$noPartner && !$noShop) {
            throw new OwinException(Code::message('409'));
        }

        $shopInfo = $noShop ? ShopService::getShop($noShop) : null;
        if (!$noPartner && $noShop) {
            $noPartner = $shopInfo && $shopInfo['no_partner'] ? $shopInfo['no_partner'] : $noPartner;
        }

        $partnerInfo = PartnerService::get($noPartner);
        if (!$shopInfo && !$partnerInfo) {
            throw new OwinException(Code::message('M1303'));
        }

        $categories = RetailProductService::getRetailCategory($noPartner)->get()->toArray();
        $response['categories'] = [];
        if (count($categories)) {
            foreach ($categories as $category) {
                if ($category['retail_sub_categories']) {
                    foreach ($category['retail_sub_categories'] as $subCategory) {
                        $subCategory['nm_category'] = $category['nm_category'] . ' ' . $subCategory['nm_sub_category'];
                        $response['categories'][] = $subCategory;
                    }
                } else {
                    unset($category['retail_sub_categories']);
                    $response['categories'][] = $category;
                }
            }
        }


        $response['package_product'] = RetailProductService::getRetailProduct(
            $noPartner,
            null,
            null,
            null,
            null,
            null,
            true
        );

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     * @throws TMapException
     *
     * 매장정보 - 매장상세정보
     */
    public function infoDetail(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'required|integer',
        ]);
        $response = [];

        $noShop = $request->get('no_shop');
        $shopInfo = ShopService::getShop($noShop);
        if (!$shopInfo) {
            throw new OwinException(Code::message('M1303'));
        }

        //매장 영업시간
        $response['shop_opt_time'] = ShopService::getInfoOptTimeAll($noShop);

        //매장 휴무
        $response['shop_holiday'] = ShopService::getShopHoliday($noShop);

        $response['yn_open'] = $response['shop_holiday']['yn_open'];

        $response['review_total'] = ReviewService::getReviewTotal($noShop);


        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     * @throws TMapException
     *
     * todo info()와 동일 정보임 삭제 예정
     * 리테일 매장 픽업존 정보
     */
    public function pickupInfo(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'required|integer',
        ]);
        $noShop = $request->get('no_shop');

        $shopInfo = ShopService::getShop($noShop);
        if (!$shopInfo) {
            throw new OwinException(Code::message('M1303'));
        }
        return response()->json($shopInfo);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 리뷰 - 리뷰리스트
     */
    public function review(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'required|integer',
            'no_user' => 'nullable|integer',
            'offset' => 'nullable|integer'
        ]);
        $noShop = $request->get('no_shop');
        $noUser = $request->get('no_user');

        $size = $request->get('size') ?: Code::conf('default_size');
        $offset = $request->get('offset') ?: 0;

        $response = [];
        if ($noUser) {
            $response['yn_week_order'] = OrderService::checkReviewWriteAuth($noUser, $noShop) ? 'Y' : 'N';
        }

        $response['review_info'] = ReviewService::getReviewTotal($noShop);

        $response['reviews'] = ReviewService::getReviews($noShop, $offset, $size);

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     * @throws OwinException
     * @throws TMapException
     *
     * 상품 리스트 - 카테고리별 상품 리스트 조회
     */
    public function productList(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'required|integer',
            'no_category' => 'required|integer',
            'no_sub_category' => 'nullable|integer',
        ]);
        $noShop = (int)$request->get('no_shop');
        $noCategory = (int)$request->get('no_category');
        $noSubCategory = $request->get('no_sub_category');

        $size = $request->get('size');
        $ctPage = $request->get('ct_page');

        $response = [];

        $shopInfo = $noShop ? ShopService::shop($noShop)->first() : null;
        $noPartner = $shopInfo['no_partner'];

        $partnerInfo = PartnerService::get($noPartner);
        if (!$shopInfo && !$partnerInfo) {
            throw new OwinException(Code::message('M1303'));
        }

        ## 상품리스트
        // 카테고리 상품 - 서브카테고리있을 경우 서브카테고리 1번 상품리스트
        // 고유번호 없을 경우 ($ctPage IS NULL) 전체상품
        // 고유번호 있을 경우 - 고유번호기준 limit ( 0인경우 처음부터 limit)
        $response['products'] = RetailProductService::getRetailProduct(
            $noPartner,
            $noShop,
            $noCategory,
            $noSubCategory,
            $size,
            $ctPage
        );
        if (count($response['products'])) {
//            $productIds = array_merge($productIds, $response['products']->pluck('no_barcode')->all());
            $productIds = RetailProductService::getRetailProductIds($response['products']);

            ## ===============================================================================
            ## [4] 실시간 상품 재고조회 추가  [ CU 상품 재고조회 ]
            //$response['list_product_stock']	    = $list_product_stock; // 재고조회 상품 리스트 ( 일반상품 + 옵션상품) TEST
            ## ===============================================================================
            if ($noPartner === Code::conf('cu.partner_no')) {
                $realProductStock = Cu::stock($shopInfo->store_cd, $noCategory);
                // 옵션그룹 : 타입 리스트 - Array
                $setProductOptTypeArr = [];
                foreach ($response['products'] as $product) {
                    $stock = RetailService::getOptionMinStock($product->no_product,  $realProductStock, $product->productOptionGroups);

                    ## [1] 상품금액정보
                    $product['cnt_product'] = match ($product->productOptionGroups?->where('cd_option_type', OptionType::REQUIRED->value)->count()) {
                        0 => data_get($realProductStock->firstWhere('no_barcode', $product->no_barcode), 'cnt_product', 0),
                        default => $stock['require']
                    };
//                    $product['min_cnt'] = $minCount;


                    ## [2] 부분 품절
                    // 옵션상품중 품절수량이 있거나 전체상품보다 품절상품  수가 적을 경우 : 부분품절
                    $product['yn_soldout'] = match ($product->productOptionGroups?->where('cd_option_type', OptionType::REQUIRED->value)->count()) {
                        0 => $product['cnt_product'] > 0 ? 'N' : 'Y',
                        default => $stock['require'] > 0 ? 'N' : 'Y'
                    };
                    $product['yn_part_soldout'] = match ($product->productOptionGroups?->where('cd_option_type', OptionType::SELECT->value)->count()) {
                        0 => 'N',
                        default => $stock['select'] > 0 ? 'N' : 'Y'
                    };
                }
            }
        }

        return response()->json([
            'result' => true,
            'products' => $response['products']->filter(function ($collect) {
                return $collect['yn_soldout'] == 'N' && $collect['yn_part_soldout'] == 'N';
            })->map(function ($product) {
                return [
                    'no_product' => $product['no_product'],
                    'no_category' => $product['no_category'],
                    'no_sub_category' => $product['no_sub_category'],
                    'nm_product' => $product['nm_product'],
                    'at_price_before' => $product['at_price_before'],
                    'at_price' => $product['at_price'],
                    'ds_image_path' => empty(data_get($product, 'ds_image_path') == false) ? Common::getImagePath(data_get($product, 'ds_image_path')) : null,
                    'cnt_product' => $product['cnt_product'],
                    'yn_soldout' => $product['yn_soldout'],
                    'at_ratio' => Common::getSaleRatio($product['at_price_before'], $product['at_price']),
                    'yn_part_soldout' => $product['yn_part_soldout']
                ];
            })->values()
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     * @throws TMapException
     *
     * 상품명 검색
     */
    public function searchProduct(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'required|integer',
            'search_word' => 'required|string'
        ]);
        $noShop = $request->get('no_shop');
        $noUser = $request->get('no_user');
        $searchWord = $request->get('search_word');

        $shopInfo = ShopService::shop($noShop)->first();
        if (!$shopInfo) {
            throw new OwinException(Code::message('M1303'));
        }

        ProductService::createSearchLog($noShop, $searchWord, $noUser);
        $searchWord = substr(strip_tags(trim($searchWord)), 0, 200);

        if (!$searchWord) {
            throw new OwinException(Code::message('P2044'));
        }

        $rows = RetailProductService::getSearchProduct($shopInfo['no_partner'], $searchWord);
        if (count($rows)) {
            return response()->json([
                'count' => count($rows),
                'rows' => $rows,
            ]);
        } else {
            throw new OwinException(Code::message('404'));
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     * @throws OwinException
     * @throws TMapException
     *
     * 상품 상세정보
     */
    public function productInfo(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'required|integer',
            'no_product' => 'required|integer'
        ]);
        $noShop = (int)$request->no_shop;
        $noProduct = (int)$request->no_product;

        $shopInfo = ShopService::shop($noShop)->first();
        if (!$shopInfo) {
            throw new OwinException(Code::message('M1303'));
        }

        $retailProduct = RetailProductService::getRetailProductInfo($shopInfo['no_partner'], $noShop, $noProduct);
        if (!$retailProduct) {
            throw new OwinException(Code::message('P2045'));
        }

        $productIds = RetailProductService::getRetailProductIds(collect([$retailProduct]));

        $realProductStock = match ($shopInfo['no_partner'] === Code::conf('cu.partner_no')) {
            true => (function () use ($shopInfo, $retailProduct) {
                return Cu::stock($shopInfo->store_cd, $retailProduct->no_category);
            })(),
            default => collect()
        };

        return response()->json([
            'result' => true,
            ...(array)(new ProductInfo($retailProduct, $realProductStock))->setProductInfo()
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 장바구니 상품 정보
     */
    public function cart(Request $request): JsonResponse
    {
        $request->validate([
            'no_products' => 'required|array',
            'cart_product' => 'required'
        ]);

        $retailProducts = RetailProductService::getretailproducts([
            'ds_status' => 'Y'
        ], $request->cart_product);

        return response()->json([
            'result' => true,
            'product' => $retailProducts
        ]);
    }

    public function envelope(): JsonResponse
    {
        return response()->json([
            'result' => true,
            'envelope' => RetailService::envelope()
        ]);
    }
}

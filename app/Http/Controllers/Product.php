<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Pickup;
use App\Enums\SearchBizKind;
use App\Exceptions\OwinException;
use App\Exceptions\TMapException;
use App\Services\OrderService;
use App\Services\ProductService;
use App\Services\RetailProductService;
use App\Services\ShopService;
use App\Utils\Code;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class Product extends Controller
{
    /**
     * @param Request $request
     * @param int $noShop
     * @return JsonResponse
     * @throws OwinException
     * @throws TMapException
     *
     * 상품 리스트
     */
    public function getList(Request $request, int $noShop): JsonResponse
    {
        $request->validate([
            'noCategory' => 'nullable|integer',
            'pickup_type' => ['nullable', Rule::in(Pickup::keys())],
        ]);

        $noCategory = (int)$request->get('noCategory');
        $type = $request->get('pickup_type');

        $shopInfo = ShopService::shop($noShop)->first();
        $product = ProductService::gets($shopInfo, $noCategory, $type);

        return response()->json([
            'result' => true,
            ...$product,
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 현재위치알림
     */
    public function setGps(Request $request): JsonResponse
    {
        $request->validate([
            'no_order' => 'required|string',
            'at_lat' => 'required|numeric',
            'at_lng' => 'required|numeric',
            'at_distance' => 'required|numeric',
        ]);

        $noOrder = $request->get('no_order');
        $atLat = $request->get('at_lat');
        $atLng = $request->get('at_lng');
        $atDistance = $request->get('at_distance');

        OrderService::changeDistance($atDistance, $atLat, $atLng, $noOrder);

        return response()->json([
            'result' => true,
        ]);
    }

    /**
     * @param int $noShop
     * @param int $noProduct
     * @return JsonResponse
     *
     * 상품 상세
     */
    public function product(int $noShop, int $noProduct): JsonResponse
    {
        return response()->json([
            'result' => true,
            'no_shop' => $noShop,
            'no_partner' => (int)substr((string)$noShop, 0, 4),
            'product' => (new ProductService())->getProduct(parameter: [
                'no_product' => $noProduct,
                'ds_status' => 'Y'
            ], noShop: $noShop, excludeShop: $noShop)->whenEmpty(function () {
                throw new OwinException(Code::message('P2045'));
            }, function ($product) {

            })->first()
        ]);
    }

    /**
     * @param int $noShop
     * @param Request $request
     * @return JsonResponse
     *
     * 장바구니 상품 정보
     */
    public function cart(int $noShop, Request $request): JsonResponse
    {
        $request->validate([
            'no_products' => 'required|array',
            'cart_product' => 'required'
        ]);

        return response()->json([
            'result' => true,
            'product' => ProductService::getCartProduct(
                noShop: $noShop,
                cartProduct: $request->cart_product,
            )->map(function ($product) {
                return [
                    'no_product' => $product['no_product'],
                    'nm_product' => $product['nm_product'],
                    'ea' => $product['ea'],
                    'cd_discount_sale' => $product['cd_discount_sale'],
                    'at_price' => $product['at_price'],
                    'current_price' => $product['current_price'],
                    'yn_soldout' => $product['yn_soldout'],
                    'yn_cup_deposit' => $product['yn_cup_deposit'],
                    'option_groups' => $product['option_groups']
                ];
            })
        ]);
    }

    public function getCart(Request $request): JsonResponse
    {
        $request->validate([
            '*.no_shop' => 'nullable|integer',
            '*.biz_kind' => 'nullable|string',
            '*.list_product' => 'nullable|array',
        ]);

        $carts = array_map(function (array $cart) {
            return match (data_get($cart, 'biz_kind')) {
                SearchBizKind::FNB->name => ProductService::getCartProduct(
                    noShop: data_get($cart, 'no_shop'),
                    cartProduct: $cart,
                ),
                SearchBizKind::RETAIL->name => RetailProductService::getretailproducts([
                    'ds_status' => 'Y'
                ], $cart),
                default => []
            };
        }, array_filter($request->all(), function ($value) {
            return !empty($value['no_shop']) && !empty($value['biz_kind']) && count($value['list_product']) > 0;
        }));

        return response()->json([
            'result' => true,
            'data' => collect($carts)->map(function (Collection $cart) {
                return match (!empty($cart->first())) {
                    true => [
                        'no_shop' => data_get($cart->first(), 'no_shop'),
                        'biz_kind' => data_get($cart->first(), 'biz_kind'),
                        'nm_shop' => data_get($cart->first(), 'nm_shop'),
                        'pickup_type' => data_get($cart->first(), 'pickup_type'),
                        'at_price_total' => $cart->filter()->sum(
                            fn(array $product
                            ) => (data_get($product, 'two_plus_one_option.at_price', data_get($product, 'at_price')) + (collect(data_get($product, data_get($cart->first(), 'biz_kind') == 'FNB' ? 'option_groups.*.product_options' : 'product_option_groups.*.product_option_products'))->filter()->sum(
                                        fn(Collection $options) => $options->sum(fn(array $option
                                        ) => data_get($option, 'at_add_price') * data_get($option, 'ea', 1)
                                        )
                                    ))) * data_get($product, 'ea', 1)
                        ),
                        'list_product' => $cart->filter()->map(fn(array $product) => [
                            'no_product' => data_get($product, 'no_product'),
                            'nm_product' => data_get($product, 'nm_product'),
                            'ea' => data_get($product, 'ea'),
                            'cd_discount_sale' => data_get($product, 'cd_discount_sale'),
                            'discount_type' => data_get($product, 'discount_type'),
                            'at_price' => data_get($product, 'at_price'),
                            'current_price' => data_get($product, 'current_price'),
                            'yn_soldout' => data_get($product, 'yn_soldout'),
                            'yn_cup_deposit' => data_get($product, 'yn_cup_deposit'),
                            'option' => collect(data_get($product, data_get($cart->first(), 'biz_kind') == 'FNB' ? 'option_groups.*.product_options' : 'product_option_groups.*.product_option_products'))->map(
                                fn(Collection $options) => $options->map(fn(array $option) => [
                                    'no_option_group' => data_get($option, 'no_group'),
                                    'no_option' => data_get($option, 'no_option'),
                                    'nm_option' => data_get($option, 'nm_option'),
                                    'nm_group' => data_get($option, 'nm_group'),
                                    'at_add_price' => data_get($option, 'at_add_price'),
                                    'add_price' => data_get($option, 'at_add_price'),
                                    'yn_check_stock' => data_get($option, 'yn_check_stock'),
                                    'cnt_product' => data_get($option, 'cnt_product'),
                                    'yn_soldout' => data_get($option, 'yn_soldout'),
                                    'yn_cup_deposit' => data_get($option, 'yn_cup_deposit'),
                                    'ea' => data_get($option, 'ea'),
                                ])
                            )->flatten(1),
                            'two_plus_one_option' => data_get($product, 'two_plus_one_option')
                        ])->values()
                    ],
                    default => null,
                };
            })->filter()->values()
        ]);
    }
}

<?php

namespace App\Utils;

use App\Exceptions\SlackNotiException;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\Shop;
use App\Services\ProductService;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class Spc
{

    public static array $brandCodes = ['PBparis'];
    public static array $storeCodes = ['0000007'];

    private static function client(string $path, array $json = [], string $method = 'POST'): array
    {
        $response = (new Client())->request($method, sprintf('%s%s', env('PROXY_URI'), $path), [
            'headers' => getProxyHeaders(),
            'timeout' => 5,
            'json' => $json,
            'http_errors' => false
        ]);
        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * 주문 연동
     * @param string $noOrder
     * @return mixed
     * @throws SlackNotiException
     */
    public static function order(string $noOrder)
    {
        try {
            $data = self::client(sprintf(env('SPC_API_PATH_ORDER'), $noOrder));
            if ($data) {
                if (data_get($data, 'resultCode') == 'S000') {
                    return $data['resultData'];
                } else {
                    throw new SlackNotiException(data_get($data, 'message'));
                }
            }
            throw new SlackNotiException(Code::message('E999'));
        } catch (Throwable $t) {
            Log::channel('spc')->critical('spc order error: ', [$t->getMessage()]);
            throw new SlackNotiException($t->getMessage());
        }
    }

    /**
     * @param string $brandCode
     * @param string $storeCode
     * @param int $category
     * @return array|null
     */
    public static function stock(string $brandCode, string $storeCode, int $category): ?array
    {
        $cacheKey = sprintf(env('SPC_STOCK_CACHE_KEY'), $brandCode, $storeCode, $category);

        return match (Cache::has($cacheKey)) {
            true => Cache::get($cacheKey),
            default => (function () use ($brandCode, $storeCode, $category, $cacheKey) {
                $products = ProductService::getSpcStockProduct(category: [$category]);
                return match ($products->isEmpty()) {
                    true => null,
                    default => (function () use ($brandCode, $storeCode, $products, $cacheKey) {
                        $stock = self::realStock($brandCode, $storeCode, $products);
                        data_set($stock, 'time', now());
                        Cache::set($cacheKey, $stock, env('SPC_STOCK_CACHE_TTL'));

                        return $stock;
                    })()
                };
            })()
        };
    }

    /**
     * @param string $brandCode
     * @param string $storeCode
     * @param Collection $categorys
     * @return void
     */
    public static function productsStock(string $brandCode, string $storeCode, Collection $categorys): void
    {
        $categorys->filter(fn(string $category) => !Cache::has(sprintf(env('SPC_STOCK_CACHE_KEY'), $brandCode, $storeCode, $category)))->whenNotEmpty(function (Collection $cacheCategory) use ($brandCode, $storeCode) {
            $products = ProductService::getSpcStockProduct(category: $cacheCategory->toArray());
            $stock = $products->chunk(env('SPC_API_PRODUCT_LIMIT'))->map(fn(Collection $chunkProducts) => self::realStock($brandCode, $storeCode, $chunkProducts->values()));

            $products->groupBy('no_partner_category')->map(function (Collection $groupProduct) use ($brandCode, $storeCode, $stock) {
                $categoryStock = $groupProduct->map(fn(Product $product) => [
                    'code' => $product->cd_spc,
                    'qty' => data_get(collect($stock)->flatten(1)->firstWhere('code', $product->cd_spc), 'qty', 0)
                ])->toArray();
                data_set($categoryStock, 'time', now());

                Cache::set(sprintf(env('SPC_STOCK_CACHE_KEY'), $brandCode, $storeCode, $groupProduct->first()->no_partner_category), $categoryStock, env('SPC_STOCK_CACHE_TTL'));
            });
        });
    }

    /**
     * @param string $brandCode
     * @param string $storeCode
     * @param Collection $products
     * @return array
     * @throws SlackNotiException
     */
    public static function realStock(string $brandCode, string $storeCode, Collection $products): array
    {
        $stock = Spc::client(env('SPC_API_PATH_STOCK'), [
            'brandCode' => $brandCode,
            'storeCode' => $storeCode,
            'menus' => $products->map(fn(Product $product) => [
//                'name' => $product->nm_product,
                'code' => $product->cd_spc,
                'options' => match ($products->count() == 1) {
                    true => $product->productOptionGroups->whereIn('no_group', $product->option_group)->map(function (ProductOptionGroup $productOptionGroup) {
                        return $productOptionGroup->productOptions->map(fn(ProductOption $option) => [
                            'code' => $option->cd_spc,
//                        'name' => $option->nm_option
                        ]);
                    })->flatten(1)->values(),
                    default => []
                }
            ])->all()
        ]);

        return match (data_get($stock, 'resultCode')) {
            'S000' => data_get($stock, 'resultData.menus'),
            default => throw new SlackNotiException(data_get($stock, 'result_msg'))
        };
    }

    /**
     * @param string $brandCode
     * @param string $storeCode
     * @param Product $product
     * @return array
     */
    public static function productStock(string $brandCode, string $storeCode, Product $product): array
    {
        $cacheKey = sprintf(env('SPC_PRODUCT_STOCK_CACHE_KEY'), $brandCode, $storeCode, $product->cd_spc);
        $cacheCategoryKey = sprintf(env('SPC_STOCK_CACHE_KEY'), $brandCode, $storeCode, $product->no_partner_category);

        return match (Cache::has($cacheKey)) {
            true => Cache::get($cacheKey),
            default => (function () use ($brandCode, $storeCode, $product, $cacheKey, $cacheCategoryKey) {
                $stock = self::realStock($brandCode, $storeCode, collect([$product]));
                Cache::set($cacheKey, $stock, env('SPC_PRODUCT_STOCK_CACHE_TTL'));
                if (Cache::has($cacheCategoryKey)) {
                    $cacheValue = collect(Cache::get($cacheCategoryKey))->where('code', $product->cd_spc)->toArray();

                    Cache::set(
                        key: $cacheCategoryKey,
                        value:  array_replace(Cache::get($cacheCategoryKey), [key($cacheValue) => data_get($stock, 0, $cacheValue)]),
                        ttl: env('SPC_STOCK_CACHE_TTL') - now()->diffInSeconds(data_get(Cache::get($cacheCategoryKey), 'time'))
                    );
                }

                return $stock;
            })()
        };
    }

    /**
     * @param string $brandCode
     * @param string $storeCode
     * @param Product $product
     * @return array
     * @throws SlackNotiException
     */
    public static function realProductStock(string $brandCode, string $storeCode, Product $product): array
    {
        return self::realStock($brandCode, $storeCode, collect([$product]));
    }

    /**
     * 주문 취소
     * @param string $orderId
     * @param Shop $shop
     * @param string|null $reason
     *
     * @return mixed
     * @throws SlackNotiException
     */
    public static function cancel(string $orderId, Shop $shop, string $reason = null)
    {
        try {
            $data = self::client(env('SPC_API_PATH_CANCEL'), [
                'brandCode' => $shop->partner->cd_spc_brand,
                'storeCode' => $shop->cd_spc_store,
                'orderChannel' => 'OWIN',
                'orderId' => $orderId,
                'cancelType' => match ($reason) {
                    '606300' => 'cancel_accept',
                    '601999' => 'cancel_cs',
                    default => 'cancel_order'
                },
                'cancelMessage' => match ($reason) {
                    '601900' => '회원결제취소',
                    '601950' => '매장결제취소',
                    '601999' => '운영자결제취소',
                    '606100' => '재고 부족',
                    '606200' => '매장 혼잡',
                    '606300' => '5분 자동취소',
                    '606610' => '요청사항 처리 불가',
                    '606620' => '도착 시간 내 조리 불가',
                    '606630' => '주문불가상품',
                    default => $reason,
                }
            ]);
            if ($data) {
                if (data_get($data, 'resultCode') == 'S000') {
                    return true;
                } else {
                    throw new SlackNotiException(data_get($data, 'message'));
                }
            }
            throw new SlackNotiException(Code::message('E999'));
        } catch (Throwable $t) {
            Log::channel('error')->critical('spc order cancel error: ', [$t->getMessage()]);
            throw new SlackNotiException(Code::message('E999'));
        }
    }

    /**
     * 도착예정시간 업데이트
     * @param string      $brandCode
     * @param string      $storeCode
     * @param string      $orderId
     * @param string      $arvYn
     * @param string|null $arvHm
     *
     * @return bool
     * @throws SlackNotiException
     */
    public static function uptime(string $brandCode, string $storeCode, string $orderId, string $arvYn = 'Y', string $arvHm = null)
    {
        try {
            $data = self::client(env('SPC_API_PATH_UPTIME'), [
                'orderChannel' => 'OWIN',
                'brandCode' => $brandCode,
                'storeCode' => $storeCode,
                'orderId' => $orderId,
                'arvYn' => $arvYn,
                'arvHm' => $arvHm
            ]);
            if ($data) {
                if ( data_get($data, 'resultCode') == 'S000') {
                    return true;
                } else {
                    throw new SlackNotiException(data_get($data, 'message'));
                }
            }
            throw new SlackNotiException(Code::message('E999'));
        } catch (Throwable $t) {
            Log::channel('error')->critical('spc order uptime error: ', [$t->getMessage()]);
            throw new SlackNotiException(Code::message('E999'));
        }
    }
}
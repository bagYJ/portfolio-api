<?php

declare(strict_types=1);

namespace App\Utils;

use App\Exceptions\OwinException;
use App\Models\RetailProduct;
use App\Models\RetailProductOption;
use App\Models\RetailProductOptionGroup;
use App\Services\RetailProductService;
use App\Services\RetailService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Cu
{
    /**
     * @param string $path
     * @param array $json
     * @param string $method
     * @return array
     * @throws GuzzleException
     */
    public static function client(string $path, array $json = [], string $method = 'POST'): array
    {
        Log::channel('cu')->info('request : ', $json);
        $response = (new Client())->request($method, sprintf('%s%s', env('PROXY_URI'), $path), [
            'headers' => getProxyHeaders(),
            'timeout' => 5,
            'json' => $json,
            'http_errors' => false
        ]);
        $data = json_decode($response->getBody()->getContents(), true);
        Log::channel('cu')->info('response ' . $path . ': ', $data);

        return [
            'result' => data_get($data, 'result_code') == '0000',
            ...$data
        ];
    }

    /**
     * hash 데이터 정확성 체크
     * @param string $sign
     * @param string $changeSign
     * @param array $request
     * @return void
     */
    public static function hashAccuracyCheck(string $sign, string $changeSign, array $request)
    {
        if ($sign !== $changeSign) {
            RetailService::insertRetailExternalResultLog($request, [
                'result' => false,
                'result_code' => '9998'
            ]);
            throw new OwinException(Code::message('9998'));
        }
    }

    /**
     * sign 생성
     * @param array $data
     * @return false|string
     */
    public static function generateSign(array $data)
    {
        return hash("sha256", implode("", $data));
    }

    public static function stock(string $storeCd, int $category): Collection
    {
        $cacheKey = sprintf(env('CU_STOCK_CACHE_KEY'), $storeCd, $category);
        return match (Cache::has($cacheKey)) {
            true => Cache::get($cacheKey),
            default => (function () use ($storeCd, $category, $cacheKey) {
                $products = RetailService::getRetailNoBarcode(category: $category);
                $stock = self::realStock($storeCd, $products);
                Cache::set($cacheKey, $stock, env('CU_STOCK_CACHE_TTL'));

                return $stock;
            })()
        };
    }

    public static function realStock(string $storeCd, Collection $products): Collection
    {
        $barcodes = RetailProductService::getRetailProductIds($products);
        $response = Cu::client(env('CU_API_PATH_PRODUCT_CHECK'), [
            'shop_code' => $storeCd,
            'product_list' => $barcodes
        ]);

        return $products->map(function (RetailProduct $product) use ($response) {
            return [
                'no_product' => $product->no_product,
                'no_barcode' => $product->no_barcode,
                'cnt_product' => data_get($response, sprintf('product_list.%s', $product->no_barcode), 0),
                'option' => $product->productOptionGroups?->map(function (RetailProductOptionGroup $group) use ($response) {
                    return $group->productOptionProducts->map(function (RetailProductOption $option) use ($response) {
                        return [
                            'no_option' => $option->no_option,
                            'no_barcode' => $option->no_barcode_opt,
                            'cnt_product' => data_get($response, sprintf('product_list.%s', $option->no_barcode_opt), 0)
                        ];
                    });
                })->flatten(1)
            ];
        });
    }
}

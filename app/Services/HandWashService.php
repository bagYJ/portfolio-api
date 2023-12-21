<?php

namespace App\Services;

use App\Models\WashHandPrice;
use App\Models\WashHandProduct;
use Illuminate\Support\Collection;

class HandWashService
{
    /**
     * 출장세차 상품정보
     * @param array $parameter
     *
     * @return Collection
     */
    public static function getProducts(array $parameter, ?array $whereIn = null): Collection
    {
        return WashHandProduct::with(['prices', 'partner'])->where($parameter)
            ->when(empty($whereIn) === false, function ($query) use ($whereIn) {
                foreach ($whereIn as $key => $value) {
                    $query->whereIn($key, $value);
                }
            })->get();
    }

    /**
     * 출장세차 상품 - 가격정보
     * @param array $parameter
     *
     * @return Collection
     */
    public static function getPrices(array $parameter): Collection
    {
        return WashHandPrice::where($parameter)->get();
    }

}
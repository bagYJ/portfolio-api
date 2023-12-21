<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DiscountSale;
use App\Models\RetailCategory;
use App\Models\RetailProduct;
use App\Models\RetailProductBestLabel;
use App\Utils\Code;
use App\Utils\Common;
use App\Utils\Cu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RetailProductService
{
    /**
     * @param $noPartner
     * @return RetailProductBestLabel|Model|object|null
     */
    public static function getRetailProductBestLabel($noPartner)
    {
        return RetailProductBestLabel::where([
            'no_partner' => $noPartner,
            'ds_status' => 'Y'
        ])->orderByDesc('no')->first();
    }

    /**
     * @param $noPartner
     * @param $offset
     * @param $size
     * @return Builder[]|Collection
     */
    public static function getRetailProductBest($noPartner, $offset, $size)
    {
        $retailProductBestLabel = RetailProductBestLabel::where([
            'no_partner' => $noPartner,
            'ds_status' => 'Y'
        ])->orderByDesc('no')->first();

        //todo 테스트 필요 (retail_product_best에 데이터가 없음)
        return RetailProduct::select([
            DB::raw('retail_product.*'),
            DB::raw("0 AS cnt_product"),
            DB::raw("'' AS yn_soldout"),
        ])->join('retail_product_best', function ($q) {
            $q->on('retail_product.no_product', 'retail_product_best.no_product');
            $q->on('retail_product.no_partner', 'retail_product_best.no_partner');
        })->with([
            'productOptionGroups.productOptionProducts'
        ])->where([
            ['retail_product.no_partner', '=', $noPartner],
            ['retail_product.ds_status', '=', 'Y'],
            ['retail_product.yn_show', '=', 'Y'],
            ['retail_product.dt_sale_st', '<=', DB::raw("CURRENT_TIMESTAMP()")],
            ['retail_product.dt_sale_end', '>=', DB::raw("CURRENT_TIMESTAMP()")],
        ])->orderBy('retail_product_best.at_view')->take($size)->offset($offset)->get()->map(
            function ($value) use ($retailProductBestLabel) {
                if ($retailProductBestLabel['ds_label']) {
                    $value['ds_label'] = $retailProductBestLabel['ds_label'];
                }
                return $value;
            }
        );
    }

    /**
     * @param $noPartner
     * @param $noCategory
     * @param $noSubCategory
     * @return Builder
     */
    public static function getRetailCategory($noPartner, $noCategory = null, $noSubCategory = null)
    {
        $where = [
            ['no_partner', '=', $noPartner],
            ['ds_status', '=', 'Y'],
            ['yn_show', '=', 'Y'],
        ];

        $retailCategory = RetailCategory::with(['retailSubCategories']);
        if ($noCategory) {
            $where[] = ['no_category', '=', $noCategory];
        } else {
            $where[] = ['dt_use_st', '<=', DB::raw("CURRENT_TIMESTAMP()")];
            $where[] = ['dt_use_end', '>=', DB::raw("CURRENT_TIMESTAMP()")];
        }

        if ($noSubCategory) {
            $retailCategory = $retailCategory->with([
                'retailSubCategories' => function ($q) use ($noSubCategory) {
                    $q->select([
                        'no_category',
                        'no_sub_category',
                        'nm_sub_category'
                    ]);
                    $q->where('no_sub_category', $noSubCategory);
                }
            ]);
        }

        return $retailCategory->where($where)->orderByDesc('yn_top')->orderBy('at_view')->orderBy(
            'no_category'
        )->orderBy('at_view');
    }

    /**
     * @param $noPartner
     * @param $noShop
     * @param $noCategory
     * @param $noSubCategory
     * @param $size
     * @param $ctPage
     * @param $isPackage
     * @return Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function getRetailProduct(
        $noPartner,
        $noShop,
        $noCategory,
        $noSubCategory,
        $size,
        $ctPage,
        $isPackage = false
    ) {
        $where = [
            ['retail_product.ds_status', '=', 'Y'],
            ['retail_product.yn_show', '=', 'Y'],
//            ['retail_product.dt_sale_st', '<=', DB::raw("CURRENT_TIMESTAMP()")],
//            ['retail_product.dt_sale_end', '>=', DB::raw("CURRENT_TIMESTAMP()")]
        ];

        $select = [
            'retail_product.*',
        ];

        if ($noPartner) {
            $where[] = ['retail_product.no_partner', '=', $noPartner];
        }

        if ($noCategory && !$isPackage) {
            $where[] = ['retail_product.no_category', '=', $noCategory];
        }

        if ($noSubCategory) {
            $where[] = ['retail_product.no_sub_category', '=', $noSubCategory];
        }

        if ($ctPage) {
            $where[] = ['retail_product.no', '>', $ctPage];
        }

        if ($isPackage) {
            $where[] = ['retail_product.no_category', '=', "{$noPartner}9999"];
        }

        $retailProduct = RetailProduct::where($where)->whereBetween(
            DB::raw('now()'),
            [DB::raw('retail_product.dt_sale_st'), DB::raw('retail_product.dt_sale_end')]
        );

        if ($noShop) {
            $select[] = 'retail_shop_product_stock.cnt_product';
            $select[] = 'retail_shop_product_stock.yn_soldout';

            $retailProduct = $retailProduct->leftJoin('retail_shop_product_stock', function ($q) use ($noShop) {
                $q->on('retail_product.no_product', 'retail_shop_product_stock.no_product');
                $q->where('no_shop', $noShop);
            });
        }

        if ($size) {
            $retailProduct = $retailProduct->take($size);
        }

        return $retailProduct->select($select)->with([
            'productOptionGroups.productOptionProducts',
        ])->orderBy('at_view')->get();
    }

    /**
     * @param $noPartner
     * @param $searchWord
     * @return Collection
     */
    public static function getSearchProduct($noPartner, $searchWord)
    {
        return RetailProduct::select([
            'no_product',
            'nm_product',
            DB::raw("POSITION('{$searchWord}' IN nm_product) AS pos")
        ])->where([
            ['no_partner', '=', $noPartner],
            ['nm_product', 'LIKE', "%{$searchWord}%"],
            ['ds_status', '=', 'Y'],
            ['yn_show', '=', 'Y'],
            ['dt_sale_st', '<=', DB::raw("CURRENT_TIMESTAMP()")],
            ['dt_sale_end', '>=', DB::raw("CURRENT_TIMESTAMP()")],
        ])->orderByRaw("POSITION('{$searchWord}' IN nm_product) ASC")->get();
    }

    /**
     * @param $noPartner
     * @param $noShop
     * @param $noProduct
     * @return RetailProduct|null
     */
    public static function getRetailProductInfo($noPartner, $noShop, $noProduct): ?RetailProduct
    {
        return RetailProduct::select([
            'retail_product.*',
            'retail_shop_product_stock.cnt_product',
            'retail_shop_product_stock.yn_soldout',
        ])->leftJoin('retail_shop_product_stock', function ($q) use ($noShop) {
            $q->on('retail_product.no_product', 'retail_shop_product_stock.no_product');
            $q->where('retail_shop_product_stock.no_shop', $noShop);
        })->where([
            ['retail_product.no_partner', '=', $noPartner],
            ['retail_product.no_product', '=', $noProduct],
        ])->with([
            'productOptionGroups.productOptionProducts',
        ])->get()->map(function ($product) {
            $product->ds_image_path = match (!$product->ds_image_path || !file_exists($product->ds_image_path)) {
                true => Common::getImagePath("/data2/partner/retail_default.jpg"),
                default => Common::getImagePath($product->ds_image_path)
            };

            return $product;
        })->first();
    }

    /**
     * @param $products
     * @return object
     */
    public static function getRetailProductIds($products): object
    {
        return $products->map(function ($product) {
            return $product->productOptionGroups?->map(function ($group) {
                return $group->productOptionProducts?->map(function ($option) {
                    return $option->no_barcode_opt;
                })->values();
            })->collect()->merge($product->no_barcode);
        })->flatten();
    }

    /**
     * @param array $parameter
     * @param array|null $cartProduct
     * @return Collection|null
     */
    public static function getRetailProducts(array $parameter, ?array $cartProduct = []): ?Collection
    {
        $shop = ShopService::shop($cartProduct['no_shop'])->first();
        if (empty($shop)) return null;
        $listProducts = collect($cartProduct['list_product']);
        $products = RetailService::getRetailNoBarcode(products: $listProducts->pluck('no_product')->all());
        $stock = Cu::realStock($shop->store_cd, $products);

        return $listProducts->map(function (array $listProduct) use ($products, $stock, $shop, $cartProduct) {
            return $products->where('no_product', $listProduct['no_product'])->filter()->map(function (RetailProduct $product) use ($listProduct, $stock, $shop, $cartProduct) {
                $cntProduct = data_get($stock->firstWhere('no_barcode', $product->no_barcode), 'cnt_product', 0);

                return [
                    'no_shop' => $shop->no_shop,
                    'biz_kind' => data_get($cartProduct, 'biz_kind'),
                    'nm_shop' => sprintf('%s %s', $shop->partner->nm_partner, $shop->nm_shop),
                    'pickup_type' => data_get($cartProduct, 'pickup_type'),
                    'no_product' => $product->no_product,
                    'nm_product' => $product->nm_product,
                    'at_price' => $listProduct['at_price'],
                    'ea' => $listProduct['ea'],
                    'cd_discount_sale' => $product->cd_discount_sale,
                    'discount_type' => data_get($listProduct, 'discount_type'),
                    'current_price' => $product->at_price,
                    'yn_soldout' => $cntProduct ? 'N' : 'Y',
                    'cnt_product' => $cntProduct,
                    'product_option_groups' => $product->productOptionGroups->filter(function ($query) use ($listProduct) {
                        return in_array($query->no_group, collect($listProduct['option'])->pluck('no_option_group')->all()) == true;
                    })->map(function ($optionGroup) use ($listProduct, $stock) {
                        return [
                            'no_group' => $optionGroup->no_group,
                            'nm_group' => $optionGroup->nm_group,
                            'cd_option_type' => $optionGroup->cd_option_type,
                            'at_select_min' => $optionGroup->at_select_min,
                            'at_select_max' => $optionGroup->at_select_max,
                            'product_option_products' => $optionGroup->productOptionProducts->filter(function ($query) use ($listProduct) {
                                return in_array($query->no_option, collect($listProduct['option'])->pluck('no_option')->all()) == true;
                            })->map(function ($option) use ($listProduct, $stock, $optionGroup){
                                $cntProduct = data_get(data_get($stock->firstWhere('no_product', $listProduct['no_product']), 'option')?->firstWhere('no_barcode', $option->no_barcode_opt), 'cnt_product', 0);
                                $ea = data_get(collect($listProduct['option'])->firstWhere('no_option', $option->no_option), 'ea', 1);
                                return [
                                    'no_group' => $optionGroup->no_group,
                                    'no_option' => $option->no_option,
                                    'nm_option' => $option->nm_product_opt,
                                    'at_add_price' => $option->at_price_opt,
                                    'yn_check_stock' => 'Y',
                                    'cnt_product' => $cntProduct,
                                    'yn_soldout' => $cntProduct ? 'N' : 'Y',
                                    'ea' => $ea,
                                ];
                            })->values()
                        ];
                    })->values(),
                    'two_plus_one_option' => match ($product->cd_discount_sale == DiscountSale::TWO_PLUS_ONE->value) {
                        true => match (data_get($listProduct, 'discount_type') == 'DOUBLE') {
                            true => [
                                'nm_product' => sprintf('%s %s', $product->nm_product, Code::conf('two_plus_one.double')),
                                'discount_type' => 'DOUBLE',
                                'at_price' => $product->at_price * 2
                            ],
                            default => [
                                'nm_product' => sprintf('%s %s', $product->nm_product, Code::conf('two_plus_one.single')),
                                'discount_type' => 'SINGLE',
                                'at_price' => $product->at_price
                            ],
                        },
                        default => null,
                    }
                ];
            })->first();
        })->filter();

    }
}

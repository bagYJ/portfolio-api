<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Pickup;
use App\Enums\SearchBizKindDetail;
use App\Exceptions\SpcException;
use App\Models\PartnerCategory;
use App\Models\Product;
use App\Models\ProductIgnore;
use App\Models\ProductIgnoreHistory;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\ProductOptionIgnore;
use App\Models\ProductOptionIgnoreHistory;
use App\Models\SearchLog;
use App\Models\Shop;
use App\Utils\Code;
use App\Utils\Common;
use App\Utils\Spc;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProductService extends Service
{

    /**
     * 브랜드 카테고리 조회
     * @param int $noPartner
     * @return Collection
     */
    public static function getCategory(int $noPartner): Collection
    {
        return PartnerCategory::select([
            DB::raw("no_partner_category AS no_category"),
            'nm_category',
        ])->where([
            ['no_partner', '=', $noPartner],
            ['no_partner_category', '!=', "{$noPartner}9999"],
        ])->orderBy('ct_order')->get();
    }

    /**
     * 상품 리스트 조회
     * @param int $noPartner
     * @param int $noShop
     * @param int|null $noCategory
     * @param string|null $type
     * @param string|null $cdBizKind
     * @return array
     */
    public static function gets(Shop $shop, int $noCategory = null, string $type = null): array
    {
        $where = [
            ['ds_status', '=', 'Y'],
        ];

        if ($noCategory) {
            $where['no_partner_category'] = $noCategory;
        }

        $where[match ($type == Pickup::SHOP->name) {
            true => 'yn_shop_pickup',
            default => 'yn_car_pickup'
        }] = 'Y';

        $products = $shop->productIgnoreExcept()
            ->where($where)
            ->with(['productOptionGroups.productOptions', 'partner'])
            ->orderBy('at_view_order')->orderBy('nm_product')->get();

        $spcStock = match (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail)) {
            SearchBizKindDetail::SPC => (function () use ($shop, $noCategory) {
                return collect(Spc::stock($shop->partner->cd_spc_brand, $shop->cd_spc_store, $noCategory))
                    ->filter(fn(mixed $product) => !empty($product) && is_array($product))
                    ->mapWithKeys(function ($product) {
                        return [$product['code'] => $product['qty']];
                    });
            })(),
            default => null
        };

        return [
            'product_count' => $products->count(),
            'ds_bi'         => $products->first()?->partner->ds_bi,
            'image_path'    => Code::conf('image_path'),
            'products'      => $products->filter(function ($collect) use ($spcStock) {
                return !$collect->cd_spc
                    || $collect->yn_check_stock == 'N'
                    || !isset($spcStock[$collect->cd_spc])
                    || (isset($spcStock[$collect->cd_spc]) && $spcStock[$collect->cd_spc] > 0);
            })->map(function ($collect) use ($spcStock, $shop) {
                $optionGroups = collect($collect->option_group)->filter(
                    function ($optiongroupNo) {
                        return $optiongroupNo > 0;
                    }
                );

                $data = [
                    'no_product'          => $collect->no_product,
                    'ds_option_sel'       => $collect->ds_option_sel,
                    'nm_product'          => $collect->nm_product,
                    'ds_content'          => $collect->ds_content,
                    'no_partner_category' => $collect->no_partner_category,
                    'at_price_before'     => $collect->at_price_before,
                    'at_price'            => $collect->at_price,
                    'ds_image_path'       => $collect->ds_image_path ? Common::getImagePath($collect->ds_image_path) : null,
                    'yn_new'              => $collect->yn_new,
                    'yn_vote'             => $collect->yn_vote,
                    'at_view_order'       => $collect->at_view_order,
                    'at_ratio'            => Common::getSaleRatio($collect->at_price_before, $collect->at_price),
                    'cnt_product'         => isset($spcStock[$collect->cd_spc]) && $collect->yn_check_stock == 'Y' ? $spcStock[$collect->cd_spc] : null,
                    'option_groups' => $optionGroups->map(function ($optiongroupNo) use ($collect, $shop) {
                        $optionGroup = $collect->productOptionGroups->firstWhere('no_group', $optiongroupNo);
                        return [
                            'no_group'          => $optionGroup?->no_group,
                            'nm_group'          => $optionGroup?->nm_group,
                            'min_option_select' => $optionGroup?->min_option_select ?? 1,
                            'max_option_select' => $optionGroup?->max_option_select ?? 1,
                            'option_type'       => match ($optionGroup?->min_option_select <= 1 && $optionGroup?->max_option_select <= 1) {
                                true => 'radio',
                                default => match (!data_get($optionGroup, 'option_type')) {
                                    true => 'checkbox',
                                    default => data_get($optionGroup, 'option_type')
                                }
                            },
                            'yn_cup_deposit' => $optionGroup?->productOptions->filter(function ($option) {
                                return $option->yn_cup_deposit == 'Y';
                            })->count() > 0 && empty($shop->at_cup_deposit) == false && $shop->at_cup_deposit > 0 ? 'Y' : 'N',
                            'product_options'   => $optionGroup?->productOptions->map(function ($option) use ($shop){
                                return [
                                    'no_option'    => $option->no_option,
                                    'nm_option'    => $option->nm_option,
                                    'yn_cup_deposit' => $option->yn_cup_deposit == 'Y' && empty($shop->at_cup_deposit) == false && $shop->at_cup_deposit > 0 ? 'Y' :'N',
                                    'at_add_price' => $option->at_add_price,
                                ];
                            })->values()
                        ];
                    }),
                ];

                $data['yn_cup_deposit'] = match (collect($data['option_groups'])->count() > 0) {
                    true => collect($data['option_groups'])->pluck('product_options')->flatten(1)->filter(function ($query) {
                        return empty($query) == false && $query['yn_cup_deposit'] == 'Y';
                    })->count() ? 'Y' : 'N',
                    default => 'N'
                };

                return $data;
            })->values()
        ];
    }

    /**
     * @param $noShop
     * @param $searchWord
     * @param $noUser
     * @return mixed
     */
    public static function createSearchLog($noShop, $searchWord, $noUser = null)
    {
        return SearchLog::create([
            'no_shop'     => $noShop,
            'no_user'     => $noUser,
            'search_word' => $searchWord,
            'ref_week'    => DB::raw("DAYOFWEEK(NOW())"),
        ]);
    }

    public static function getSpcStock(Shop $shop, Collection $products): ?Collection
    {
        $spcStock = new Collection();
        if (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail) == SearchBizKindDetail::SPC) {
            $products->map(function ($product) use ($shop, $spcStock){
                if (count($product->productIgnore->where('no_shop', $shop->no_shop)) == 0
                    && ($product->yn_check_stock == 'Y' || $product->productOptionGroups->whereIn('no_group', $product->option_group)->count() > 0)) {
                    collect(Spc::productStock($shop->partner->cd_spc_brand, $shop->cd_spc_store, $product))
                        ->map(function($product) use ($spcStock) {
                            $spcStock->put($product['code'], [
                                'qty'    => $product['qty'],
                                'option' => collect(data_get($product, 'options'))->mapWithKeys(function ($option) {
                                    return [$option['code'] => [
                                        'qty' => $option['qty']
                                    ]];
                                })
                            ]);
                        });
                }
            });
        }
        return $spcStock;
    }

    /**
     * @param int $noShop
     * @param array $cartProduct
     * @return Collection|null
     */
    public static function getCartProduct(int $noShop, array $cartProduct): ?Collection
    {
        $shop = ShopService::shop($noShop)->first();
        if (empty($shop)) return null;
        $listProducts = collect($cartProduct['list_product']);
        $products = $shop->load('productIgnoreExcept.productOptionGroups.productOptions.productOptionIgnore')->productIgnoreExcept()->whereIn('product.no_product', $listProducts->pluck('no_product')->all())->get();
        $spcStock = self::getSpcStock($shop, $products);

        return $listProducts->map(function (array $listProduct) use ($products, $spcStock, $shop, $cartProduct) {
            return $products->where('no_product', $listProduct['no_product'])->filter()->map(function (Product $product) use ($listProduct, $shop, $spcStock, $cartProduct) {
                $data = [
                    'no_shop' => $shop->no_shop,
                    'biz_kind' => data_get($cartProduct, 'biz_kind'),
                    'nm_shop' => sprintf('%s %s', $shop->partner->nm_partner, $shop->nm_shop),
                    'pickup_type' => data_get($cartProduct, 'pickup_type'),
                    'no_product' => $product->no_product,
                    'nm_product' => $product->nm_product,
                    'at_price' => $listProduct['at_price'],
                    'ea' => $listProduct['ea'],
                    'cd_discount_sale' => null,
                    'current_price' => $product->at_price,
                    'yn_soldout' => count($product->productIgnore->where('no_shop', $shop->no_shop)) == 0 && $product->ds_status == 'Y'
                    && ($product->yn_check_stock == 'N' ||
                        (isset($spcStock[$product->cd_spc]) && $product->yn_check_stock == 'Y' && $spcStock[$product->cd_spc]['qty'] >= $listProduct['ea'])
                        || (SearchBizKindDetail::getBizKindDetail($product->partner->cd_biz_kind_detail) == SearchBizKindDetail::SPC)
                    ) ? 'N' : 'Y',
                    'option_groups' => $product->productOptionGroups->whereIn('no_group', $product->option_group)->sortBy(
                        fn(ProductOptionGroup $group) => array_search($group->no_group, $product->option_group)
                    )->filter(
                        fn(ProductOptionGroup $group) => (empty($shop->at_cup_deposit) == false && $shop->at_cup_deposit > 0
                                && $group->yn_cup_deposit == 'Y') || $group->yn_cup_deposit == 'N'
                    )->filter(function ($optionGroup) use ($shop) {
                        return (empty($shop->at_cup_deposit) == false && $shop->at_cup_deposit > 0 && $optionGroup->yn_cup_deposit == 'Y')
                            || $optionGroup->yn_cup_deposit == 'N';
                    })->map(function ($optionGroup) use ($product, $spcStock, $listProduct, $shop) {
                        $productOptions = $optionGroup->productOptions->whereIn('no_option', collect($listProduct['option'])->pluck('no_option')->all());
                        return [
                            'no_group' => $optionGroup->no_group,
                            'nm_group' => $optionGroup->nm_group,
                            'min_option_select' => $optionGroup->min_option_select ?? 1,
                            'max_option_select' => $optionGroup->max_option_select ?? 1,
                            'option_type' => match ($optionGroup->min_option_select <= 1 && $optionGroup->max_option_select <= 1) {
                                true => 'radio',
                                default => match (!data_get($optionGroup, 'option_type')) {
                                    true => 'checkbox',
                                    default => data_get($optionGroup, 'option_type')
                                }
                            },
                            'yn_cup_deposit' => $productOptions->filter(function ($option) {
                                return $option->yn_cup_deposit == 'Y';
                            })->count() > 0 && !empty($shop->at_cup_deposit) && $shop->at_cup_deposit > 0 ? 'Y' : 'N',
                            'product_options' => $productOptions->map(function ($option) use ($product, $spcStock, $shop, $listProduct, $optionGroup) {
                                $cntProduct = $option['yn_check_stock'] == 'Y' && isset($spcStock[$product->cd_spc]['option'][$option->cd_spc]) ? $spcStock[$product->cd_spc]['option'][$option->cd_spc]['qty'] : null;
                                $ea = data_get(collect($listProduct['option'])->firstWhere('no_option', $option->no_option), 'ea', 1);
                                return [
                                    'no_group' => $optionGroup->no_group,
                                    'no_option' => $option->no_option,
                                    'nm_option' => $option->nm_option,
                                    'nm_group' => $optionGroup->nm_group,
                                    'at_add_price' => $option->yn_cup_deposit == 'Y' && !empty($shop->at_cup_deposit) && $shop->at_cup_deposit > 0 ? $shop->at_cup_deposit : $option->at_add_price,
                                    'yn_check_stock' => $option->yn_check_stock,
                                    'cnt_product' => $cntProduct,
                                    'yn_soldout' => count($option->productOptionIgnore->where('no_shop', $shop->no_shop)) == 0
                                    && ($cntProduct == null || $spcStock[$product->cd_spc]['option'][$option->cd_spc]['qty'] > $ea)
                                        ? 'N' : 'Y',
                                    'yn_cup_deposit' => $option->yn_cup_deposit == 'Y' && !empty($shop->at_cup_deposit) && $shop->at_cup_deposit > 0 ? 'Y' : 'N',
                                    'ea' => $ea,
                                ];
                            })->values()
                        ];
                    })->values()
                ];

                $data['yn_soldout'] = match (collect($data['option_groups'])->count() > 0) {
                    true => collect($data['option_groups'])->pluck('product_options')->flatten(1)->filter(function ($query) {
                        return $query['yn_soldout'] == 'Y';
                    })->count() ? 'Y' : 'N',
                    default => $data['yn_soldout']
                };

                $data['yn_cup_deposit'] = match (collect($data['option_groups'])->count() > 0) {
                    true => collect($data['option_groups'])->pluck('product_options')->flatten(1)->filter(function ($query) {
                        return $query['yn_cup_deposit'] == 'Y';
                    })->count() ? 'Y' : 'N',
                    default => 'N'
                };

                return $data;
            })->first();
        })->filter();
    }

    /**
     * @param array $parameter
     * @param array|null $whereIn
     * @param int|null $excludeShop
     * @return Collection
     */
    public static function getProduct(array $parameter, int $noShop, ?array $whereIn = [], ?int $excludeShop = null): Collection
    {
        $shop = ShopService::getShop($noShop);
        $products = $shop->productIgnoreExcept()->where(collect($parameter)->mapWithKeys(function (mixed $value, string $key) {
            return [sprintf('%s.%s', 'product', $key) => $value];
        })->toArray())->when(!empty($whereIn), function (Builder $query) use ($whereIn) {
            foreach ($whereIn as $key => $value) {
                $query->whereIn(sprintf('%s.%s', 'product', $key), $value);
            }
        })->with([
            'productOptionGroups.productOptions' => function ($query) use ($excludeShop) {
                if ($excludeShop) {
                    $query->whereNotIn('no_option', self::getProductOptionIgnore($excludeShop));
                }
            },
            'partner'
        ])->get();

        $spcStock = match (empty($excludeShop) == false) {
            true => self::getSpcStock($shop, $products),
            default => new Collection()
        };

        return $products->map(function ($collect) use ($spcStock, $shop) {
            $optionGroup = $collect->option_group;
            $product = [
                'no_product' => $collect->no_product,
                'biz_kind_detail' => SearchBizKindDetail::getBizKindDetail($collect->partner->cd_biz_kind_detail)->name,
                'ds_option_sel' => $collect->ds_option_sel,
                'nm_product' => $collect->nm_product,
                'ds_content' => $collect->ds_content,
                'no_partner_category' => $collect->no_partner_category,
                'at_price_before' => $collect->at_price_before,
                'at_price' => $collect->at_price,
                'ds_image_path' => $collect->ds_image_path ? Common::getImagePath($collect->ds_image_path) : null,
                'yn_new' => $collect->yn_new,
                'yn_vote' => $collect->yn_vote,
                'at_view_order' => $collect->at_view_order,
                'at_ratio' => Common::getSaleRatio($collect->at_price_before, $collect->at_price),
                'cnt_product' => isset($spcStock[$collect->cd_spc]) && $collect->yn_check_stock == 'Y' ? $spcStock[$collect->cd_spc]['qty'] : null,
                'policy_uri' => Code::conf('policy_uri'),
                'option_groups' => $collect->productOptionGroups->whereIn('no_group', $optionGroup)->sortBy(function ($option) use ($optionGroup) {
                    return array_search($option->no_group, $optionGroup);
                })->filter(function ($optionGroup) use ($shop) {
                    return (empty($shop->at_cup_deposit) == false && $shop->at_cup_deposit > 0 && $optionGroup->yn_cup_deposit == 'Y')
                        || $optionGroup->yn_cup_deposit == 'N';
                })->map(function ($optionGroup) use ($collect, $spcStock, $shop) {
                    $minOptionSelect = data_get($optionGroup, 'min_option_select') !== null ? data_get($optionGroup, 'min_option_select'): 1;
                    $maxOptionSelect = data_get($optionGroup, 'max_option_select') !== null ? data_get($optionGroup, 'max_option_select'): 1;
                    return [
                        'no_group' => $optionGroup->no_group,
                        'nm_group' => $optionGroup->nm_group,
                        'min_option_select' => $minOptionSelect,
                        'max_option_select' => $maxOptionSelect,
                        'option_type' => match ($minOptionSelect == 1 && $maxOptionSelect <= 1) {
                            true => 'radio',
                            default => match (!data_get($optionGroup, 'option_type')) {
                                true => 'checkbox',
                                default => data_get($optionGroup, 'option_type')
                            }
                        },
                        'yn_cup_deposit' => $optionGroup?->yn_cup_deposit,
                        'product_options' => $optionGroup->productOptions->map(function ($option) use ($collect, $spcStock, $shop) {
                            return [
                                'no_option' => $option->no_option,
                                'nm_option' => $option->nm_option,
                                'at_add_price' => $option->yn_cup_deposit == 'Y' ? $shop->at_cup_deposit : $option->at_add_price,
                                'yn_check_stock' => $option->yn_check_stock,
                                'yn_cup_deposit' => $option->yn_cup_deposit,
                                'cnt_product' => $option['yn_check_stock'] == 'Y' && isset($spcStock[$collect->cd_spc]['option'][$option->cd_spc])? $spcStock[$collect->cd_spc]['option'][$option->cd_spc]['qty'] : null,
                            ];
                        })->values()
                    ];
                })->values()
            ];

            $product['yn_cup_deposit'] =  match (collect($product['option_groups'])->count() > 0) {
                true => collect($product['option_groups'])->filter(function ($query) {
                    return $query['yn_cup_deposit'] == 'Y';
                })->count() ? 'Y' : 'N',
                default => 'N'
            };

            return $product;
        })->values();
    }

    /**
     * @param int $noShop
     * @return Collection
     */
    public static function getProductIgnore(int $noShop): Collection
    {
        return ProductIgnore::where('no_shop', $noShop)->pluck('no_product');
    }

    /**
     * @param int $noShop
     * @return Collection
     */
    public static function getProductOptionIgnore(int $noShop): Collection
    {
        return ProductOptionIgnore::where('no_shop', $noShop)->pluck('no_option');
    }

    public static function setProductIgnore(Shop $shop, Collection $request)
    {
        $noProducts = Product::whereIn('cd_spc', $request['menuCodes'])->where('no_partner', $shop->no_partner)->get()->pluck('no_product')->all();
        $noOptions = ProductOption::whereIn('cd_spc', $request['menuCodes'])->where('no_partner', $shop->no_partner)->get()->pluck('no_option')->all();
        match ($request['soldoutType']) {
            'soldout' => self::createProductIgnore($shop, $noProducts, $noOptions, $request['resetDate']),
            'instock' => self::removeProductIgnore($shop, $noProducts, $noOptions),
            default => throw new SpcException(Code::message('E006')),
        };
    }

    public static function createProductIgnore(Shop $shop, array $noProducts, array $noOptions, string $resetDate)
    {
        $productBody = [];
        if (count($noProducts)) {
            foreach ($noProducts AS $noProduct) {
                $productBody[] = [
                    'no_shop'    => $shop->no_shop,
                    'no_product' => $noProduct,
                    'id_start' => 'SYSTEM',
                    'dt_start' => $resetDate,
                    'id_stop'    => 'SYSTEM',
                    'dt_stop'    => now(),
                    'dt_reg'     => now(),
                ];
            }
        }

        $optionBody = [];
        if (count($noOptions)) {
            foreach ($noOptions AS $noOption) {
                $optionBody[] = [
                    'no_shop'    => $shop->no_shop,
                    'no_option' => $noOption,
                    'id_start' => 'SYSTEM',
                    'dt_start' => $resetDate,
                    'id_stop'    => 'SYSTEM',
                    'dt_stop'    => now(),
                    'dt_reg'     => now(),
                ];
            }
        }

        try {
            DB::transaction(function () use ($productBody, $optionBody) {
                if (count($productBody)) {
                    ProductIgnore::insertOrIgnore($productBody);
                    ProductIgnoreHistory::insertOrIgnore($productBody);
                }
                if (count($optionBody)) {
                    ProductOptionIgnore::insertOrIgnore($optionBody);
                    ProductOptionIgnoreHistory::insertOrIgnore($optionBody);
                }
            });
        } catch (Throwable $t) {
            Log::channel('spc')->error('product soldout status change error:: ', [$t->getMessage()]);
            throw new SpcException(Code::message('E003'));
        }
    }

    public static function removeProductIgnore(Shop $shop, array $noProducts, array $noOptions)
    {
        $productBody = [];
        foreach ($noProducts AS $noProduct) {
            $productBody[] = [
                'no_shop'    => $shop->no_shop,
                'no_product' => $noProduct,
                'id_start'   => 'SYSTEM',
                'dt_start'   => now(),
                'dt_reg'     => now(),
            ];
        }

        $optionBody = [];
        foreach ($noOptions AS $noOption) {
            $optionBody[] = [
                'no_shop'    => $shop->no_shop,
                'no_option' => $noOption,
                'id_start'   => 'SYSTEM',
                'dt_start'   => now(),
                'dt_reg'     => now(),
            ];
        }

        try {
            DB::transaction(function () use ($shop, $noProducts, $productBody, $noOptions, $optionBody) {
                if (count($productBody)) {
                    ProductIgnore::whereIn('no_product', $noProducts)->where('no_shop', $shop->no_shop)->delete();
                    ProductIgnoreHistory::insertOrIgnore($productBody);
                }

                if (count($optionBody)) {
                    ProductOptionIgnore::whereIn('no_option', $noOptions)->where('no_shop', $shop->no_shop)->delete();
                    ProductOptionIgnoreHistory::insertOrIgnore($optionBody);
                }
            });
        } catch (Throwable $t) {
            Log::channel('spc')->error('product instock status change error:: ', [$t->getMessage()]);
            throw new SpcException(Code::message('E003'));
        }
    }

    public static function getSpcStockProduct(?array $products = [], ?array $category = []): Collection
    {
        return Product::when(!empty($products), fn(Builder $builder) => $builder->whereIn('no_product', $products)
        )->when(!empty($category), fn(Builder $builder) => $builder->whereIn('no_partner_category', $category)
        )->where('yn_check_stock', 'Y')->get();
    }
}

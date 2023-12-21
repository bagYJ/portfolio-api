<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AppType;
use App\Enums\EnumYN;
use App\Models\Partner;
use App\Models\Product;
use App\Models\ProductIgnore;
use App\Models\Shop;
use App\Utils\Common;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SearchService extends Service
{
    /**
     * @param float $distance
     * @param array $positions
     * @param int|null $noPartner
     * @param array|null $cdBizKind
     * @param array|null $cdBizKindDetail
     * @param string|null $cdThirdParty
     * @return Collection
     */
    public function getRadiusSingleRound(
        float $distance,
        array $positions,
        ?int $noPartner = null,
        ?array $cdBizKind = [],
        ?array $cdBizKindDetail = [],
        ?string $cdThirdParty = null
    ): Collection {
        return Shop::with([
            'shopOilPrice',
            'product' => function ($query) {
                $query->select('product.*');
                $query->leftJoin('product_ignore', 'product_ignore.no_product', 'product.no_product');
                $query->whereNull('product_ignore.no_product');
            }
        ])->leftJoin('partner AS p', 'shop.no_partner', '=', 'p.no_partner')
            ->leftJoin('shop_detail AS sd', 'shop.no_shop', '=', 'sd.no_shop')
            ->leftJoin('shop_opt_time AS sot', function ($query) {
                $query->on('shop.no_shop', '=', 'sot.no_shop')
                    ->on('sot.nt_weekday', '=', DB::raw('WEEKDAY(NOW())'))
                    ->whereBetween(
                        DB::raw(now()->format('Hi')),
                        [DB::raw('sot.ds_open_time'), DB::raw('sot.ds_close_time')]
                    );
            })->leftJoin('shop_holiday AS sh', function ($query) {
                $query->on('shop.no_shop', '=', 'sh.no_shop')
                    ->on(
                        'sh.no',
                        '=',
                        DB::raw(
                            sprintf(
                                '
                    (SELECT
                        no
                    FROM
                        shop_holiday AS sh
                    WHERE
                        sh.no_shop = shop.no_shop
                        AND nt_weekday = WEEKDAY(NOW())
                        AND (
                            cd_holiday = ?
                            OR (cd_holiday = ? AND WEEKOFYEAR(NOW()) - WEEKOFYEAR(%1$s) = 0)
                            OR (cd_holiday = ? AND WEEKOFYEAR(NOW()) - WEEKOFYEAR(%1$s) = 1)
                            OR (cd_holiday = ? AND WEEKOFYEAR(NOW()) - WEEKOFYEAR(%1$s) = 2)
                            OR (cd_holiday = ? AND WEEKOFYEAR(NOW()) - WEEKOFYEAR(%1$s) = 3))
                            OR (cd_holiday = ? AND NOW() BETWEEN dt_imsi_start AND dt_imsi_end
                        ) LIMIT 1)',
                                now()->startOfMonth()->format('Y-m-d')
                            )
                        )
                    )->setBindings([
                        '211200',
                        '211300',
                        '211400',
                        '211500',
                        '211600',
                        '211900'
                    ]);
            })->where([
                'shop.yn_display_map' => EnumYN::Y->name,
                'sd.cd_contract_status' => '207100'
            ])->where(
                function ($query) use ($distance, $positions, $noPartner, $cdBizKind, $cdBizKindDetail, $cdThirdParty) {
                    foreach ($positions as $position) {
                        $query->orWhere(
                            DB::raw(
                                sprintf(
                                    '(6371 * ACOS(COS(RADIANS(%1$s)) * COS(RADIANS(at_lat)) * COS(RADIANS(at_lng) -RADIANS(%2$s)) + SIN(RADIANS(%1$s)) * SIN(RADIANS(at_lat))))',
                                    $position['x'],
                                    $position['y']
                                )
                            ),
                            '<=',
                            $distance
                        );
                    }
                    if (empty($noPartner) === false) {
                        $query->where('p.no_partner', $noPartner);
                    }
                    if (empty($cdBizKind) === false) {
                        $query->whereIn('p.cd_biz_kind', $cdBizKind);
                    }
                    if (empty($cdBizKindDetail) === false) {
                        $query->whereIn('p.cd_biz_kind_detail', $cdBizKindDetail);
                    }
                    if (empty($cdThirdParty) === false) {
                        $query->where('shop.list_cd_third_party', 'REGEXP', $cdThirdParty);
                    }
                }
            )
            ->whereRaw(
                'IF(? BETWEEN sot.ds_break_start_time AND sot.ds_break_end_time, true, false) = false',
                [now()->format('Hi')]
            )
            ->whereRaw(
                'IF(? BETWEEN sot.ds_break_start_time2 AND sot.ds_break_end_time2, true, false) = false',
                [now()->format('Hi')]
            )
            ->whereRaw(
                'IF(sh.nt_weekday = WEEKDAY(NOW()) OR (NOW() BETWEEN sh.dt_imsi_start AND sh.dt_imsi_start), true, false) = false'
            )
            ->select([
                '*',
                DB::raw("CONCAT(p.nm_partner, ' ', shop.nm_shop) AS nm_shop"),
                DB::raw('shop.no_shop AS no_shop'),
                DB::raw(
                    sprintf(
                        '(6371 * ACOS(COS(RADIANS(%1$s)) * COS(RADIANS(at_lat)) * COS(RADIANS(at_lng) -RADIANS(%2$s)) + SIN(RADIANS(%1$s)) * SIN(RADIANS(at_lat)))) as distance',
                        $positions[0]['x'],
                        $positions[0]['y']
                    )
                )
            ])->orderBy('distance')->get();
    }

    /**
     * @param $noShop
     * @return Model|Builder|object|null
     */
    public static function getPosError($noShop)
    {
        $sub = Shop::select([
            'no_shop_ark',
            'yn_control',
            'cd_ark_status',
            'shop.no_shop',
            DB::raw("(TIMESTAMPDIFF(MINUTE, ark.dt_upt, NOW())) AS alert_time_diff"),
            'yn_control_ark'
        ])->join('ark', 'shop.no_shop', '=', 'ark.no_shop')
            ->leftJoin('shop_opt_time', function ($q) {
                $q->on('shop.no_shop', 'shop_opt_time.no_shop');
                $q->where('shop_opt_time.nt_weekday', DB::raw('WEEKDAY(CURDATE())'));
            })->leftJoin('shop_holiday', function ($q) {
                $q->on('shop_holiday.no_shop', 'shop.no_shop');
                $q->whereRaw(
                    "( (cd_holiday = 211900 AND dt_imsi_start <= NOW() AND dt_imsi_end >= NOW() )
							OR ( cd_holiday = 211200 AND shop_holiday.nt_weekday = WEEKDAY(CURDATE()))
							OR ( cd_holiday = 211300 AND shop_holiday.nt_weekday = WEEKDAY(CURDATE()) AND CAST(DAYOFMONTH(NOW())/7 AS UNSIGNED INTEGER)=0 ) -- 매주첫째주
							OR ( cd_holiday = 211400 AND shop_holiday.nt_weekday = WEEKDAY(CURDATE()) AND CAST(DAYOFMONTH(NOW())/7 AS UNSIGNED INTEGER)=1 ) -- 매주두째주
							OR ( cd_holiday = 211500 AND shop_holiday.nt_weekday = WEEKDAY(CURDATE()) AND CAST(DAYOFMONTH(NOW())/7 AS UNSIGNED INTEGER)=2 ) -- 매주세째주
							OR ( cd_holiday = 211600 AND shop_holiday.nt_weekday = WEEKDAY(CURDATE()) AND CAST(DAYOFMONTH(NOW())/7 AS UNSIGNED INTEGER)=3 ) -- 매주네째주
							)"
                );
            })->whereRaw(
                "((ark.cd_ark_status = '304900')
            OR ( ark.cd_ark_status = '304200' AND ark.no_shop_ark < '99' AND ark.dt_upt < DATE_ADD(NOW(), INTERVAL -35 MINUTE)))"
            )->whereRaw(
                "shop.ds_status = 'Y' AND DATE_FORMAT(NOW(), '%H%i') BETWEEN shop_opt_time.ds_open_time AND shop_opt_time.ds_close_time"
            )
            ->groupBy([
                'ark.no_shop_ark',
                'ark.yn_control',
                'shop.no_shop',
                'ark.dt_upt',
                'ark.yn_control_ark'
            ]);

        return DB::table(DB::raw("({$sub->toSql()}) as sub"))
            ->mergeBindings($sub->getQuery())->where([
                ['no_shop', '=', $noShop],
                ['no_shop_ark', '>', 30],
                ['alert_time_diff', '>', 4]
            ])->orderBy('no_shop_ark')->first();
    }

    /**
     * @param float $radius
     * @param array $position
     * @param int|null $limit
     * @return Collection
     */
    public function homeProductList(float $radius, array $position, ?int $limit = 5): Collection
    {
        $now = now()->format('Hi');

        return Shop::with([
            'shopOptTime' => function ($query) use ($now) {
                $query->where('nt_weekday', DB::raw('WEEKDAY(NOW())'))->whereBetween(DB::raw($now), [DB::raw('ds_open_time'), DB::raw('ds_close_time')])
                    ->whereRaw('IF(? BETWEEN ds_break_start_time AND ds_break_end_time, true, false) = false', [$now])
                    ->whereRaw('IF(? BETWEEN ds_break_start_time2 AND ds_break_end_time2, true, false) = false', [$now]);
            },
            'product' => function ($query) {
                $query->where('ds_image_path', '<>', '');
            },
            'product.productIgnore' => function ($query) {
                $query->whereNull('no_product');
            }
        ])->join('partner AS p', 'p.no_partner', '=', 'shop.no_partner')
            ->leftJoin('shop_holiday AS sh', function ($query) {
                $query->on('shop.no_shop', '=', 'sh.no_shop')
                    ->on('sh.no', '=', DB::raw(
                        sprintf('
                    (SELECT
                        no
                    FROM
                        shop_holiday AS sh
                    WHERE
                        sh.no_shop = shop.no_shop
                        AND nt_weekday = WEEKDAY(NOW())
                        AND (
                            cd_holiday = ?
                            OR (cd_holiday = ? AND WEEKOFYEAR(%1$s) - WEEKOFYEAR(%2$s) = 0)
                            OR (cd_holiday = ? AND WEEKOFYEAR(%1$s) - WEEKOFYEAR(%2$s) = 1)
                            OR (cd_holiday = ? AND WEEKOFYEAR(%1$s) - WEEKOFYEAR(%2$s) = 2)
                            OR (cd_holiday = ? AND WEEKOFYEAR(%1$s) - WEEKOFYEAR(%2$s) = 3)
                            OR (cd_holiday = ? AND NOW() BETWEEN dt_imsi_start AND dt_imsi_end)
                        ) LIMIT 1)
                        ', now()->format('Y-m-d'), now()->startOfMonth()->format('Y-m-d'))
                    ))->setBindings([
                        '211200',
                        '211300',
                        '211400',
                        '211500',
                        '211600',
                        '211900'
                    ]);
            })->whereHas('product', function ($query) {
                $query->where('ds_status', 'Y')->where('ds_image_path', '<>', '');
            })->where('shop.at_lat', '>', 0)
            ->where('shop.at_lng', '>', 0)
            ->where('shop.ds_status', 'Y')
            ->whereIn('p.cd_biz_kind', ['201100', '201200', '201400'])
            ->whereNull('sh.no')
            ->select([
                DB::raw("CONCAT(p.nm_partner, ' ',  shop.nm_shop) AS nm_shop"),
                'shop.no_shop',
                'shop.no_partner',
                DB::raw(sprintf(
                        '(6371 * ACOS(COS(RADIANS(%1$s)) * COS(RADIANS(shop.at_lat)) * COS(RADIANS(shop.at_lng) -RADIANS(%2$s)) + SIN(RADIANS(%1$s)) * SIN(RADIANS(shop.at_lat)))) AS distance', $position['x'], $position['y']
                    )
                )
            ])
            ->orderBy('distance')
            ->limit($limit)->get()->map(function ($shop) {
                $product = $shop->product->shuffle()->first();

                $shop->at_ratio = Common::getSaleRatio($shop->at_price_before, $shop->at_price);
                $shop->is_car_pickup = $product?->yn_car_pickup == 'Y';
                $shop->is_shop_pickup = $product?->yn_shop_pickup == 'Y';
                $shop->no_product = $product->no_product;
                $shop->nm_product = $product->nm_product;
                $shop->at_price_before = $product->at_price_before;
                $shop->at_price = $product->at_price;
                $shop->ds_image_path = $product->ds_image_path;
                $shop->ds_recommend_start_time = $product->ds_recommend_start_time;
                $shop->ds_recommend_end_time = $product->ds_recommend_end_time;

                return $shop;
            })->makeHidden(['shopOptTime', 'product']);
    }

    /**
     * @param float $radius
     * @param array $position
     * @param int|null $limit
     * @param int|null $productLimit
     * @return Collection
     */
    public function homeShopList(float $radius, array $position, ?int $limit = 5, ?int $productLimit = 5): Collection
    {
        return Shop::whereExists(function (Builder $query) {
            $query->select(DB::raw(1))
                ->from((new Product())->getTable())
                ->whereColumn('shop.no_partner', 'product.no_partner')
                ->where('ds_image_path', '<>', '')
                ->where('ds_status', 'Y')
                ->whereExists(function (Builder $query) {
                    $query->select(DB::raw(1))
                        ->from((new Product())->getTable())
                        ->leftJoin((new ProductIgnore())->getTable(), 'product.no_partner', 'shop.no_partner')
                        ->where('product_ignore.no_shop', '!=', 'shop.no_shop')
                        ->whereNotNull('product.no_product');
                });
            match (getAppType()) {
                AppType::AVN => $query->where('no_partner_category', 'LIKE', '%9999'),
                default => $query->where('no_partner_category', 'NOT LIKE', '%9999')
            };
        })->join('partner AS p', 'shop.no_partner', '=', 'p.no_partner')
            ->join('shop_detail AS d', 'shop.no_shop', '=', 'd.no_shop')
            ->leftJoin('shop_opt_time AS sot', function ($query) {
                $query->on('shop.no_shop', '=', 'sot.no_shop')
                    ->on('sot.nt_weekday', '=', DB::raw('WEEKDAY(NOW())'))
                    ->whereBetween(
                        DB::raw(now()->format('Hi')),
                        [DB::raw('sot.ds_open_time'), DB::raw('sot.ds_close_time')]
                    );
            })->leftJoin('shop_holiday AS sh', function ($query) {
                $query->on('shop.no_shop', '=', 'sh.no_shop')
                    ->on(
                        'sh.no',
                        '=',
                        DB::raw(
                            sprintf(
                                '
                    (SELECT
                        no
                    FROM
                        shop_holiday AS sh
                    WHERE
                        sh.no_shop = shop.no_shop
                        AND nt_weekday = WEEKDAY(NOW())
                        AND (
                            cd_holiday = ?
                            OR (cd_holiday = ? AND WEEKOFYEAR(%1$s) - WEEKOFYEAR(%2$s) = 0)
                            OR (cd_holiday = ? AND WEEKOFYEAR(%1$s) - WEEKOFYEAR(%2$s) = 1)
                            OR (cd_holiday = ? AND WEEKOFYEAR(%1$s) - WEEKOFYEAR(%2$s) = 2)
                            OR (cd_holiday = ? AND WEEKOFYEAR(%1$s) - WEEKOFYEAR(%2$s) = 3)
                            OR (cd_holiday = ? AND NOW() BETWEEN dt_imsi_start AND dt_imsi_end)
                        ) LIMIT 1)',
                                now()->format('Y-m-d'),
                                now()->startOfMonth()->format('Y-m-d')
                            )
                        )
                    )->setBindings([
                        '211200',
                        '211300',
                        '211400',
                        '211500',
                        '211600',
                        '211900'
                    ]);
            })->select([
                DB::raw("CONCAT(p.nm_partner, ' ',  shop.nm_shop) AS nm_shop"),
//            'shop.nm_shop',
                'shop.no_shop',
//            'shop.no_partner',
//            'p.nm_partner',
                'p.no_partner',
                DB::raw(
                    sprintf(
                        '(6371 * ACOS(COS(RADIANS(%1$s)) * COS(RADIANS(shop.at_lat)) * COS(RADIANS(shop.at_lng) -RADIANS(%2$s)) + SIN(RADIANS(%1$s)) * SIN(RADIANS(shop.at_lat)))) AS distance',
                        $position['x'],
                        $position['y']
                    )
                ),
                'd.yn_car_pickup',
                'd.yn_shop_pickup',
                'shop.at_send_price',
                'shop.at_send_disct',
            ])->where('shop.at_lat', '>', 0)
            ->where('shop.at_lng', '>', 0)
            ->where('shop.ds_status', 'Y')
            ->where('shop.list_cd_third_party', 'REGEXP', getAppType()->value)
            ->whereRaw(
                'IF(? BETWEEN sot.ds_break_start_time AND sot.ds_break_end_time, true, false) = false',
                [now()->format('Hi')]
            )
            ->whereRaw(
                'IF(? BETWEEN sot.ds_break_start_time2 AND sot.ds_break_end_time2, true, false) = false',
                [now()->format('Hi')]
            )
            ->whereRaw(
                'IF(sh.nt_weekday = WEEKDAY(NOW()) OR (NOW() BETWEEN sh.dt_imsi_start AND sh.dt_imsi_start), true, false) = false'
            )
            ->where(function ($query) {
                $query->where('d.yn_car_pickup', '=', 'Y')->orWhere('d.yn_shop_pickup', '=', 'Y');
            })
            ->whereIn('p.cd_biz_kind', ['201100', '201200', '201400']) //다른 카테고리 추가 시에 프론트와 이야기 해보아야 함!!
            //->having('distance', '<=', $radius) //매장이 안나오는 경우가 있어 일단 주석처리
            ->orderBy('distance')->limit($limit)->with(['productIgnoreExcept' => function ($query) {
                $query->where('ds_image_path', '<>', '')->inRandomOrder();
            }])->get();
    }

    /**
     * @return array
     */
    public static function getTags(): array
    {
        return Partner::select(['tags'])->whereNotNull('tags')->get()->map(function ($tags) {
            return array_filter(explode('|', $tags->tags));
        })->flatten()->unique()->values()->all();
    }
}

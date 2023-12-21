<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AppType;
use App\Enums\EnumShopType;
use App\Enums\Pickup;
use App\Enums\SearchBizKind;
use App\Enums\SearchBizKindDetail;
use App\Exceptions\OwinException;
use App\Exceptions\SpcException;
use App\Exceptions\TMapException;
use App\Models\PartnerCategory;
use App\Models\RetailCategory;
use App\Models\RetailSubCategory;
use App\Models\Shop;
use App\Models\ShopDetail;
use App\Models\ShopHoliday;
use App\Models\ShopOilPrice;
use App\Models\ShopOilUnuseCard;
use App\Models\ShopOptTime;
use App\Utils\Code;
use App\Utils\Common;
use App\Utils\Spc;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class ShopService extends Service
{
    /**
     * 매장 휴일정보 반환
     *
     * @param int $noShop
     * @return array
     */
    public static function getShopHoliday(int $noShop): array
    {
        $timestamp = date('Y-m-d H:i:s');
        $today = intval(date('N') - 1);
        $searchEndTime = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . '+1 minutes'));

        $response = [
            'regular' => [],
            'temp' => [],
        ];
        $shopHolidays = ShopHoliday::where([
            ['no_shop', '=', $noShop],
            ['cd_holiday', '<>', 211900],
        ])->orWhere([
            ['no_shop', '=', $noShop],
            ['cd_holiday', '=', 211900],
            ['dt_imsi_end', '>', $searchEndTime],
        ])->get();

        foreach ($shopHolidays as $holiday) {
            //정기 휴일
            if (in_array($holiday['cd_holiday'], [211200, 211300, 211400, 211500, 211600])) {
                if (!data_get($response, 'regular.' . $holiday['cd_holiday'])) {
                    $response['regular'][$holiday['cd_holiday']] = [];
                }
                $response['regular'][$holiday['cd_holiday']][] = $holiday['nt_weekday'];

                $response['yn_open'] = match ($holiday['cd_holiday']) {
                    '211200' => $today === $holiday['nt_weekday'] ? 'Y' : null,
                    '211300' => Common::getWeekByMonth($timestamp) == 1 && $today === $holiday['nt_weekday'] ? 'Y' : null,
                    '211400' => Common::getWeekByMonth($timestamp) == 2 && $today === $holiday['nt_weekday'] ? 'Y' : null,
                    '211500' => Common::getWeekByMonth($timestamp) == 3 && $today === $holiday['nt_weekday'] ? 'Y' : null,
                    '211600' => Common::getWeekByMonth($timestamp) == 4 && $today === $holiday['nt_weekday'] ? 'Y' : null,
                    default => null,
                };
            }

            //임시 휴일
            if ($holiday['dt_imsi_start'] && $holiday['dt_imsi_end'] && !isset($holiday['nt_weekday'])) {
                $response['temp'][] = $holiday;
                $startTime = date('Y-m-d H:i:s', strtotime($holiday['dt_imsi_start'] . '-1 minutes'));
                $endTime = date('Y-m-d H:i:s', strtotime($holiday['dt_imsi_end'] . '+1 minutes'));
                if ($timestamp > $startTime && $timestamp < $endTime) {
                    $response['yn_open'] = 'T'; // 매장임시휴일 [T]
                }
            }
        }
        $response['yn_open'] = $response['yn_open'] ?? 'Y';

        return $response;
    }

    /**
     * 매장 오늘 운영시간 정보 반환
     * @param int $noShop
     * @return ShopOptTime|null
     */
    public static function getInfoOptTime(int $noShop): ?ShopOptTime
    {
        return ShopOptTime::where([
            'no_shop' => $noShop,
            'nt_weekday' => DB::raw("WEEKDAY(CURDATE())")
        ])->first();
    }

    /**
     * @param Shop $shopInfo
     * @return string
     */
    public static function getYnShopOpen(Shop $shopInfo): string
    {
        $ynOpen = 'Y';
        if ($shopInfo['shopHolidayExists'] || $shopInfo['shopOptTimeExists']) {
            $ynOpen = 'N';
        }

        if ($ynOpen === 'Y') {
            $posError = SearchService::getPosError($shopInfo['no_shop']);
            if ($posError) {
                $ynOpen = 'N';
            }

            if ($shopInfo['ds_status'] === 'N') {
                $ynOpen = 'N';
            }

            if (isset($shopInfo['cd_pause_type']) && $shopInfo['cd_pause_type']) {
                $ynOpen = 'E';
            }
        }

        return $ynOpen;
    }

    /**
     * 매장 전체 영업시간
     * @param int $noShop
     *
     * @return Collection
     */
    public static function getInfoOptTimeAll(int $noShop): Collection
    {
        return ShopOptTime::where([
            'no_shop' => $noShop,
        ])->orderBy('nt_weekday')->get();
    }

    /**
     * @param int $noShop
     *
     * @return Shop
     */
    public static function shop(int $noShop): ?Builder
    {
        return Shop::where('no_shop', $noShop)->with(['partner']);
    }

    /**
     * @param int $noShop
     * @param string|null $pickupType
     * @return Shop
     * @throws OwinException
     * @throws TMapException
     */
    public static function getShop(int $noShop, ?string $pickupType = null): Shop
    {
        return self::shop($noShop)->get()->load([
            'shopDetail',
            'shopOptTime' => function ($q) {
                $q->where('nt_weekday', DB::raw('WEEKDAY(NOW())'));
            },
            'partner',
            'shopHolidayExists',
            'shopOptTimeExists',
            'partnerCategory',
            'partnerCategory.product',
            'product',
            'retailCategory.retailProduct',
            'retailCategory.retailSubCategories.retailProduct',
            'shopOilPrice',
            'shopOil',
            'shopOilUnUseCard',
            'washInShop.shop.partner',
            'washInShop.shop.washProducts.washOptions',
            'washProducts.washOptions',
            'washCommissions',
        ])->whenEmpty(function () {
            if (getAppType() == AppType::TMAP_AUTO) {
                throw new TMapException('SC1000', 400);
            }
            throw new OwinException(Code::message('9910'));
        })->map(function ($shop) use ($pickupType) {
            $shop->nm_shop = $shop->partner['nm_partner'] . ' ' . $shop->nm_shop;
            $shop->at_grade = ReviewService::getReviewTotal($shop->no_shop)?->at_grade;
            if ($shop->shopDetail) {
                $dsImage1 = $shop->shopDetail->ds_image1;
                if ($shop->no_partner === Code::conf('oil.gs_no_partner')) {
                    $dsImage1 = $dsImage1 ?? '/data2/shop/1000/gs_default.jpg';
                } elseif ($shop->no_partner === Code::conf('oil.ex_no_partner')) {
                    $dsImage1 = $dsImage1 ?? '/data2/shop/1426/ex_default.jpg';
                }
                $shop->shopDetail->ds_image1 = Common::getImagePath($dsImage1) ?: null;

                $shop->shopDetail->is_car_pickup = match (SearchBizKind::getBizKind($shop->partner->cd_biz_kind)) {
                    SearchBizKind::FNB => $shop->shopDetail->yn_car_pickup == 'Y',
                    default => true
                };

                $shop->shopDetail->is_shop_pickup = match (SearchBizKind::getBizKind($shop->partner->cd_biz_kind)) {
                    SearchBizKind::FNB => $shop->shopDetail->yn_shop_pickup == 'Y',
                    default => false
                };
            }

            $shop->ds_status = Auth::user()?->is_master ? 'Y' : $shop->ds_status;
            $shop->ds_menu_info = $shop->partner->ds_menu_origin ?? $shop->shopDetail?->ds_text10;
            $shop->nm_partner = $shop->partner->nm_partner;
            $shop->yn_open = Auth::user()?->is_master ? 'Y' : ShopService::getYnShopOpen($shop);
            $shop->biz_kind = SearchBizKind::getBizKind($shop->partner->cd_biz_kind)->name;
            $shop->is_order = Auth::user()?->is_master ? Auth::user()?->is_master : $shop->yn_open == 'Y' && $shop->shopOptTime->first()?->is_order;
            $shop->at_partner_make_ready_time = $shop->partner->at_make_ready_time;

            $shop->list_category = match (true) {
                $shop->partnerCategory->count() > 0 => self::getCategoryList($shop, $pickupType),
                $shop->retailCategory->count() > 0 => self::getRetailCategoryList($shop->retailCategory),
                default => null
            };
            unset($shop->partnerCategory, $shop->retailCategory, $shop->product);

            if ($shop->washInShop) {
                $shop->wash_shop = $shop->washInShop->shop;
                $shop->wash_shop->nm_shop = $shop->washInShop->shop->partner->nm_partner . ' ' . $shop->washInShop->shop->nm_shop;
                unset($shop->washInShop);
            }

            return $shop;
        })->first();
    }

    /**
     * @param int $noShop
     * @param string|null $pickupType
     * @return Shop|Collection|null
     * @throws OwinException
     * @throws TMapException
     */
    public static function getShopInfo(int $noShop, ?string $pickupType = null): ?Shop
    {
        return self::shop($noShop)->get([
            'no_shop',
            'no_partner',
            'nm_shop',
            'ds_open_time',
            'ds_close_time',
            'at_grade',
            'ds_address',
            'ds_address2',
            'at_lat',
            'at_lng',
            'at_lat_shop',
            'at_lng_shop',
            'at_commission_rate',
            'at_make_ready_time',
            'at_send_price',
            'at_send_disct',
            'at_cup_deposit',
            'ds_tel',
            'cd_spc_store',
            'ds_status'
        ])->load([
            'shopDetail:no_shop,ds_image1,ds_image2,ds_image3,ds_image4,ds_image5,ds_image6,ds_image7,ds_image8,ds_image9,ds_image10,ds_text10,ds_content,yn_shop_pickup,yn_car_pickup,yn_booking_pickup,yn_car_pickup_for_cu,yn_disabled',
            'shopOptTime:no_shop,nt_weekday,ds_open_time,ds_close_time,cd_break_time,ds_break_start_time,ds_break_end_time,cd_break_time2,ds_break_start_time2,ds_break_end_time2',
            'partner:no_partner,nm_partner,cd_biz_kind,cd_biz_kind_detail,ds_bi,ds_pin,cd_spc_brand',
            'shopOilPrice:no_shop,ds_prod,cd_gas_kind,at_price,at_discnt_liter',
            'washInShop:no_shop',
            'washProducts:no_product,no_shop,nm_product,at_price,cd_car_kind',
            'washProducts.washOptions:no_option,no_shop,no_product,nm_option,at_price',
            'partnerCategory',
            'partnerCategory.product',
            'product',
            'retailCategory.retailProduct',
            'retailCategory.retailSubCategories.retailProduct',
            'washInShop.shop.washProducts.washOptions',
            'shopHolidayExists',
            'shopOptTimeExists'
        ])->whenEmpty(function () {
            if (getAppType() == AppType::TMAP_AUTO) {
                throw new TMapException('SC1000', 400);
            }
            throw new OwinException(Code::message('9910'));
        })->map(function ($shop) use ($pickupType) {
            $shop->ds_status = Auth::user()?->is_master ? 'Y' : $shop->ds_status;
            $shop->nm_shop = $shop->partner->nm_partner . ' ' . $shop->nm_shop;
            $shop->nm_partner = $shop->partner->nm_partner;
            $shop->at_partner_make_ready_time = $shop->partner->at_make_ready_time;
            $shop->yn_open = Auth::user()?->is_master ? 'Y' : ShopService::getYnShopOpen($shop);
            $shop->biz_kind = SearchBizKind::getBizKind($shop->partner->cd_biz_kind)->name;
            $shop->is_order = Auth::user()?->is_master ? Auth::user()?->is_master : $shop->yn_open == 'Y' && $shop->shopOptTime->first()?->is_order;
            $shop->list_category = match (true) {
                $shop->partnerCategory->count() > 0 => self::getCategoryList($shop, $pickupType),
                $shop->retailCategory->count() > 0 => self::getRetailCategoryList($shop->retailCategory),
                default => null
            };

            unset($shop->partnerCategory, $shop->retailCategory, $shop->product);

            $shop->shop_holiday = ShopService::getShopHoliday($shop->no_shop);

            //플랫폼별 전달비 금액 적용(conf.yml at_send_price(플랫폼 전달비)가 있을 경우 해당 값 사용, 없으면 DB에 저장된 전달비 항목 사용)
            $shop->at_send_price = match ($pickupType) {
                Pickup::SHOP->name => 0,
                default => $shop->at_order_send_price
            };

            $shop->at_send_disct = match ($pickupType) {
                Pickup::SHOP->name => 0,
                default => min($shop->at_order_send_price, data_get($shop, 'at_send_disct', 0))
            };


            return $shop;
        })->first();
    }

    /**
     * @param Shop $shop
     * @param string|null $pickupType
     * @return Collection
     */
    private static function getCategoryList(Shop $shop, ?string $pickupType): Collection
    {
        $spcStock = match (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail) == SearchBizKindDetail::SPC) {
            true => (function () use ($shop) {
                return $shop->partnerCategory->map(fn(PartnerCategory $category) => Spc::stock($shop->partner->cd_spc_brand, $shop->cd_spc_store, $category->no_partner_category));
            })()->flatten(1)->filter(fn(mixed $product) => !empty($product) && is_array($product))->mapWithKeys(fn(array $product) => [data_get($product, 'code') => data_get($product, 'qty')]),
            default => null
        };

        return $shop->partnerCategory->map(function (PartnerCategory $category) use ($pickupType, $spcStock) {
            return [
                'nm_category' => $category->nm_category,
                'yn_top' => null,
                'no_category' => $category->no_partner_category,
                'no_sub_category' => null,
                'count' => $category->product->where('ds_status', 'Y')->when($pickupType, function (Collection $product) use ($pickupType) {
                    return match ($pickupType) {
                        Pickup::CAR->name => $product->where('yn_car_pickup', 'Y'),
                        Pickup::SHOP->name => $product->where('yn_shop_pickup', 'Y'),
                        default => null
                    };
                })->filter(function ($collect) use ($spcStock) {
                    return empty($collect->cd_spc)
                        || $collect['yn_check_stock'] == 'N'
                        || ($collect['yn_check_stock'] == 'Y' && empty(data_get($spcStock, $collect->cd_spc)) == false && $spcStock[$collect->cd_spc] > 0);
                })->count()
            ];
        })->filter(fn($category) => $category['count'] > 0)->values();
    }

    /**
     * @param Collection $list
     * @return Collection
     */
    private static function getRetailCategoryList(Collection $list): Collection
    {
        return $list->map(function (RetailCategory $category) {
            return match ($category->retailSubCategories->count()) {
                0 => [
                    [
                        'nm_category' => $category->nm_category,
                        'yn_top' => $category->yn_top,
                        'no_category' => $category->no_category,
                        'no_sub_category' => null,
                        'count' => $category->retailProduct->count()
                    ]
                ],
                default => $category->retailSubCategories->map(
                    function (RetailSubCategory $subCategory) use ($category) {
                        return [
                            'nm_category' => sprintf('%s %s', $category->nm_category, $subCategory->nm_sub_category),
                            'yn_top' => $category->yn_top,
                            'no_category' => $category->no_category,
                            'no_sub_category' => $subCategory->no_sub_category,
                            'count' => $subCategory->retailProduct->count()
                        ];
                    }
                )
            };
        })->flatten(1)->filter(fn($category) => $category['count'] > 0)->values();
    }

    /**
     * @param int $noShop
     * @return void
     */
    public static function updateCtView(int $noShop): void
    {
        Shop::where(['no_shop' => $noShop])->increment('ct_view');
    }

    /**
     * @param Shop $shop
     * @param array $parameter
     * @return void
     * @throws Throwable
     */
    public static function updateShop(Shop $shop, array $parameter): void
    {
        $shop->updateOrFail($parameter);
    }

    /**
     * @param int $noShop
     * @return Collection
     */
    public static function getShopOilPrices(int $noShop): Collection
    {
        return ShopOilPrice::select([
            DB::raw("*"),
            DB::raw("(SELECT nm_code FROM code_manage WHERE no_code = cd_gas_kind) AS nm_gas_kind"),
            DB::raw("ADDTIME(dt_trade, tm_trade) AS dt_trade")
        ])->where([
            'no_shop' => $noShop
        ])->get();
    }

    /**
     * @param int $noShop
     * @return Shop|null
     */
    public static function getInfoCommission(int $noShop): ?Shop
    {
        return Shop::select([
            'cd_commission_type',
            'at_commission_amount',
            'at_commission_rate'
        ])->where([
            'no_shop' => $noShop
        ])->first();
    }

    /**
     * @param int $noShop
     * @param int|null $addDays
     * @return Collection
     */
    public function getHoliday(int $noShop, ?int $addDays = 14): Collection
    {
        $dates = [];
        for ($i = 0; $i < $addDays; $i++) {
            $week = match (Common::getWeekByMonth(now()->addDays($i)->format('Y-m-d'))) {
                0 => 211300,
                1 => 211400,
                2 => 211500,
                3 => 211600,
                default => null
            };
            $day = now()->addDays($i)->dayOfWeek;
            $dates[$week][$day] = now()->addDays($i)->format('Y-m-d');
        }

        return ShopHoliday::where('no_shop', $noShop)
            ->whereNot('cd_holiday', '211100')->get()->map(function ($holiday) use ($dates, $addDays) {
                $weekday = $holiday->nt_weekday + 1 > 6 ? 0 : $holiday->nt_weekday + 1;
                $data = [];

                switch ($holiday->cd_holiday) {
                    case '211100':
                        break;
                    case '211200':
                        for ($i = 0; $i < $addDays; $i += 7) {
                            $data[] = [
                                'holiday' => now()->endOfWeek($weekday)->addDays($i)->format('Y-m-d'),
                                'break_start_time' => now()->startOfDay()->format('H:i:s'),
                                'break_end_time' => now()->endOfDay()->format('H:i:s')
                            ];
                        }
                        break;
                    case '211300':
                    case '211400':
                    case '211500':
                    case '211600':
                        $data[] = match (empty($dates[$holiday->cd_holiday][$weekday])) {
                            false => [
                                'holiday' => $dates[$holiday->cd_holiday][$weekday],
                                'break_start_time' => now()->startOfDay()->format('H:i:s'),
                                'break_end_time' => now()->endOfDay()->format('H:i:s')
                            ],
                            default => null
                        };
                        break;
                    case '211900':
                        if ($holiday->dt_imsi_end < now()) {
                            return;
                        }
                        $period = CarbonPeriod::create($holiday->dt_imsi_start->format('Y-m-d'), $holiday->dt_imsi_end->format('Y-m-d'));
                        foreach ($period as $date) {
                            if ($date->format('Y-m-d') < now()->format('Y-m-d')) {
                                continue;
                            }
                            $data[] = [
                                'holiday' => $date->format('Y-m-d'),
                                'break_start_time' => $holiday->dt_imsi_start->format('Y-m-d') == $date->format('Y-m-d') ? $holiday->dt_imsi_start->format('H:i:s') : '00:00:00',
                                'break_end_time' => $holiday->dt_imsi_end->format('Y-m-d') == $date->format('Y-m-d') ? $holiday->dt_imsi_end->format('H:i:s') : '23:59:00',
                            ];
                        }
                        break;
                }

                return $data;
            })->flatten(1)->filter()->sortBy('holiday')->values();
    }

    /**
     * @param int $noShop
     * @return Collection
     */
    public function getOperate(int $noShop): Collection
    {
        $operate = Code::operate();

        return ShopOptTime::where('no_shop', $noShop)->get()->map(function ($opt) use ($operate) {
            return [
                'day_of_week' => $opt->nt_weekday + 1 > 6 ? 0 : $opt->nt_weekday + 1,
                'day_text' => $operate['day'][$opt->nt_weekday]['text'],
                'ds_open_time' => Carbon::createFromFormat('Hi', $opt->ds_open_time)->format('H:i:s'),
                'ds_close_time' => Carbon::createFromFormat('Hi', $opt->ds_close_time)->format('H:i:s'),
                'ds_close_full_time' => Carbon::createFromFormat('Hi', $opt->ds_close_time)->format('Y-m-d H:i:s'),
                'ds_open_order_time' => $opt->ds_open_order_time ? Carbon::createFromFormat(
                    'Hi',
                    $opt->ds_open_order_time
                )->format('H:i:s') : null,
                'ds_close_order_time' => $opt->ds_close_order_time ? Carbon::createFromFormat(
                    'Hi',
                    $opt->ds_close_order_time
                )->format('H:i:s') : null,
                'break1' => [
                    'type' => $opt->cd_break_time,
                    'text' => $operate['break'][$opt->cd_break_time] ?? null,
                    'start_time' => $opt->ds_break_start_time,
                    'end_time' => $opt->ds_break_end_time
                ],
                'break2' => [
                    'type' => $opt->cd_break_time2,
                    'text' => $operate['break'][$opt->cd_break_time2] ?? null,
                    'start_time' => $opt->ds_break_start_time2,
                    'end_time' => $opt->ds_break_end_time2
                ]
            ];
        });
    }

    /**
     * 주유 주문 안되는 카드 return
     * @param int $noShop
     * @return Collection
     */
    public static function getShopUnUseCards(int $noShop): Collection
    {
        return ShopOilUnuseCard::where([
            'no_shop' => $noShop,
            'yn_unuse_status' => 'Y'
        ])->get();
    }

    /**
     * SPC 브랜드 코드와 매장 코드를 가지고 OWIN 매장 조회
     * @param string $brandCode
     * @param array  $storeCodes
     *
     * @return Collection
     */
    public static function getSpcShop(string $brandCode, array $storeCodes): Collection
    {
        return Shop::select(['shop.*'])->join('partner', 'shop.no_partner', '=', 'partner.no_partner')
            ->whereIn('shop.cd_spc_store', $storeCodes)
            ->where('partner.cd_spc_brand', $brandCode)
            ->get()
            ->whenEmpty(function () {
                throw new SpcException(Code::message('E101'));
            });
    }

    /**
     * SPC 요청으로 들어온 매장 상태 변경
     * @param Collection $shops
     * @param Collection $request
     *
     * @return void
     * @throws SpcException
     */
    public static function setShopStatus(Collection $shops, Collection $request)
    {
        $orderType = data_get($request, 'orderType');
        try {
            DB::beginTransaction();
            $update = [
                'id_upt' => 'HAPPY_ORDER',
                'dt_upt' => now(),
            ];

            $state = $request['storeStatus'] == 'open' ? 'Y' : 'N';
            if ($orderType == 'DRIVETHRU') {
                $update['yn_car_pickup'] = $state;
            } elseif ($orderType == 'PICKUP') {
                $update['yn_shop_pickup'] = $state;
            } else {
                $update['yn_car_pickup'] = $state;
                $update['yn_shop_pickup'] = $state;
            }

            ShopDetail::whereIn('no_shop', $shops->pluck('no_shop')->all())->update($update);
            //elastic search 반영을 위하여 shop 테이블의 dt_upt, id_upt도 업데이트
            Shop::whereIn('shop.no_shop', $shops->pluck('no_shop')->all())
                ->update([
                    'shop.id_upt' => 'HAPPY_ORDER',
                    'shop.dt_upt' => now()
                ]);
            DB::commit();
        } catch (Throwable $t) {
            DB::rollBack();
            throw new SpcException($t->getMessage());
        }
    }
}

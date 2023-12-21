<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BenefitDetailType;
use App\Enums\BenefitType;
use App\Enums\DiscountSale;
use App\Enums\EnumYN;
use App\Enums\MemberLevel;
use App\Enums\OptionType;
use App\Enums\Pg;
use App\Enums\Pickup;
use App\Enums\SearchBizKind;
use App\Enums\SearchBizKindDetail;
use App\Enums\SendType;
use App\Enums\ServiceCode;
use App\Enums\ServicePayCode;
use App\Enums\SpcAlarmEventStatus;
use App\Exceptions\MobilXException;
use App\Exceptions\OwinException;
use App\Models\MemberCard;
use App\Models\MemberCarinfo;
use App\Models\MemberPush;
use App\Models\MemberShopEnterLog;
use App\Models\MemberShopRetailLog;
use App\Models\MemberShopWashLog;
use App\Models\MemberWashCoupon;
use App\Models\OrderAlarmEventLog;
use App\Models\OrderList;
use App\Models\OrderLocation;
use App\Models\OrderPayment;
use App\Models\OrderProcess;
use App\Models\OrderProduct;
use App\Models\OrderRetailCommission;
use App\Models\OrderWashCommission;
use App\Models\ParkingOrderList;
use App\Models\ParkingOrderProcess;
use App\Models\ParkingSite;
use App\Models\Product;
use App\Models\RetailOrderProduct;
use App\Models\RetailOrderProductOption;
use App\Models\RetailProduct;
use App\Models\Shop;
use App\Models\User;
use App\Models\VirtualNumberIssueLog;
use App\Models\WashHandProduct;
use App\Models\WashProduct;
use App\Services\Pg\PgService;
use App\Utils\AutoParking as AutoParkingUtil;
use App\Utils\BizCall;
use App\Utils\Code;
use App\Utils\Common;
use App\Utils\Cu;
use App\Utils\Parking;
use App\Utils\Spc;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Owin\OwinCommonUtil\CodeUtil;
use Owin\OwinCommonUtil\Enums\ServiceCodeEnum;
use Throwable;

class OrderService extends Service
{
    /**
     * @param int $noUser
     * @param array|null $operate
     * @param array|null $notIn
     * @return Collection
     */
    public function ordering(int $noUser, ?array $operate = null, ?array $notIn = null): Collection
    {
        return OrderList::where('no_user', $noUser)
            ->where(function ($query) use ($operate, $notIn) {
                if (empty($operate) === false) {
                    foreach ($operate as $key => $value) {
                        $query->where($key, $value[0], $value[1]);
                    }
                }
                if (empty($notIn) === false) {
                    foreach ($notIn as $key => $value) {
                        $query->whereNotIn($key, $value);
                    }
                }
            })->get();
    }

    /**
     * @param int $noUser
     * @return Collection
     */
    public static function orderingByExternal(int $noUser): Collection
    {
        return OrderList::join('partner AS p', 'order_list.no_partner', '=', 'p.no_partner')
            ->where('order_list.no_user', $noUser)
            ->where(function ($query) {
                $query->where([
                    'p.cd_biz_kind' => '201300',
                    'order_list.cd_order_status' => '601200',
                ])->where('order_list.cd_pickup_status', '<', '602400')->orWhere(function ($query) {
                    $query->whereIn('p.cd_biz_kind', ['201100', '201200', '201400'])
                        ->whereIn('order_list.cd_pickup_status', ['602100', '602200', '602300', '602900'])
                        ->where([
                            'order_list.cd_order_status' => '601200',
                            'order_list.cd_payment_status' => '603300'
                        ]);
                });
            })->get();
    }

    /**
     * @param string $month
     * @param string|null $ctDate
     * @return Collection
     */
    public function getUsePayment(string $month, ?string $ctDate): Collection
    {
        return (new OrderList())->getUsePayment(
            Carbon::createFromFormat('Ym', $month)->format('Y-m-01'),
            $ctDate
        );
    }

    /**
     * @param array $orderWhere
     * @param array $orderData
     * @param array|null $logData
     * @return void
     */
    public static function updateOrderVns(
        array $orderWhere,
        array $orderData,
        ?array $logData = null
    ) {
        DB::transaction(function () use ($orderWhere, $orderData, $logData) {
            OrderList::where($orderWhere)->update($orderData);
            if (empty($logData) === false) {
                VirtualNumberIssueLog::where($logData)->update($logData);
            }
        });
    }

    /**
     * @param $noUser
     * @return Collection
     */
    public static function getPushInfo($noUser)
    {
        return MemberPush::select([
            'member_push.cd_phone_os',
            'member_push.ds_phone_token',
            'member.yn_push_msg',
            'member.nm_user',
        ])->leftJoin('member', function ($q) {
            $q->on('member_push.no_user', 'member.no_user');
        })->where([
            'member.yn_push_msg' => 'Y',
            'member_push.no_user' => $noUser,
            'member_push.cd_service' => '900100'
        ])->get();
    }

    /**
     * @param $noShop
     * @return string
     */
    public static function getNoOrder($noShop)
    {
        $data = OrderList::select([
            DB::raw(
                "CONCAT( DATE_FORMAT(NOW(),'%y%m%d'),'" . $noShop
                . "', LPAD(IFNULL(COUNT(no)+1,'1'),4,'0')) AS no_order"
            )
        ])->where([
            ['no_shop', '=', $noShop],
            ['dt_reg', '>', DB::raw("CURDATE()")],
        ])->first();

        return intval($data['no_order']) . mt_rand(100, 999);
    }

    /**
     * @param $data
     * @return void
     * @throws Throwable
     */
    public static function registOrderProcess($data)
    {
        (new OrderProcess([
            'no_user' => $data['no_user'],
            'no_order' => $data['no_order'],
            'no_shop' => $data['no_shop'],
            'cd_order_process' => $data['cd_order_process'],
            'dt_order_process' => now(),
        ]))->saveOrFail();
    }

    /**
     * @param $noOrder
     * @param $noShop
     * @return Builder|Model|object|null
     */
    public static function getOrder($noOrder, $noShop = null)
    {
        $where = [
            'no_order' => $noOrder
        ];
        if ($noShop) {
            $where['no_shop'] = $noShop;
        }
        $order = OrderList::where($where)->with([
            'member',
            'orderPayment',
            'partner',
            'shop.shopDetail',
            'shop.washInShop',
            'orderProcess',
            'shopOil',
        ])->first();

        if ($order) {
            $order['nm_shop'] = $order->partner->nm_partner ??
                $order->shop->nm_shop;

            if (isset($order->shop) && isset($order->shop->shopDetail)) {
                $dsImage1 = $order->shop->shopDetail->ds_image1;
                if ($order->shop->no_partner === Code::conf('oil.gs_no_partner')) {
                    $dsImage1 = $dsImage1 ?? '/data2/shop/1000/gs_default.jpg';
                } elseif ($order->shop->no_partner === Code::conf('oil.ex_no_partner')) {
                    $dsImage1 = $dsImage1 ?? '/data2/shop/1426/ex_default.jpg';
                }
                $order->shop->shopDetail->ds_image1 = Common::getImagePath($dsImage1) ?: null;
            }
        }

        return $order;
    }

    /**
     * @param $update
     * @param $where
     * @return void
     */
    public static function updateOrder($update, $where)
    {
        OrderList::where($where)->update($update);
    }

    /**
     * @param $atDistance
     * @param $atLat
     * @param $atLng
     * @param $noOrder
     * @return void
     */
    public static function changeDistance($atDistance, $atLat, $atLng, $noOrder)
    {
        OrderList::where('no_order', $noOrder)->update([
            'at_distance' => $atDistance,
            'at_lat_decide' => $atLat,
            'at_lng_decide' => $atLng
        ]);
    }

    /**
     * @param $noUser
     * @param $noShop
     * @return int
     */
    public static function checkReviewWriteAuth($noUser, $noShop)
    {
        return OrderList::where([
            ['no_user', '=', $noUser],
            ['no_shop', '=', $noShop],
            ['dt_reg', '>', DB::raw("DATE_ADD(NOW(), INTERVAL -7 DAY)")],
            ['cd_pickup_status', '=', '602400'],
            ['cd_payment_status', '=', '603300'],
        ])->count();
    }

    /**
     * @param array $parameter
     * @return Collection
     */
    public static function getParkingOrderInfo(array $parameter): Collection
    {
        return ParkingOrderList::with([
            'parkingSite',
            'autoParking',
            'ticket',
            'card' => function ($q) {
                $q->withTrashed();
            }])->where($parameter)->get()->map(function ($item) {
                $item->cd_biz_kind_detail = SearchBizKindDetail::PARKING->name;
            list($item->cd_status, $item->nm_status) = getOrderStatus(
                cdBizKind: '201500',
                cdOrderStatus: $item->cd_order_status,
                cdPaymentStatus: $item->cd_payment_status,
                parkingStatus: $item->cd_parking_status
            );
            if (empty($item->ticket) == false) {
                $item->list_product = [[
                    'no_product' => $item->ticket?->no_product,
                    'nm_product' => $item->ticket?->nm_product,
                    'ct_inven' => 1,
                    'at_price_product' => $item->at_price_pg,
                    'option' => null,
                ]];
            }
            return $item;
        });
    }

    /**
     * @param array $parameter
     * @param array|null $whereIn
     * @param array|null $whereNotIn
     * @return Collection
     */
    public static function getOrderInfo(
        array $parameter,
        ?array $whereIn = [],
        ?array $whereNotIn = []
    ): Collection {
        $orderList = OrderList::select(['order_list.*'])->with([
            'partner',
            'shop.shopOil',
            'shop.shopDetail',
            'orderPayment.memberCard',
            'orderProduct.product',
            'orderProduct.washProduct',
            'retailOrderProduct.retailOrderProductOption',
            'shop.shopHolidayExists',
            'shop.shopOptTimeExists',
            'shop.washInshop.shop.partner',
            'shop.shopOptTime' => function ($q) {
                $nowWeek = Carbon::now()->dayOfWeek - 1 < 0 ? 6
                    : Carbon::now()->dayOfWeek - 1;
                $q->where('nt_weekday', $nowWeek);
            },
            'orderOilEvent',
            'orderProcess',
            'shopOilUnit',
            'card' => function ($q) {
                $q->withTrashed();
            },
        ])->where($parameter)->join(
            'partner',
            'partner.no_partner',
            '=',
            'order_list.no_partner'
        );

        if ($whereIn) {
            foreach ($whereIn as $key => $value) {
                $orderList = $orderList->whereIn($key, $value);
            }
        }

        if ($whereNotIn) {
            foreach ($whereNotIn as $key => $value) {
                $orderList = $orderList->whereNotIn($key, $value);
            }
        }

        return $orderList->get()->whenEmpty(function () {
            throw new OwinException(Code::message('P2120'));
        })->map(function ($item) {
            $item->nm_shop = $item->partner->nm_partner . ' ' . $item->shop->nm_shop;

            $item->yn_payment_cancel = match (
                ($item->cd_pickup_status == '602100')
                || (
                    $item->cd_pickup_status == '602200'
                    && $item->at_add_delay_min > 0
                    && Carbon::createFromFormat(
                        'Y-m-d H:i:s',
                        $item->dt_pickup_status
                    )->addSeconds(300) > now()
                )
            ) {
                true => EnumYN::Y->name,
                default => EnumYN::N->name
            };
            list($item->cd_status, $item->nm_status) = getOrderStatus(
                cdBizKind: $item->partner->cd_biz_kind,
                cdOrderStatus: $item->cd_order_status,
                cdPickupStatus: $item->cd_pickup_status,
                cdPaymentStatus: $item->cd_payment_status,
                cdPgResult: $item->orderPayment->cd_pg_result,
                cdBizKindDetail: $item->partner->cd_biz_kind_detail,
            );
            $item->ct_order = isset($item->orderProduct->product) ? count($item->orderProduct->product) : (isset($item->orderProduct->washProduct) ? count($item->orderProduct->washProduct) : 1);
            $item->yn_device = empty($item->no_device) ? EnumYN::N->name : EnumYN::Y->name;
            $item->list_product = self::makeListProduct($item);
            $item->wash_in_shop = $item->shop->washInshop?->shop;
            $item->pickup_type = match (SearchBizKind::getBizKind($item->partner->cd_biz_kind)) {
                SearchBizKind::FNB => Pickup::tryFrom((int)$item->cd_pickup_type)->name,
                default => null,
            };
            $item->is_car_pickup = $item->shop->shopDetail->yn_car_pickup == 'Y';
            $item->is_shop_pickup = $item->shop->shopDetail->yn_shop_pickup == 'Y';

            return $item;
        });
    }

    /**
     * @param OrderList $orderList
     * @return Collection|null
     */
    public static function makeListProduct(OrderList $orderList): ?Collection
    {
        return match (true) {
            $orderList->retailOrderProduct->count() > 0 => $orderList->retailOrderProduct->map(function ($product) {
                return [
                    'no_product' => $product->no_product,
                    'nm_product' => $product->nm_product . ' ' . match ($product->cd_discount_sale) {
                            DiscountSale::ONE_PLUS_ONE->value, DiscountSale::TWO_PLUS_ONE->value => CodeService::getCode(
                                $product->cd_discount_sale
                            )->nm_code,
                            default => null
                        },
                    'ct_inven' => $product->ct_inven,
                    'at_price_product' => $product->at_price_product * match ($product->cd_discount_sale) {
                            DiscountSale::TWO_PLUS_ONE->value => 2,
                            default => 1
                        },
                    'yn_cup_deposit' => 'N',
                    'option' => in_array(
                        $product->cd_discount_sale,
                        [DiscountSale::ONE_PLUS_ONE->value, DiscountSale::TWO_PLUS_ONE->value]
                    ) ?: $product->retailOrderProductOption->map(function ($option) {
                        return [
                            'no_option_group' => $option->retailProductOption?->no_group,
                            'no_option' => $option->no_option,
                            'add_price' => $option->at_price_product_opt,
                            'nm_option_group' => null,
                            'nm_option' => $option->nm_product_opt,
                            'yn_cup_deposit' => 'N',
                            'ea' => $option?->ea ?? 1
                        ];
                    }),
                ];
            }),
            $orderList->orderProduct->count() > 0 => $orderList->orderProduct->map(function ($product) {
                return [
                    'no_product' => $product->no_product,
                    'nm_product' => $product->nm_product,
                    'ct_inven' => $product->ct_inven,
                    'at_price_product' => $product->at_price_product,
                    'yn_cup_deposit' => $product->product?->yn_cup_deposit ?? 'N',
//                        todo 상품옵션정보 options로 migration
                    'option' => match (empty($product->options)) {
                        false => collect(json_decode($product->options))->map(function ($option) {
                            return [
                                'no_option_group' => $option->no_option_group,
                                'no_option' => $option->no_option,
                                'add_price' => $option->add_price,
                                'nm_option_group' => $option->nm_option_group,
                                'nm_option' => $option->nm_option,
                                'yn_cup_deposit' => $option?->yn_cup_deposit ?? 'N',
                                'ea' => $option?->ea ?? 1,
                            ];
                        }),
                        default => null
                    }
                ];
            }),
            default => null
        };
    }

    /**
     * @param array $parameter
     * @param array $where
     * @param array|null $whereNot
     * @return void
     */
    public function updateOrderList(
        array $parameter,
        array $where,
        ?array $whereNot = []
    ): void {
        OrderList::where($where)->when(
            empty($whereNot) === false,
            function ($query) use ($whereNot) {
                foreach ($whereNot as $key => $value) {
                    $query->where($key, $value[0], $value[1]);
                }
            }
        )->update($parameter);
    }

    /**
     * @param array $parameter
     * @param array|null $operate
     * @return int
     */
    public function getOrderCount(array $parameter, ?array $operate = []): int
    {
        return OrderList::join(
                'partner AS p',
                'order_list.no_partner',
                '=',
                'p.no_partner'
            )
                ->where($parameter)->when(
                    empty($operate) === false,
                    function ($query) use ($operate) {
                        foreach ($operate as $key => $value) {
                            $query->where($key, $value[0], $value[1]);
                        }
                    }
                )->selectRaw(
                    "
                SUM(
                    CASE
                    WHEN cd_biz_kind IN ('201100', '201200', '201400', '201800')  AND cd_payment_status = '603300' THEN 1
                    WHEN cd_biz_kind IN ('201300','201600')  AND cd_payment_status = '603100' THEN 1 ELSE 0 END
                ) AS count
            "
                )->first()->count ?? 0;
    }

    /**
     * @param User $user
     * @param Shop $shop
     * @param Collection $request
     * @return array
     * @throws OwinException
     */
    public function payment(User $user, Shop $shop, Collection $request): array
    {
        $verify = match (SearchBizKind::getBizKind($shop->partner->cd_biz_kind)) {
            SearchBizKind::FNB => $this->verifyFnbOrder($user, $shop, $request),
            SearchBizKind::RETAIL => $this->verifyRetailOrder($user, $shop, $request),
            SearchBizKind::OIL => '',
            SearchBizKind::WASH => $this->verifyWashOrder($user, $shop, $request),
            default => throw new OwinException(Code::message('9910'))
        };

        $pgPrice = $request['at_price_calc'];
        $noOrder = CodeUtil::generateOrderCode(ServiceCodeEnum::OWIN);
        $noPayment = makePaymentNo();
        $nmOrder = preg_replace("/[\,\&\;\r\n\\\|\'\"\<\>]/", "_", $verify['shopProducts']->first()->nm_product . match(count($request['list_product']) > 1) {
                true => sprintf(' 외 %s건', count($request['list_product']) - 1),
                default => ''
            });
        $cdPg = match ($shop->cd_pg) {
            500600 => Pg::incarpayment_kcp,
            default => Pg::from($shop->cd_pg)
        };

        $parameter = [
            'nmPg' => $cdPg->name,
            'noOrder' => $noOrder,
            'noShop' => $shop->no_shop,
            'noUser' => $user->no_user,
            'nmBuyer' => $user->nm_user,
            'email' => $user->id_user,
            'phone' => $user->ds_phone,
            'price' => $pgPrice,
            'atCupDeposit' => $request['at_cup_deposit'],
            'billkey' => $verify['card']->ds_billkey,
            'nmOrder' => $nmOrder
        ];
//        $pg = (new PgService($cdPg->name))->setPg();
        $paymentInfo = match ($pgPrice) {
            0 => [
                'res_cd' => '0000',
                'res_msg' => Code::message('0000'),
                'ds_req_param' => $parameter,
                'ds_res_param' => [],
                'ds_res_order_no' => null,
                'at_price_pg' => 0
            ],
            default => \App\Utils\Pg::payment($parameter)
        };
        try {
            $orderPayment = new OrderPayment([
                'no_order' => $noOrder,
                'no_payment' => $noPayment,
                'no_partner' => $shop->no_partner,
                'no_shop' => $shop->no_shop,
                'no_user' => $user->no_user,
                'cd_pg' => $shop->cd_pg,
                'ds_res_order_no' => $paymentInfo['ds_res_order_no'],
                'cd_payment' => '501200',
//            'cd_payment_kind' => '',
                'cd_payment_status' => match ($paymentInfo['res_cd']) {
                    '0000' => '603300',
                    default => '603200'
                },
                'ds_req_param' => json_encode(
                    $paymentInfo['ds_req_param'],
                    JSON_UNESCAPED_UNICODE
                ),
                'ds_server_reg' => now()->format('YmdHis'),
                'ds_res_param' => json_encode(
                    $paymentInfo['ds_res_param'],
                    JSON_UNESCAPED_UNICODE
                ),
                'cd_pg_result' => match ($paymentInfo['res_cd']) {
                    '0000' => '604100',
                    default => '604999'
                },
                'ds_res_msg' => $paymentInfo['res_msg'],
                'ds_res_code' => $paymentInfo['res_cd'],
                'at_price' => $request['at_price_total'],
                'at_price_pg' => $paymentInfo['at_price_pg'],
                'cd_card_corp' => $verify['card']->cd_card_corp,
                'no_card' => $verify['card']->no_card,
                'no_card_user' => $verify['card']->no_card_user,
                'product_num' => count(
                    data_get($request['list_product'], '*.*')
                ),
            ]);
            $orderPayment->saveOrFail();
            if ($paymentInfo['res_cd'] == '0000') {
                $atCommissionRate = match (SearchBizKind::getBizKind($shop->partner->cd_biz_kind)) {
                    SearchBizKind::RETAIL => match ($shop->partner->cd_calculate_main) {
                        '618300' => match ($shop->partner->cd_commission_type) {
                            '205100' => $shop->partner->at_commission_rate,
                            default => data_get($request, 'at_commission_rate')
                        },
                        default => data_get($request, 'at_commission_rate')
                    },
                    default => data_get($request, 'at_commission_rate')
                };

                $orderList = (new OrderList([
                    'no_order' => $noOrder,
                    'no_payment_last' => $noPayment,
                    'nm_order' => $nmOrder,
                    'no_user' => $user->no_user,
                    'no_device' => '',
                    'ds_adver' => '',
                    'no_partner' => $shop->no_partner,
                    'no_shop' => $shop->no_shop,
                    'cd_service' => ServiceCode::case($request['cd_service']),
                    'cd_service_pay' => ServicePayCode::case($request['cd_service_pay']),
                    'cd_calc_status' => '609100',
                    'cd_send_status' => '610100',
                    'no_card' => $verify['card']->no_card,
                    'at_price' => $request['at_price_total'],
                    'at_lat_decide' => $shop->at_lat,
                    'at_lng_decide' => $shop->at_lng,
                    'dt_pickup' => match (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail)) {
                        SearchBizKindDetail::SPC => match (data_get($request, 'pickup_type')) {
                            Pickup::SHOP->name => now(),
                            default => $request['arrived_time']
                        },
                        default => $request['arrived_time'] ?? now()->format('Y-m-d 23:59:59')
                    },
                    'ds_address' => $request['ds_address'] ?? null,
                    'ds_address2' => $request['ds_address2'] ?? null,
                    'yn_gps_status' => 'Y',
                    'cd_alarm_event_type' => '607000',
                    'cd_call_shop' => '611100',
                    'cd_payment' => '501200',
                    'cd_payment_status' => $orderPayment->cd_payment_status,
                    'cd_order_status' => '601200',
                    'cd_pickup_status' => '602100',
                    'at_cpn_disct' => $request['at_cpn_disct'],
                    'at_disct' => data_get($request, 'at_disct'),
                    'at_price_pg' => $pgPrice,
                    'cd_gas_kind' => '',
                    'at_gas_price' => '',
                    'at_gas_price_opnet' => '',
                    'cd_pg' => $shop->cd_pg,
                    'at_liter_gas' => '',
                    'yn_gas_order_liter' => '',
                    'yn_gas_pre_order' => '',
                    'at_commission_rate' => data_get($request, 'at_commission_rate'),
                    'ds_request_msg' => data_get($request, 'ds_request_msg'),
                    'ds_cpn_no' => data_get($request, 'discount_info.coupon.no'),
                    'ds_franchise_num' => $shop->shopDetail?->ds_franchise_num,
                    'cd_booking_type' => '',
                    'at_send_price' => match (data_get($request, 'pickup_type')) {
                        Pickup::SHOP->name => 0,
                        default => $shop->at_send_price
                    },
                    'at_third_party_send_price' => match (data_get($request, 'pickup_type')) {
                        Pickup::SHOP->name => 0,
                        default => Code::conf('at_send_price') ?? 0
                    },
                    'at_send_disct' => match (data_get($request, 'pickup_type')) {
                        Pickup::SHOP->name => 0,
                        default => data_get($request, 'at_send_disct')
                    },
                    'ds_car_number' => match (data_get($request, 'pickup_type')) {
                        Pickup::SHOP->name => '매장픽업',
                        default => data_get($request, 'car_number')
                    },
                    'at_send_sub_disct' => match (data_get($request, 'pickup_type')) {
                        Pickup::SHOP->name => 0,
                        default => data_get($request, 'at_send_sub_disct')
                    },
                    'seq' => $user->memberCarInfoAll->where(
                        'ds_car_number',
                        $request['car_number']
                    )->first()?->seq,
                    'cd_third_party' => getAppType()->value,
                    'cd_send_type' => match (data_get($request, 'pickup_type')) {
                        Pickup::SHOP->name => SendType::PU->value,
                        default => SendType::DV->value
                    },
                    'cd_pickup_type' => Pickup::case(data_get($request, 'pickup_type'))?->value ?? Pickup::CAR->value,
                    'cd_payment_method' => '504100',
                    'dt_pickup_status' => now(),
                    'dt_order_status' => now(),
                    'dt_payment_status' => now(),
                ]));
                $orderList->saveOrFail();

                $orderPayment->update([
                    'at_pg_commission_rate' => $shop->at_pg_commission_rate,
                    'cd_commission_type' => $shop->cd_commission_type,
                    'at_commission_amount' => $shop->at_commission_amount,
                    'at_commission_rate' => $shop->at_commission_rate,
                    'at_sales_commission_rate' => $shop->at_sales_commission_rate,
                ]);


                if (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail) != SearchBizKindDetail::WASH) {
                    $virtualNumber = BizCall::autoMapping($user->ds_phone, $shop->partner->cd_biz_kind, $noOrder);
                    VirtualNumberService::insertVnsLog([
                        'virtual_number' => $virtualNumber['vn'],
                        'real_number' => $user->ds_phone,
                        'no_order' => $noOrder,
                        'yn_success' => match ($virtualNumber['rt']) {
                            0 => 'Y',
                            default => 'N'
                        },
                        'dt_use_start' => now(),
                        'fail_reason' => Arr::get($virtualNumber, 'rs'),
                    ]);
                    if ($virtualNumber['rt'] != 0) {
                        throw new OwinException($virtualNumber['rs']);
                    }

                    $orderList->update([
                        'ds_safe_number' => $virtualNumber['vn'],
                    ]);
                }

                if (SearchBizKind::getBizKind($shop->partner->cd_biz_kind) == SearchBizKind::WASH) {
                    if (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail) == SearchBizKindDetail::WASH) {
                        $orderCommissionBody = [
                            'no_order' => $noOrder,
                            'no_user' => $user->no_user,
                            'no_shop' => $shop->no_shop,
                            'cd_biz_kind' => $shop->partner->cd_biz_kind,
                            'cd_pg' => $shop->cd_pg,
                            'at_pg_commission_rate' => $shop->at_pg_commission_rate ?? 0,

                            'at_price' => $request['at_price_total'],
                            'at_cpn_disct' => $request['at_cpn_disct'],
                            'at_price_pg' => $pgPrice,
                            'yn_pg_commission_out' => 'Y',

                            'no_commission' => 0, //매장등록 정산번호
                            'at_commission' => 0, // 세차장 세팅된 수수료율 % (사용하는 쿠폰이 있을경우 만 수수료 세팅)
                            'at_apply_price' => 0, //수수료적용 기준 초가금액
                            'at_owin_commission' => 0,
                            'at_owin_vat' => 0,
                            'at_pg_commission' => 0,
                            'at_pg_vat' => 0,
                            'at_cpn_disct_pg_commit' => 0,
                            'at_cpn_disct_pg_vat' => 0,
                            'at_price_for_shop' => 0,
                        ];

                        if ($pgPrice > 0) {
                            if ($request['at_cpn_disct'] > 0) {
                                $coupon = MemberWashCoupon::where([
                                    'no_user' => $user->no_user,
                                    'no' => data_get($request, 'discount_info.coupon.no'),
                                ])->with(['washCoupon'])->first();

                                if ($coupon && $coupon->cd_issue_kind != '131300') {
                                    $cpnOrderAtPrice = $coupon->at_price;
                                    $commissionInfo = $shop->washCommissions->where('at_create_min_price', '<=', $cpnOrderAtPrice)
                                        ->where('at_create_max_price', '>=', $cpnOrderAtPrice)->first();

                                    if ($commissionInfo) {
                                        $orderCommissionBody['no_commission'] = $commissionInfo->no;

                                        // 적용기준 주유금액 보다  실주유금액 이상일 경우 수수료 부과
                                        if ($commissionInfo->at_standard_price <= $cpnOrderAtPrice) {
                                            // 정산정보에 적용된 수수료율
                                            $orderCommissionBody['at_commission'] = $commissionInfo->at_commission;
                                            $orderCommissionBody['at_apply_price'] = $cpnOrderAtPrice - $commissionInfo->at_standard_price;

                                            $orderCommissionBody['at_owin_commission'] = ceil(($orderCommissionBody['at_apply_price'] * $commissionInfo->at_commission) / 100);
                                            $atOwinVatOrg = ($orderCommissionBody['at_apply_price'] * ($orderCommissionBody['at_commission'] / 10)) / 100;
                                            if (is_float($atOwinVatOrg) == null) { // 실수값 확인
                                                $orderCommissionBody['at_owin_vat'] = floor($atOwinVatOrg * 100) / 100;
                                            } else {
                                                $orderCommissionBody['at_owin_vat'] = floor($atOwinVatOrg);
                                            }

                                        }
                                    }
                                }
                            }

                            // PG수수료율 적용된 수수료 - 소수점 있을시에 버림 처리
                            $atPgCommissionOrg = ($orderCommissionBody['at_price_pg'] * $orderCommissionBody['at_pg_commission_rate'])/ 100;
                            $atPgCommissionOrg = floatval(sprintf("%.0f",$atPgCommissionOrg));

                            if (is_float($atPgCommissionOrg) == null) { // 실수값 확인
                                $orderCommissionBody['at_pg_commission'] = floor($atPgCommissionOrg * 100) / 100;
                            } else {
                                $orderCommissionBody['at_pg_commission'] = floor($atPgCommissionOrg);
                            }

                            // PG수수료 부가세 정보 - 소수점 있을시에 버림 처리
                            $atPgVatOrg = ($orderCommissionBody['at_pg_commission'] * 10) / 100;
                            if (is_float($atPgVatOrg) == null) {
                                $orderCommissionBody['at_pg_vat'] = floor($atPgVatOrg * 100) / 100;
                            } else {
                                $orderCommissionBody['at_pg_vat'] = floor($atPgVatOrg);
                            }

                            // 쿠폰수수료 - 미적용 [추가 개발필요]
                            $orderCommissionBody['at_cpn_disct_pg_commit'] = 0;
                            $orderCommissionBody['at_cpn_disct_pg_vat'] = 0;

                            // 실결제금액 기준 매장 정산금액
                            $owinCommission = $orderCommissionBody['at_owin_commission'] + $orderCommissionBody['at_owin_vat'];
                            $pgCommission = $orderCommissionBody['at_pg_commission'] + $orderCommissionBody['at_pg_vat'];
                            $cpnCommission = $orderCommissionBody['at_cpn_disct_pg_commit']+ $orderCommissionBody['at_cpn_disct_pg_vat'];
                            $orderCommissionBody['at_price_for_shop'] = $orderCommissionBody['at_price_pg'] - ($owinCommission + $pgCommission + $cpnCommission);
                        }

                        $orderWashCommission = new OrderWashCommission($orderCommissionBody);
                        $orderWashCommission->saveOrFail();
                    }

                    $memberShopWashLog = new MemberShopWashLog([
                        'no_user' => $user->no_user,
                        'no_shop' => $shop->no_shop,
                        'no_order' => $noOrder,
                        'cd_alarm_event_type' => '619100'
                    ]);
                    $memberShopWashLog->saveOrFail();
                }

                (new OrderLocation([
                    'no_order' => $noOrder
                ]))->saveOrFail();

                $noOrderProduct = time() . mt_rand(1000, 9999);

                match (SearchBizKind::getBizKind($shop->partner->cd_biz_kind)) {
                    SearchBizKind::FNB => $this->setFnbOrderProduct(
                        $noOrder,
                        $user->no_user,
                        $shop->partner->nm_partner . ' ' . $shop->nm_shop,
                        $noOrderProduct,
                        $request,
                        $verify['shopProducts']
                    ),
                    SearchBizKind::RETAIL => $this->setRetailOrderProduct(
                        $noOrder,
                        $user->no_user,
                        $noOrderProduct,
                        $request,
                        $verify['shopProducts']
                    ),
                    SearchBizKind::OIL => '',
                    SearchBizKind::WASH => $this->setWashOrderProduct(
                        $noOrder,
                        $user->no_user,
                        $shop->partner->nm_partner . ' ' . $shop->nm_shop,
                        $noOrderProduct,
                        $request,
                        $verify['shopProducts'],
                        $shop
                    ),
                    default => throw new OwinException(Code::message('9910'))
                };

                $alarmLog = [
                    'cd_alarm_event_type' => '607000',
                    'no_order' => $noOrder,
                    'no_shop' => $shop->no_shop,
                    'no_user' => $user->no_user
                ];
                match (SearchBizKind::getBizKind($shop->partner->cd_biz_kind)) {
                    SearchBizKind::FNB => (new OrderAlarmEventLog($alarmLog))->saveOrFail(),
                    SearchBizKind::RETAIL => (new MemberShopRetailLog($alarmLog))->saveOrFail(),
                    default => null
                };

                if (SearchBizKind::getBizKind($shop->partner->cd_biz_kind) == SearchBizKind::RETAIL) {
                    $atPriceExceptSend = $request['at_price_total'] - data_get($request, 'at_send_price');
                    $retailCommission = [
                        'cd_calculate_main' => data_get($verify, 'coupon.cd_calculate_main') ,
                        'at_commission_rate' => $atCommissionRate,
                        'at_target_price' => $atPriceExceptSend,
                        'at_cpn_disct_cu_self' => 0,
                        'at_cpn_disct_cu' => 0,
                        'at_cpn_disct_owin' => 0,
                        'at_cpn_disct_owin_commit' => 0,
                        'at_cpn_disct_owin_vat' => 0,
                        'at_owin_commission' => 0,
                        'at_owin_vat' => 0,
                        'at_commission_total' => 0,
                        'at_price_for_shop' => 0
                    ];

                    if ($request['at_cpn_disct'] > 0) {
                        if ($shop->partner->cd_calculate_main == '618200') {
                            $retailCommission['at_cpn_disct_cu'] = $request['at_cpn_disct'];
                            $retailCommission['at_target_price'] = $atPriceExceptSend - $retailCommission['at_cpn_disct_cu'];
                        } else if ($shop->partner->cd_calculate_main == '618200') {
                            $retailCommission['at_cpn_disct_owin'] = $request['at_cpn_disct'];

                            if ($retailCommission['at_commission_rate'] > 0) {
                                $retailCommission['at_cpn_disct_owin_commit'] = ceil($retailCommission['at_cpn_disct_owin'] * $retailCommission['at_commission_rate']) / 100;
                                $retailCommission['at_cpn_disct_owin_vat'] = floor(($retailCommission['at_cpn_disct_owin_commit'] * 10) / 100);

                                if ($retailCommission['at_cpn_disct_owin_vat'] < 1) {
                                    $retailCommission['at_cpn_disct_owin_commit'] = $retailCommission['at_cpn_disct_owin_commit'] - 1;
                                    $retailCommission['at_cpn_disct_owin_vat'] = 1;
                                }
                            }
                        }
                    }

                    if ($retailCommission['at_commission_rate'] > 0) {
                        $atOwinCommission = (($pgPrice - data_get($request, 'at_send_price')) * $retailCommission['at_commission_rate']) / 100;
                        $retailCommission['at_owin_commission'] = ceil($atOwinCommission);  // PG결제금액 * 수수료율
                        // 부가세 버림
                        $retailCommission['at_owin_vat'] = floor(($atOwinCommission * 10) / 100); // 수수료율 10% - 부가세율

                        // 부가세버림 후 부가세가 0일경우 > 부가세 + 1 , 수수료-1 처리
                        if ($retailCommission['at_owin_vat'] < 1) {
                            $retailCommission['at_owin_commission'] = $retailCommission['at_owin_commission'] - 1;
                            $retailCommission['at_owin_vat'] = 1;
                        }

                        # 수수료총합
                        $retailCommission['at_commission_total'] = $retailCommission['at_cpn_disct_owin_commit'] + $retailCommission['at_cpn_disct_owin_vat'] + $retailCommission['at_owin_commission'] + $retailCommission['at_owin_vat'];
                        # 지금할 정산금액 ( 정산대상금액 - 수수료합계)
                        $retailCommission['at_price_for_shop'] = $retailCommission['at_target_price'] - $retailCommission['at_commission_total'];

                        (new OrderRetailCommission([
                            'no_order' => $noOrder,
                            'no_user' => $user->no_user,
                            'no_partner' => $shop->no_partner,
                            'no_shop' => $shop->no_shop,
                            'cd_biz_kind' => $shop->partner->cd_biz_kind,
                            'cd_pg' => $shop->cd_pg,
                            'at_send_price' => data_get($request, 'at_send_price'),
                            'at_price' => $request['at_price_total'],
                            'at_price_pg' => $pgPrice,
                            'at_cpn_disct' => $request['at_cpn_disct']
                        ] + $retailCommission))->saveOrFail();
                    }
                    $response = Cu::client(path: sprintf(env('CU_API_PATH_ORDER'), $noOrder), method: 'GET');

                    if ($response['result_code'] != '0000') {
                        throw new OwinException(Code::message('P2814'));
                    }
                }

                if (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail) == SearchBizKindDetail::SPC) {
                    $spcOrder = Spc::order($noOrder);

                    OrderList::where('no_order', $noOrder)->update([
                        'ds_spc_order' => empty(data_get($spcOrder, 'providerOrderId')) == false && strlen(data_get($spcOrder, 'providerOrderId')) > 0 ? data_get($spcOrder, 'providerOrderId') : null
                    ]);
                    $verify['shopProducts']->map(function (Product $product) use ($shop) {
                        Cache::delete(sprintf(env('SPC_STOCK_CACHE_KEY'), $shop->partner->cd_spc_brand, $shop->cd_spc_store, $product->no_partner_category));
                        Cache::delete(sprintf(env('SPC_PRODUCT_STOCK_CACHE_KEY'), $shop->partner->cd_spc_brand, $shop->cd_spc_store, $product->cd_spc));
                    });
                }
            }
        } catch (Throwable $t) {
            Log::channel('error')->critical($t->getMessage(), [$t->getFile(), $t->getLine(), $t->getTraceAsString()]);
            Log::channel('slack')->critical(env('APP_ENV'), [
                'exception' => $t::class,
                'message' => $t->getMessage(),
                'time' => now()
            ]);

//            카드결제 취소
            $this->refund(
                $user,
                $shop,
                $noOrder,
                '601900',
                $cdPg->name,
                Code::message('PG9999')
            );

            return [
                'result' => false,
                'no_order' => $noOrder,
                'nm_order' => $nmOrder,
                'pg_msg' => $t->getMessage(),
                'msg' => Code::message('P2010')
            ];
        }

//        결제 성공
        return match ($paymentInfo['res_cd']) {
            '0000' => [
                'result' => true,
                'no_order' => $noOrder,
                'nm_order' => $nmOrder,
                'pg_msg' => $paymentInfo['res_msg'],
                'msg' => Code::message('P2024')
            ],
            default => [
                'result' => false,
                'no_order' => $noOrder,
                'nm_order' => $nmOrder,
                'pg_msg' => $paymentInfo['res_msg'],
                'msg' => Code::message('P2010')
            ]
        };
    }

    /**
     * @param string $noOrder
     * @param int $noUser
     * @param string $nmShop
     * @param string $noOrderProduct
     * @param Collection $request
     * @param Collection $shopProducts
     * @return void
     * @throws Throwable
     */
    public function setFnbOrderProduct(
        string $noOrder,
        int $noUser,
        string $nmShop,
        string $noOrderProduct,
        Collection $request,
        Collection $shopProducts
    ): void {
        if ($request['at_cpn_disct'] > 0) {
            (new CouponService())->usedMemberCoupon(
                $noOrder,
                data_get($request, 'discount_info.coupon.no'),
                $nmShop
            );
        }

        foreach ($request['list_product'] as $key => $product) {
            $optionPrice = collect(data_get($product, 'option'))->map(function ($option) {
                return $option['add_price'] * ($option['ea'] ?? 1);
            })->sum();
            $orderProduct = (new OrderProduct([
                'no_order_product' => $noOrderProduct . str_pad(
                        (string)($key + 1),
                        4,
                        '0',
                        STR_PAD_LEFT
                    ),
                'no_order' => $noOrder,
                'no_product' => $product['no_product'],
                'nm_product' => $shopProducts->firstWhere('no_product', $product['no_product'])->nm_product,
                'at_price' => ($product['at_price'] + $optionPrice) * $product['ea'],
                'at_price_product' => $product['at_price'],
                'at_price_option' => $optionPrice,
                'ct_inven' => $product['ea'],
                'no_user' => $noUser
            ]));
            $orderProduct->saveOrFail();

            if (empty(data_get($product, 'option')) === false) {
                $setOption = [];
                foreach (data_get($product, 'option') as $optionKey => $option) {
                    if ($optionKey < 5) {
                        $setOption['no_sel_group' . ($optionKey + 1)] = $option['no_option_group'];
                        $setOption['no_sel_option' . ($optionKey + 1)] = $option['no_option'];
                        $setOption['no_sel_price' . ($optionKey + 1)] = $option['add_price'] * ($option['ea'] ?? 1);
                    }

                    $product['option'][$optionKey]['nm_option_group'] = $shopProducts->firstWhere('no_product', $product['no_product'])
                        ?->productOptionGroups?->firstWhere('no_group', $option['no_option_group'])?->nm_group;
                    $product['option'][$optionKey]['nm_option'] = $shopProducts->firstWhere('no_product', $product['no_product'])
                        ?->productOptionGroups?->firstWhere('no_group', $option['no_option_group'])
                        ?->productOptions?->firstWhere('no_option', $option['no_option'])?->nm_option;

                    $product['option'][$optionKey]['cd_spc'] = $shopProducts->firstWhere('no_product', $product['no_product'])
                        ?->productOptionGroups?->firstWhere('no_group', $option['no_option_group'])
                        ?->productOptions?->firstWhere('no_option', $option['no_option'])?->cd_spc;

                    $product['option'][$optionKey]['yn_cup_deposit'] = $shopProducts->firstWhere('no_product', $product['no_product'])
                            ?->productOptionGroups?->firstWhere('no_group', $option['no_option_group'])
                            ?->productOptions?->firstWhere('no_option', $option['no_option'])?->yn_cup_deposit ?? 'N';
                }
                $orderProduct->updateOrFail([
                    'options' => json_encode(
                        data_get($product, 'option'),
                        JSON_UNESCAPED_UNICODE
                    ),
                    ...$setOption
                ]);
            }
        }
    }

    /**
     * @param string $noOrder
     * @param int $noUser
     * @param string $noOrderProduct
     * @param Collection $request
     * @param Collection $shopProducts
     * @return void
     * @throws Throwable
     */
    public function setRetailOrderProduct(
        string $noOrder,
        int $noUser,
        string $noOrderProduct,
        Collection $request,
        Collection $shopProducts
    ): void {
        if ($request['at_cpn_disct'] > 0) {
            (new CouponService())->usedMemberRetailCoupon(
                $noOrder,
                data_get($request, 'discount_info.coupon.no'),
                $noUser
            );
        }

        foreach ($request['list_product'] as $key => $product) {
            $optionPrice = data_get($product, 'option.*.add_price') ?? [];
            $shopProduct = $shopProducts->firstWhere(
                'no_product',
                $product['no_product']
            );
            $atPrice = match (false) {
                empty($shopProduct->cd_discount_sale) => match (DiscountSale::tryFrom($shopProduct->cd_discount_sale) == DiscountSale::TWO_PLUS_ONE) {
                    true => match (data_get($product, 'discount_type')) {
                        'SINGLE', 'DOUBLE' => $product['at_price'],
                        default => null
                    },
                    default => $product['at_price']
                },
                default => $product['at_price']
            };
            $orderProduct = (new RetailOrderProduct([
                'no_order' => $noOrder,
                'no_order_product' => $noOrderProduct . sprintf(
                        '%04d',
                        $key + 1
                    ),
                'no_product' => $product['no_product'],
                'nm_product' => $shopProduct->nm_product,
                'at_price' => ($atPrice) * $product['ea'],
                'at_price_product' => $atPrice,
                'at_price_option' => match (false) {
                    empty($shopProduct->cd_discount_sale) => match (DiscountSale::tryFrom($shopProduct->cd_discount_sale)) {
                        DiscountSale::TWO_PLUS_ONE => match (data_get($product, 'discount_type') == 'DOUBLE') {
                            true => $atPrice + array_sum($optionPrice),
                            default => 0
                        },
                        DiscountSale::SET->value => array_sum($optionPrice),
                        default => 0
                    },
                    default => 0
                },
                'cd_discount_sale' => match (false) {
                    empty($shopProduct->cd_discount_sale) => match (DiscountSale::tryFrom($shopProduct->cd_discount_sale)) {
                        DiscountSale::TWO_PLUS_ONE => match (data_get($product, 'discount_type') == 'DOUBLE') {
                            true => $shopProduct->cd_discount_sale,
                            default => null
                        },
                        default => $shopProduct->cd_discount_sale
                    },
                    default => $shopProduct->cd_discount_sale
                },
                'ct_inven' => $product['ea']
            ]));
            $orderProduct->saveOrFail();
            if (empty(data_get($product, 'option')) === false) {
                foreach (data_get($product, 'option') as $option) {
                    $shopOption = $shopProduct->productOptionGroups->firstWhere(
                        'no_group',
                        $option['no_option_group']
                    )
                        ->productOptionProducts->firstWhere(
                            'no_option',
                            $option['no_option']
                        );
                    (new RetailOrderProductOption([
                        'no_order' => $noOrder,
                        'no_order_product' => $orderProduct->no_order_product,
                        'no_option' => $option['no_option'],
                        'no_product_opt' => $shopOption->no_product_opt,
                        'nm_product_opt' => $shopOption->nm_product_opt,
                        'at_price_opt' => $option['add_price']
                            * $product['ea'],
                        'at_price_product_opt' => $option['add_price'],
                        'ct_inven' => $product['ea'],
                    ]))->saveOrFail();
                }
            }
            switch ($shopProduct->cd_discount_sale) {
                case DiscountSale::ONE_PLUS_ONE->value:
                    (new RetailOrderProductOption([
                        'no_order' => $noOrder,
                        'no_order_product' => $orderProduct->no_order_product,
                        'no_option' => $orderProduct->no_product,
                        'no_product_opt' => $orderProduct->no_product,
                        'nm_product_opt' => $orderProduct->nm_product,
                        'at_price_opt' => 0,
                        'at_price_product_opt' => 0,
                        'ct_inven' => $product['ea'],
                    ]))->saveOrFail();
                    break;
                case DiscountSale::TWO_PLUS_ONE->value:
                    if (data_get($product, 'discount_type') == 'DOUBLE') {
                        (new RetailOrderProductOption([
                            'no_order' => $noOrder,
                            'no_order_product' => $orderProduct->no_order_product,
                            'no_option' => $orderProduct->no_product,
                            'no_product_opt' => $orderProduct->no_product,
                            'nm_product_opt' => $orderProduct->nm_product,
                            'at_price_opt' => ($atPrice)
                                * $product['ea'],
                            'at_price_product_opt' => $atPrice,
                            'ct_inven' => $product['ea']
                        ]))->saveOrFail();
                        (new RetailOrderProductOption([
                            'no_order' => $noOrder,
                            'no_order_product' => $orderProduct->no_order_product,
                            'no_option' => $orderProduct->no_product,
                            'no_product_opt' => $orderProduct->no_product,
                            'nm_product_opt' => $orderProduct->nm_product,
                            'at_price_opt' => 0,
                            'at_price_product_opt' => 0,
                            'ct_inven' => $product['ea']
                        ]))->saveOrFail();
                    }
                    break;
            }
        }
    }

    /**
     * @param string $noOrder
     * @param int $noUser
     * @param string $nmShop
     * @param string $noOrderProduct
     * @param Collection $request
     * @param Collection $shopProducts
     * @param Shop $shop
     * @return void
     * @throws Throwable
     */
    public function setWashOrderProduct(
        string $noOrder,
        int $noUser,
        string $nmShop,
        string $noOrderProduct,
        Collection $request,
        Collection $shopProducts,
        Shop $shop
    ): void {
        if ($request['at_cpn_disct'] > 0) {
            match (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail)) {
                SearchBizKindDetail::HANDWASH => (new CouponService())->usedMemberHandWashCoupon(
                    $noOrder,
                    data_get($request, 'discount_info.coupon.no'),
                    $nmShop
                ),
                default => (new CouponService())->usedMemberWashCoupon(
                    $noOrder,
                    data_get($request, 'discount_info.coupon.no'),
                    $noUser,
                    $nmShop
                )
            };
        }

        foreach ($request['list_product'] as $key => $product) {
            $optionPrice = data_get($product, 'option.*.add_price') ?? [];
            $orderProduct = (new OrderProduct([
                'no_order_product' => $noOrderProduct . str_pad(
                        (string)($key + 1),
                        4,
                        '0',
                        STR_PAD_LEFT
                    ),
                'no_order' => $noOrder,
                'no_product' => $product['no_product'],
                'nm_product' => $shopProducts->firstWhere('no_product', $product['no_product'])->nm_product,
                'at_price' => ($product['at_price'] + array_sum(
                            $optionPrice
                        )) * $product['ea'],
                'at_price_product' => $product['at_price'],
                'at_price_option' => array_sum($optionPrice),
                'ct_inven' => $product['ea']
            ]));
            $orderProduct->saveOrFail();
            if (empty(data_get($product, 'option')) === false) {
                $setOption = [];
                foreach (data_get($product, 'option') as $optionKey => $option) {
                    if ($optionKey < 5) {
                        $setOption['no_sel_group' . ($optionKey + 1)]
                            = $option['no_option'];
                        $setOption['no_sel_option' . ($optionKey + 1)]
                            = $option['no_option'];
                        $setOption['no_sel_price' . ($optionKey + 1)]
                            = $option['add_price'];
                    }

                    $product['option'][$optionKey]['nm_option_group'] = null;
                    $product['option'][$optionKey]['nm_option']
                        = $shopProducts->firstWhere(
                        'no_product',
                        $product['no_product']
                    )
                        ?->washOptions?->firstWhere(
                            'no_option',
                            $option['no_option']
                        )?->nm_option;
                }
                $orderProduct->updateOrFail([
                    'options' => json_encode(
                        data_get($product, 'option'),
                        JSON_UNESCAPED_UNICODE
                    ),
                    'no_user' => $noUser,
                    ...$setOption
                ]);
            }
        }
    }

    /**
     * @param User $user
     * @param Shop $shop
     * @param Collection $request
     *
     * @return array
     * @throws OwinException
     */
    public function verifyFnbOrder(
        User $user,
        Shop $shop,
        Collection $request
    ): array {
        $shopProducts = $shop->productIgnoreExcept()->with([
            'productOptionGroups.productOptions' => function ($query) use ($request) {
                $query->whereIn('no_option', data_get($request['list_product'], '*.option.*.no_option'));
            }
        ])->where([
            'product.no_partner' => $shop->no_partner,
            'product.ds_status' => EnumYN::Y->name
        ])->whereIn(
            'product.no_product',
            data_get($request['list_product'], '*.no_product')
        )->get();

//        상품 정상 주문 체크 (상품 테이블에 없는 상품 주문 체크)
        if (array_diff(
            data_get($request['list_product'], '*.no_product'),
            $shopProducts->pluck('no_product')->all()
        )
        ) {
            throw new OwinException(Code::message('P2045'));
        }

        $totalPrice = collect($request['list_product'])->map(function ($product) use ($shop, $shopProducts) {
            $checkProduct = $shopProducts->firstWhere('no_product', $product['no_product']);
//            상품 금액 체크
            if ($checkProduct->at_price != $product['at_price']) {
                throw new OwinException(Code::message('P2204'));
            }

//            상품 정상 주문 체크 (옵션 그룹 테이블에 없는 상품 주문 체크)
            $checkProduct->productOptionGroups->whereIn('no_group', $checkProduct->option_group)->whenNotEmpty(
                function ($group) use ($product) {
                    if (array_diff(data_get($product, 'option.*.no_option_group'), $group->pluck('no_group')->all())) {
                        throw new OwinException(Code::message('P2205'));
                    }
                }
            );

            $response = match (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail)) {
                SearchBizKindDetail::SPC => Spc::realProductStock($shop->partner->cd_spc_brand, $shop->cd_spc_store, $checkProduct),
                default => null
            };

//            필수옵션 체크
            $checkProduct->productOptionGroups->whereIn('no_group', $checkProduct->option_group)
                ->where('min_option_select', '>=', 1)->whenNotEmpty(function ($group) use ($product) {
                    if ($product['yn_cup_deposit'] == 'Y' && array_diff($group->pluck('no_group')->all(), data_get($product['option'], '*.no_option_group'))
                    ) {
                        throw new OwinException(Code::message('P2206'));
                    }
                });

//             옵션그룹 최대 /최소 체크
            $checkProduct->productOptionGroups->whereIn('no_group', data_get($product['option'], '*.no_option_group'))->map(function ($group) use ($product) {
                $optionCount = match ($group->option_type) {
                    'input' => match (collect($product['option'])->where('no_option_group', $group->no_group)->sum('ea')) {
                        0 => collect($product['option'])->where('no_option_group', $group->no_group)->count(),
                        default => collect($product['option'])->where('no_option_group', $group->no_group)->sum('ea')
                    },
                    default => collect($product['option'])->where('no_option_group', $group->no_group)->count()
                };
                if (
                    between($group->min_option_select ?? 1, $group->max_option_select ?? 1, $optionCount) === false
                ) {
                    throw new OwinException(Code::message('P2040'));
                }
            });

            if (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail) == SearchBizKindDetail::SPC) {
                collect($response)->where('code', $checkProduct['cd_spc'])
                    ->whenEmpty(function () use ($checkProduct) {
                        throw new OwinException(
                            sprintf(
                                Code::message('P2011'),
                                $checkProduct->nm_product
                            )
                        );
                    })->whenNotEmpty(function ($collect) use ($checkProduct, $product) {
                        // 상품 재고
                        if ($checkProduct['yn_check_stock'] == 'Y' && $checkProduct['no_product']
                            == $product['no_product']
                            && $product['ea'] > $collect->first()['qty']
                        ) {
                            throw new OwinException(sprintf(
                                    Code::message('P2011'),
                                    $checkProduct->nm_product
                                )
                            );
                        }
                    }
                );
            }

            $optionTotalPrice = collect($product['option'])->map(function ($options) use ($shop, $product, $shopProducts, $checkProduct, $response) {
                $option = $shopProducts->firstWhere('no_product', $product['no_product'])
                    ->productOptionGroups->firstWhere('no_group', $options['no_option_group'])
                    ->productOptions->where('no_option', $options['no_option'])->whenEmpty(function () {
//                        상품 정상 주문 체크 (옵션 테이블에 없는 상품 주문 체크)
                        throw new OwinException(Code::message('P2205'));
                    })->first();

                if (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail) == SearchBizKindDetail::SPC) {
                    collect($response)->where('code', $checkProduct['cd_spc'])
                        ->whenNotEmpty(function ($collect) use ($checkProduct, $option, $options) {
                            // 상품 옵션 재고
                            collect($collect->first()['options'])->where('code', $option['cd_spc'])->whenEmpty(function () {
                                throw new OwinException(Code::message('P2205'));
                            })->whenNotEmpty(function ($spcOption) use ($option, $checkProduct, $options) {
                                 if ($option['yn_check_stock'] == 'Y' && (!$spcOption->first()['qty'] || data_get($options, 'ea', 1) > $spcOption->first()['qty'])) {
                                     $optionGroup = $checkProduct->productOptionGroups->firstWhere('no_group', $option['no_group']);
                                     throw new OwinException(sprintf(Code::message('P2011'), $checkProduct->nm_product . '-(' . $optionGroup['nm_group'] . ')' . $option['nm_option']));
                                 }
                            }
                            );
                        });
                }
//                옵션 금액 체크
                if ($option->yn_cup_deposit == 'Y' && empty($shop->at_cup_deposit) == false && $shop->at_cup_deposit > 0 && $shop->at_cup_deposit != $options['add_price']) {
                    throw new OwinException(Code::message('P2207'));
                } elseif ($option->yn_cup_deposit == 'N' && $option->at_add_price != $options['add_price'])  {
                    throw new OwinException(Code::message('P2207'));
                }

//                컵 보증금 금액은 옵션의 가격 정보가 아닌 매장의 at_cup_deposit 데이터를 사용하도록 변경
                return match ($option->yn_cup_deposit == 'Y' && empty($shop->at_cup_deposit) == false && $shop->at_cup_deposit > 0) {
                    true => $shop->at_cup_deposit,
                    default => $option->at_add_price * ($options['ea'] ?? 1),
                };
            })->sum();
            return ($checkProduct->at_price + $optionTotalPrice) * $product['ea'];
        })->sum();

        $atSendPrice = match (Pickup::case(data_get($request, 'pickup_type')) == Pickup::SHOP) {
            true => 0,
            default => data_get($request, 'at_send_price') - max(data_get($request, 'at_send_disct'), data_get($request, 'at_send_sub_disct'))
        };

        $sendPrice = $shop->at_order_send_price;
        $sendDisct = min($shop->at_order_send_price, data_get($shop, 'at_send_disct', 0));

        $atSendDisct = match (Pickup::case(data_get($request, 'pickup_type'))) {
            Pickup::CAR => match (!empty($user->useSubscription?->benefit?->{BenefitType::SEND->name})) {
                true => match (BenefitDetailType::saleUse($user->useSubscription?->benefit?->{BenefitType::SEND->name}?->type)) {
                    true => (function () use ($user, $shop, $request, $sendPrice) {
                        $percent = $user->useSubscription?->benefit->{BenefitType::SEND->name}->{BenefitDetailType::SALE->name}->price * 0.01;
                        $atSendDisct = floor(($sendPrice * $percent) * 0.1) * 10;
                        if ($atSendDisct != data_get($request, 'at_send_sub_disct')) {
                            throw new OwinException(Code::message('P2204'));
                        }
                        return $atSendDisct;
                    })(),
                    default => $sendDisct
                },
                default => $sendDisct
            },
            default => 0,
        };

        if (Pickup::case(data_get($request, 'pickup_type')) == Pickup::CAR
            && (
                $sendPrice != data_get($request, 'at_send_price')
                || ($atSendDisct != data_get($request, 'at_send_disct') && $atSendDisct != data_get($request, 'at_send_sub_disct'))
                || $atSendPrice != $sendPrice - $atSendDisct
            )) {
            throw new OwinException(Code::message('P2204'));
        }

        $atDisct = match (BenefitDetailType::saleUse($user->useSubscription?->benefit->{BenefitType::FNB->name}->type)) {
            true => (function () use ($user, $request) {
                $percent = $user->useSubscription?->benefit->{BenefitType::FNB->name}->{BenefitDetailType::SALE->name}->price * 0.01;
                $price = $request['at_price_total'] + data_get($request, 'at_commission_rate') - $request['at_cpn_disct'];
                $atDisct = floor(($price * $percent) * 0.1) * 10;
                if ($atDisct != data_get($request, 'at_disct')) {
                    throw new OwinException(Code::message('P2204'));
                }
                return $atDisct;
            })(),
            default => 0
        };

//        상품 금액 체크
        if (
            $totalPrice != $request['at_price_total']
            || $request['at_price_calc'] != $totalPrice + data_get($request, 'at_commission_rate') - ($request['at_cpn_disct'] + $atDisct) + $atSendPrice
        ) {
            throw new OwinException(Code::message('P2204'));
        }

        if (collect($request['list_product'])->where("yn_cup_deposit", 'Y')->sum('ea') * $shop->at_cup_deposit != $request['at_cup_deposit']) {
            throw new OwinException(Code::message('P2209'));
        }

//        pg 결제 최소 금액 체크
        if (between(1, Code::conf('pg_min_price') - 1, data_get($request, 'at_price_calc'))) {
            throw new OwinException(sprintf(Code::message('P2021'), Code::conf('pg_min_price')));
        }

//        쿠폰 체크
        if (empty(data_get($request, 'discount_info.coupon.no')) === false) {
            (new CouponService())->getFnbUsableCoupon($user->no_user, $shop->no_shop, (int)$totalPrice, collect($request['list_product']))
                ->where('no', Arr::get($request, 'discount_info.coupon.no'))
                ->whenEmpty(function () {
                    throw new OwinException(Code::message('P2300'));
                })->whenNotEmpty(function ($coupon) use ($request, $shopProducts) {
                    if (
                        $coupon->first()['at_discount'] != Arr::get($request, 'discount_info.coupon.at_coupon')
                        || $coupon->first()['at_discount'] != $request['at_cpn_disct']
                    ) {
                        throw new OwinException(Code::message('P2330'));
                    }

//                    상품증정 쿠폰 / 주문상품에 해당 쿠폰의 상품이 없을 경우 주문상품에 강제 추가
                    if (empty($coupon->first()['gift']) === false && $coupon->first()['at_discount'] == 0) {
                        $request['list_product'] = collect($request['list_product'])->push([
                            'no_product' => $coupon->first()['gift'],
                            'ea' => 1,
                            'at_price' => 0
                        ])->toArray();

                        $shopProducts->push(Product::where([
                            'no_product' => $coupon->first()['gift']
                        ])->first());
                    }
                });
        }

//        전달비 체크
        if ($shop->cd_commission_type == '205300' && $shop->at_commission_rate > 0) {
            if ($shop->at_commission_rate != data_get($request, 'at_commission_rate')) {
                throw new OwinException(Code::message('P2204'));
            }
        }

//        픽업 시간 체크
        $shopService = new ShopService();
        $arrivedTime = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $request['arrived_time']
        );
//         현재 시간 + 최소 준비시간 > 핍업시간일 경우
        $bizKindDetail = SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail);
        if ($bizKindDetail == SearchBizKindDetail::SPC
            && data_get($request, 'pickup_type') == Pickup::CAR->name
            && $arrivedTime->format('Y-m-d H:i:s') < now()->addMinutes($shop->at_make_ready_time)
        ) {
            throw new OwinException(Code::message('P2050'));
        } elseif ($bizKindDetail != SearchBizKindDetail::SPC
            && $arrivedTime->format('Y-m-d H:i:s') < now()->addMinutes($shop->at_make_ready_time)) {
            throw new OwinException(Code::message('P2050'));
        }

//        매장 휴무일
        $shopService->getHoliday($shop->no_shop)->where(
            'holiday',
            $arrivedTime->format('Y-m-d')
        )
            ->where('break_start_time', '<=', $arrivedTime->format('H:i:s'))
            ->where('break_end_time', '>=', $arrivedTime->format('H:i:s'))
            ->whenNotEmpty(function () {
                throw new OwinException(Code::message('P2055'));
            });
//        매장 휴무일 break time
        $shopService->getOperate($shop->no_shop)->where(
            'day_of_week',
            $arrivedTime->dayOfWeek

        )
            ->where('ds_open_time', '<=', $arrivedTime->format('H:i:s'))
            ->where('ds_close_full_time', '>=', $arrivedTime->format('Y-m-d H:i:s'))
            ->whenEmpty(function () {
                throw new OwinException(Code::message('P2050'));
            })->whenNotEmpty(function ($opt) use ($arrivedTime) {
                if (
                    $opt->whereNotNull('break1.type')
                        ->where('break1.start_time', '<=', $arrivedTime->format('Hi'))
                        ->where('break1.end_time', '>=', $arrivedTime->format('Hi'))
                        ->count() > 0
                    || $opt->whereNotNull('break2.type')
                        ->where('break2.start_time', '<=', $arrivedTime->format('Hi'))
                        ->where('break2.end_time', '>=', $arrivedTime->format('Hi'))
                        ->count() > 0
                ) {
                    throw new OwinException(Code::message('P2054'));
                }
            });
//        카드 체크
        $card = $user->memberCard->where('no_card', $request['no_card'])
            ->where('cd_pg', $shop->cd_pg)->whenEmpty(function () {
                throw new OwinException(Code::message('P1020'));
            })->first();

        return [
            'shopProducts' => $shopProducts,
            'card' => $card
        ];
    }

    /**
     * @param User $user
     * @param Shop $shop
     * @param Collection $request
     * @return array
     * @throws OwinException
     * @throws GuzzleException
     */
    public function verifyRetailOrder(
        User $user,
        Shop $shop,
        Collection $request
    ): array {
        $shopProducts = RetailProduct::with([
            'productOptionGroups.productOptionProducts' => function ($query) use ($request) {
                $query->whereIn('no_option', data_get($request['list_product'], '*.option.*.no_option'));
            }
        ])->whereIn('no_product', data_get($request['list_product'], '*.no_product'))
            ->orderByDesc('no_product')->get();

//        상품 정상 주문 체크 (상품 테이블에 없는 상품 주문 체크)
        if (array_diff(data_get($request['list_product'], '*.no_product'), $shopProducts->pluck('no_product')->all())) {
            throw new OwinException(Code::message('P2045'));
        }

        $response = Cu::realStock($shop->store_cd, $shopProducts);
        $totalPrice = collect($request['list_product'])->map(function ($product) use ($shop, $shopProducts, $response) {
            $checkProduct = $shopProducts->firstWhere('no_product', $product['no_product']);

//            상품 금액 체크
            $multi = match (data_get($product, 'discount_type')) {
                'DOUBLE' => 2,
                default => 1
            };
            $atPrice = $checkProduct->at_price * $multi;
            if ($atPrice != ($product['at_price'] * $multi)) {
                throw new OwinException(Code::message('P2204'));
            }

//            단품 체크
            if (data_get($product, 'discount_type') == 'SINGLE'
                && $product['ea'] > 1
            ) {
                throw new OwinException(Code::message('P2026'));
            }

//            상품 정상 주문 체크 (옵션 그룹 테이블에 없는 상품 주문 체크)
            $checkProduct->productOptionGroups->whenNotEmpty(
                function ($group) use ($product) {
                    if (array_diff(
                        data_get($product, 'option.*.no_option_group'),
                        $group->pluck('no_group')->all()
                    )
                    ) {
                        throw new OwinException(Code::message('P2205'));
                    }
                }
            );

//            필수옵션 체크
            $checkProduct->productOptionGroups->where(
                'cd_option_type',
                OptionType::REQUIRED->value
            )->whenNotEmpty(
                function ($group) use ($product) {
                    if (data_get($product, 'yn_cup_deposit') == 'Y' && array_diff(
                        $group->pluck('no_group')->all(),
                        data_get($product, 'option.*.no_option_group')
                    )
                    ) {
                        throw new OwinException(Code::message('P2206'));
                    }
                }
            );

//            옵션그룹 최대 /최소 체크
            $checkProduct->productOptionGroups->whereIn('no_group', data_get($product, 'option.*.no_option_group'))->map(function ($group) use ($product) {
                if (collect($product['option'])->where('no_option_group', $group->no_group)->count() < 1) {
                    throw new OwinException(Code::message('P2040'));
                }
            });
//            상품 정상 주문 체크 (편의점 상품의 재고 체크)
            if ($checkProduct->cd_discount_sale != DiscountSale::SET->value && $product['ea'] > data_get($response->firstWhere('no_barcode', $checkProduct->no_barcode), 'cnt_product', 0)) {
                throw new OwinException(sprintf(Code::message('P2011'), $checkProduct->nm_product));
            }

            $optionTotalPrice = collect(data_get($product, 'option'))->map(function ($options) use ($product, $checkProduct, $response) {
                    $option = $checkProduct->productOptionGroups->firstWhere('no_group', $options['no_option_group'])
                        ->productOptionProducts->where('no_option', $options['no_option'])->whenEmpty(function () {
//                        상품 정상 주문 체크 (옵션 테이블에 없는 상품 주문 체크)
                            throw new OwinException(Code::message('P2205'));
                        })->whenNotEmpty(function ($option) use ($product, $checkProduct, $response) {
//                        상품 정상 주문 체크 (편의점 상품의 재고 체크)
                            if ($product['ea'] > data_get(data_get($response->firstWhere('no_barcode', $checkProduct->no_barcode), 'option')?->firstWhere('no_barcode', $option->first()->no_barcode_opt), 'cnt_product', 0)) {
                                throw new OwinException(sprintf(Code::message('P2011'), $checkProduct->nm_product . ' ' . $option->first()->nm_product_opt));
                            }
                        })->first();

//                        상품 옵션 금액 체크
                    if ($option->at_price_opt != $options['add_price']) {
                        throw new OwinException(Code::message('P2204'));
                    }

                return $option->at_price_opt;
            }
            )->sum();
            Cache::delete(sprintf(env('CU_STOCK_CACHE_KEY'), $shop->store_cd, $checkProduct->no_category));

            return ($atPrice + $optionTotalPrice) * $product['ea'];
        })->sum();

//        상품 금액 체크

        $atSendPrice = match (Pickup::case(data_get($request, 'pickup_type')) == Pickup::SHOP) {
            true => 0,
            default => match (!empty(data_get($request, 'at_send_disct'))) {
                true => data_get($request, 'at_send_price') - data_get($request, 'at_send_disct'),
                default => data_get($request, 'at_send_price'),
            }
        };

        $sendDisct = min($shop->at_order_send_price, data_get($shop, 'at_send_disct', 0));
        $atSendDisct = match (Pickup::case(data_get($request, 'pickup_type'))) {
            Pickup::CAR => $sendDisct,
            default => 0,
        };

        if (Pickup::case(data_get($request, 'pickup_type')) == Pickup::CAR
            && (
                $shop->at_order_send_price != data_get($request, 'at_send_price')
                || ($atSendDisct != data_get($request, 'at_send_disct') && $atSendDisct != data_get($request, 'at_send_sub_disct'))
                || $atSendPrice != $shop->at_order_send_price - $atSendDisct
            )) {
            throw new OwinException(Code::message('P2204'));
        }

        if (
            $totalPrice != $request['at_price_total']
            || $request['at_price_calc'] != $totalPrice + data_get($request, 'at_commission_rate') - $request['at_cpn_disct'] + $atSendPrice
        ) {
            throw new OwinException(Code::message('P2204'));
        }

        if ($shop->at_min_order > $totalPrice + $atSendPrice) {
            throw new OwinException(
                sprintf(Code::message('P2023'), $shop->at_min_order)
            );
        }
//        pg 결제 최소 금액 체크
        if (between(
            1,
            Code::conf('pg_min_price') - 1,
            $totalPrice + data_get($request, 'at_send_price')
            - $request['at_cpn_disct']
        )
        ) {
            throw new OwinException(sprintf(Code::message('P2021'), Code::conf('pg_min_price')));
        }

//        쿠폰 체크
        $coupon = match (empty(data_get($request, 'discount_info.coupon.no')) === false) {
            true => (new CouponService())->getRetailUsableCoupon($user->no_user, $shop->no_shop, (int)$totalPrice)
                ->where('no', Arr::get($request, 'discount_info.coupon.no'))
                ->whenEmpty(function () {
                    throw new OwinException(Code::message('P2300'));
                })->whenNotEmpty(function ($coupon) use ($request) {
                    if (
                        $coupon->first()['at_discount'] != Arr::get(
                            $request,
                            'discount_info.coupon.at_coupon'
                        )
                        || $coupon->first()['at_discount']
                        != $request['at_cpn_disct']
                    ) {
                        throw new OwinException(Code::message('P2330'));
                    }
                })->first(),
            default => null
        };

//        픽업 시간 체크
        $shopService = new ShopService();
        $arrivedTime = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $request['arrived_time']
        );
//         현재 시간 + 최소 준비시간 > 핍업시간일 경우
        if ($arrivedTime->format('Y-m-d H:i:s') < now()->addMinutes(
                $shop->at_make_ready_time
            )
        ) {
            throw new OwinException(Code::message('P2050'));
        }
//        매장 휴무일
        $shopService->getHoliday($shop->no_shop)->where(
            'holiday',
            $arrivedTime->format('Y-m-d')
        )
            ->where('break_start_time', '<=', $arrivedTime->format('H:i:s'))
            ->where('break_end_time', '>=', $arrivedTime->format('H:i:s'))
            ->whenNotEmpty(function () {
                throw new OwinException(Code::message('P2055'));
            });
//        매장 휴무일 break time
        $shopService->getOperate($shop->no_shop)
            ->where('day_of_week', $arrivedTime->dayOfWeek)
            ->where('ds_open_time', '<=', $arrivedTime->format('H:i:s'))
            ->where('ds_close_full_time', '>=', $arrivedTime->format('Y-m-d H:i:s'))
            ->whenEmpty(function () {
                throw new OwinException(Code::message('P2050'));
            })->whenNotEmpty(function ($opt) use ($arrivedTime) {
                if (
                    $opt->whereNotNull('break1.type')
                        ->where('break1.start_time', '<=', $arrivedTime->format('Hi'))
                        ->where('break1.end_time', '>=', $arrivedTime->format('Hi'))
                        ->count() > 0
                    || $opt->whereNotNull('break2.type')
                        ->where('break2.start_time', '<=', $arrivedTime->format('Hi'))
                        ->where('break2.end_time', '>=', $arrivedTime->format('Hi'))
                        ->count() > 0
                    || $opt->where('ds_open_order_time', '<=', $arrivedTime->format('H:i:s'))
                        ->where('ds_close_order_time', '>=', $arrivedTime->format('H:i:s'))
                        ->count() <= 0
                ) {
                    throw new OwinException(Code::message('P2054'));
                }
            });
//        카드 체크
        $card = $user->memberCard->where('no_card', $request['no_card'])
            ->where('cd_pg', $shop->cd_pg)->whenEmpty(function () {
                throw new OwinException(Code::message('P1020'));
            })->first();

        return [
            'shopProducts' => $shopProducts,
            'card' => $card,
            'coupon' => $coupon
        ];
    }

    public function verifyWashOrder(
        User $user,
        Shop $shop,
        Collection $request
    ): array {
        $carInfo = $user->memberCarInfoAll->where(
            'ds_car_number',
            $request['car_number']
        )->whenEmpty(function () {
            throw new OwinException(Code::message('M1510'));
        })->first();

        $shopProducts = match (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail)) {
            SearchBizKindDetail::HANDWASH => WashHandProduct::where([
                'yn_status' => 'Y',
                'no_partner' => $shop->no_partner
            ])->whereIn(
                'no_product',
                data_get($request['list_product'], '*.no_product')
            )->with([
                'prices' => function ($q) use ($carInfo) {
                    $q->where('cd_wash_carnpeople', $carInfo->carList->cd_wash_carnpeople);
                }
            ])->get()->whenEmpty(function () {
                throw new OwinException(Code::message('P2040'));
            }),
            default => WashProduct::where([
                'yn_status' => 'Y',
                'no_shop' => $shop['no_shop'],
            ])->whereIn(
                'no_product',
                data_get($request['list_product'], '*.no_product')
            )->with([
                'washOptions' => function ($q) use ($request) {
                    $q->whereIn('no_option', data_get($request['list_product'], '*.option.*.no_option') ?? []);
                }
            ])->get()->whenEmpty(function () {
                throw new OwinException(Code::message('P2040'));
            })->map(function ($collect) use ($carInfo) {
                //차종이 다를 경우
                if ($collect['cd_car_kind'] != $carInfo->carList->cd_car_kind) {
                    throw new OwinException(Code::message('P2040'));
                }
                return $collect;
            })
        };

        $totalPrice = collect($request['list_product'])->map(
            function ($product) use ($shop, $shopProducts) {
                $checkProduct = $shopProducts->firstWhere(
                    'no_product',
                    $product['no_product']
                );

                return match (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail)) {
                    SearchBizKindDetail::HANDWASH => $checkProduct->prices->first()->at_price,
                    default => $checkProduct->at_price
                        + $checkProduct->washOptions->pluck('at_price')->sum()
                };
            }
        )->sum();

        if ($totalPrice != $request['at_price_total']) {
            throw new OwinException(Code::message('P2204'));
        }

        if ($shop->at_min_order > $totalPrice) {
            throw new OwinException(
                sprintf(Code::message('P2023'), $shop->at_min_order)
            );
        }

//        pg 결제 최소 금액 체크
        if (between(1, Code::conf('pg_min_price') - 1, $totalPrice - $request['at_cpn_disct'])) {
            throw new OwinException(sprintf(Code::message('P2021'), Code::conf('pg_min_price')));
        }

        if (empty(data_get($request, 'discount_info.coupon.no')) === false) {
            $coupons = match (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail)) {
                SearchBizKindDetail::HANDWASH => (new CouponService())->getHandWashUsableCoupon(
                    $user->no_user,
                    $shop->no_shop,
                    (int)$totalPrice,
                    collect($request['list_product'])
                ),
                default => (new CouponService())->getWashUsableCoupon(
                    $user->no_user,
                    $shop->no_shop,
                    (int)$totalPrice,
                )
            };

            $coupons->where('no', Arr::get($request, 'discount_info.coupon.no'))
                ->whenEmpty(function () {
                    throw new OwinException(Code::message('P2300'));
                })->whenNotEmpty(function ($coupon) use ($request) {
                    if (
                        $coupon->first()['at_discount'] != Arr::get($request, 'discount_info.coupon.at_coupon')
                        || $coupon->first()['at_discount'] != $request['at_cpn_disct']
                    ) {
                        throw new OwinException(Code::message('P2330'));
                    }
                });
        }

        $atDisct = match (BenefitDetailType::saleUse($user->useSubscription?->benefit->{BenefitType::WASH->name}->type)) {
            true => (function () use($user, $request) {
                $percent = $user->useSubscription?->benefit->{BenefitType::WASH->name}->{BenefitDetailType::SALE->name}->price * 0.01;
                $price = $request['at_price_total'] - $request['at_cpn_disct'];
                $atDisct = floor(($price * $percent) * 0.1) * 10;
                if ($atDisct != data_get($request, 'at_disct')) {
                    throw new OwinException(Code::message('P2204'));
                }

                return $atDisct;
            })(),
            default => 0
        };

        if ($request['at_price_calc'] != $totalPrice - ($request['at_cpn_disct'] + $atDisct)) {
            throw new OwinException(Code::message('P2204'));
        }

        if (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail) == SearchBizKindDetail::HANDWASH) {
            //출장 세차의 경우 3일 후 ~ 30일 전까지 예약 가능
           $arrivedTime = Carbon::createFromFormat('Y-m-d H:i:s', $request['arrived_time']);
           if ($arrivedTime < now()->addDays(2)->endOfDay()
               || $arrivedTime > now()->addDays(30)->endOfDay()) {
               throw new OwinException(Code::message('P2501'));
           }
        }

        $shopService = new ShopService();
//        매장 휴무일
        $shopService->getHoliday($shop->no_shop)->where(
            'holiday',
            Carbon::now()->format('Y-m-d')
        );
//        매장 휴무일 break time
        $shopService->getOperate($shop->no_shop)->where('day_of_week', Carbon::now()->dayOfWeek);

        //카드 체크
        $card = $user->memberCard->where('no_card', $request['no_card'])->where(
            'cd_pg',
            $shop->cd_pg
        )->whenEmpty(
            function () {
                throw new OwinException(Code::message('P1020'));
            }
        )->first();

        return [
            'shopProducts' => $shopProducts,
            'card' => $card
        ];
    }

    /**
     * @param User $user
     * @param Shop $shop
     * @param PgService $pg
     * @param string $noOrder
     * @param string $cdOrderStatus
     * @param string|null $reason
     * @return array
     * @throws GuzzleException
     * @throws OwinException
     * @throws Throwable
     */
    public function refund(
        User $user,
        Shop $shop,
        string $noOrder,
        string $cdOrderStatus,
        string $nmPg,
        string $reason = null,
    ): array {
        $orderList = OrderList::where([
            'no_order' => $noOrder
        ])->get()->whenEmpty(function () {
            throw new OwinException(Code::message('P2120'));
        })->first();
        if (in_array($orderList->cd_order_status, ['601900', '601950', '601999'])) {
            throw new OwinException(Code::message('P2401'));
        }

        $bizKind = SearchBizKind::getBizKind($shop->partner->cd_biz_kind);
        $bizKindDetail = SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail);
        if ($bizKind == SearchBizKindDetail::OIL || ($bizKind == SearchBizKind::FNB && $bizKindDetail == SearchBizKindDetail::SPC)) {
        } elseif ($bizKind == SearchBizKind::WASH && $bizKindDetail == SearchBizKindDetail::HANDWASH) {
            if ($orderList->dt_pickup->subDays(1)->format('Y-m-d 17:00:00') < now()) {
                throw new OwinException(Code::message('P2140'));
            }
        } else {
            if (
                ($orderList->cd_pickup_status == '602100'
                    || (
                        $orderList->cd_pickup_status == '602200'
                        && $orderList->at_add_delay_min > 0
                        && Carbon::createFromFormat(
                            'Y-m-d H:i:s',
                            $orderList->dt_pickup_status
                        )->addMinutes(800) > now()
                    )) === false
            ) {
                throw new OwinException(Code::message('P2140'));
            }
        }

        $response = match ($orderList->at_price_pg) {
            0 => [
                'res_cd'  => '0000',
                'res_msg' => Code::message('0000'),
            ],
            default => \App\Utils\Pg::refund([
                'nmPg' => $nmPg,
                'dsResOrderNo' => $orderList->orderPayment->ds_res_order_no,
                'price' => $orderList->at_price_pg,
                'reason' => $reason ?? Code::message('PG9999'),
                'noOrder' => $orderList->no_order,
                'dsServerReg' => $orderList->orderPayment->ds_server_reg,
                'nmOrder' => $orderList->nm_order
            ])
        };

        $orderList->orderPayment->update([
            'ds_res_code_refund' => $response['res_cd'],
            'cd_reject_reason' => $reason,
            'dt_req_refund' => now(),
            'dt_res_refund' => now()
        ]);

        if ($response['res_cd'] == '0000') {
            $orderList->update([
                'cd_pickup_status' => '602400',
                'cd_payment_status' => '603900',
                'cd_order_status' => $cdOrderStatus,
                'dt_order_status' => now(),
                'dt_pickup_status' => now(),
                'dt_payment_status' => now(),
            ]);

            (new OrderProcess([
                'no_order' => $orderList->no_order,
                'no_user' => $user->no_user,
                'no_shop' => $shop->no_shop,
                'cd_order_process' => '616991',
            ]))->saveOrFail();

            match (SearchBizKind::getBizKind($shop->partner->cd_biz_kind)) {
                SearchBizKind::FNB => (new CouponService())->refundMemberCoupon(
                $orderList->no_order,
                $user->no_user
                ),
                SearchBizKind::RETAIL => (new CouponService())->refundMemberRetailCoupon(
                    $orderList->no_order,
                    $user->no_user
                ),
                SearchBizKind::WASH => match (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail)) {
                    SearchBizKindDetail::HANDWASH => (new CouponService())->refundMemberHandWashCoupon($orderList->no_order, $user->no_user),
                    default => (new CouponService())->refundMemberWashCoupon($orderList->no_order, $user->no_user)
                },
                default => null
            };

            if (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail) != SearchBizKindDetail::WASH) {
                BizCall::setVn([
                    'virtualNumber' => $orderList->ds_safe_number,
                    'realNumber' => ' '
                ]);
                (new VirtualNumberService())->updateVnsLog([
                    'dt_use_end' => now()
                ], [
                    'virtual_number' => $orderList->ds_safe_number,
                    'no_order' => $noOrder
                ]);
                $orderList->update([
                    'ds_safe_number' => Code::conf('virtual_number.default_number')
                ]);
            }

            if (SearchBizKind::getBizKind($shop->partner->cd_biz_kind) == SearchBizKind::RETAIL) {
                Cu::client(env('CU_API_PATH_ORDER_CANCEL'), [
                    'shop_code' => $shop->store_cd,
                    'no_order' => $orderList->no_order,
                    'nm_order' => $orderList->nm_order,
                    'cd_cancel_type' => '620100',
                    'dt_order' => $orderList->dt_reg->format('YmdHis'),
                    'dt_pickup' => $orderList->dt_pickup->format('YmdHis')
                ]);
            }

            if (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail) == SearchBizKindDetail::SPC
                && $cdOrderStatus == '601900' && empty(data_get($orderList, 'ds_spc_order')) == false
            ) {
                Spc::cancel($noOrder, $shop, $reason);
            }
        }

        return $response;
    }

    /**
     * @param Shop $shop
     * @param PgService $pg
     * @param string $noOrder
     * @param string $cdOrderStatus
     * @param string|null $reason
     * @return array
     * @throws GuzzleException
     * @throws OwinException
     * @throws Throwable
     */
    public function refundAdmin(
        Shop $shop,
        string $noOrder,
        string $cdOrderStatus,
        string $nmPg,
        string $reason = null
    ): array {
        $orderList = OrderList::where([
            'no_order' => $noOrder
        ])->get()->whenEmpty(function () {
            throw new OwinException(Code::message('P2120'));
        })->first();

        $user = MemberService::getMember([
            'no_user' => $orderList->no_user
        ])->first();

        $response = match ($orderList->at_price_pg) {
            0 => [
                'res_cd' => '0000',
                'res_msg' => Code::message('0000'),
            ],
            default => \App\Utils\Pg::refund([
                'nmPg' => $nmPg,
                'noOrder' => $orderList->no_order,
                'dsServerReg' => $orderList->orderPayment->ds_server_reg,
                'nmOrder' => $orderList->nm_order,
                'dsResOrderNo' => $orderList->orderPayment->ds_res_order_no,
                'price' => $orderList->at_price_pg,
                'reason' => $cdRejectReason ?? Code::message('PG9999')
            ])
        };

        $orderList->orderPayment->update([
            'ds_res_code_refund' => $response['res_cd'],
            'cd_reject_reason' => $reason,
            'dt_req_refund' => now(),
            'dt_res_refund' => now()
        ]);

        if ($response['res_cd'] == '0000') {
            $orderList->update([
                'cd_pickup_status' => '602400',
                'cd_payment_status' => '603900',
                'cd_order_status' => $cdOrderStatus,
                'dt_order_status' => now(),
                'dt_pickup_status' => now(),
                'dt_payment_status' => now(),
            ]);

            (new OrderProcess([
                'no_order' => $orderList->no_order,
                'no_user' => $orderList->no_user,
                'no_shop' => $shop->no_shop,
                'cd_order_process' => '616991',
            ]))->saveOrFail();

            match (SearchBizKind::getBizKind($shop->partner->cd_biz_kind)) {
                SearchBizKind::FNB => (new CouponService())->refundMemberCoupon($orderList->no_order, $orderList->no_user,),
                SearchBizKind::RETAIL => (new CouponService())->refundMemberRetailCoupon($orderList->no_order, $orderList->no_user,),
                SearchBizKind::WASH => (new CouponService())->refundMemberWashCoupon($orderList->no_order, $orderList->no_user,),
                default => null
            };

            if (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind) != SearchBizKindDetail::WASH) {
                BizCall::setVn([
                    'virtualNumber' => $orderList->ds_safe_number,
                    'realNumber' => ' '
                ]);
                (new VirtualNumberService())->updateVnsLog([
                    'dt_use_end' => now()
                ], [
                    'virtual_number' => $orderList->ds_safe_number,
                    'no_order' => $noOrder
                ]);
                $orderList->update([
                    'ds_safe_number' => Code::conf('virtual_number.default_number')
                ]);
            }

            if (SearchBizKind::getBizKind($shop->partner->cd_biz_kind) == SearchBizKind::RETAIL) {
                Cu::client(env('CU_API_PATH_ORDER_CANCEL'), [
                    'shop_code' => $shop->store_cd,
                    'no_order' => $orderList->no_order,
                    'nm_order' => $orderList->nm_order,
                    'cd_cancel_type' => '620100',
                    'dt_order' => $orderList->dt_reg->format('YmdHis'),
                    'dt_pickup' => $orderList->dt_pickup->format('YmdHis')
                ]);
            }

            if (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail) == SearchBizKindDetail::SPC) {
                Spc::cancel($orderList->no_order, $shop, $reason);
            }
        }

        return $response;
    }

    /**
     * @param $noUser
     * @return array
     */
    public static function getAllOrderList($noUser)
    {
        $rows = OrderList::with([
            'shop.shopOil',
            'partner',
            'orderPayment',
            'orderProcess'
        ])->where([
            ['no_user', '=', $noUser],
            ['dt_reg', '>', DB::raw("CURRENT_DATE()")]
        ])->get()->map(function ($collect) {
            $collect->nm_shop = $collect->partner['nm_partner'] . ' ' . $collect->shop['nm_shop'];
            $collect->cd_biz_kind = (string)$collect->partner['cd_biz_kind'];
            $collect->dt_sort = $collect->cd_biz_kind === '201300'
                ? Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $collect->dt_reg
                )->format('Y-m-d H:i:s')
                : Carbon::createFromFormat('Y-m-d H:i:s', $collect->dt_pickup)
                    ->format(
                        'Y-m-d H:i:s'
                    );
            $collect->biz_sort = match ($collect->cd_biz_kind) {
                '201300' => '201300',
                '201800' => '201800',
                default => '201100'
            };

            return [
                'no_order' => $collect->no_order,
                'no_shop' => $collect->no_shop,
                'nm_order' => $collect->nm_order,
                'nm_shop' => $collect->nm_shop,
                'cd_biz_kind' => $collect->cd_biz_kind,
                'dt_sort' => $collect->dt_sort,
                'dt_reg' => $collect->dt_reg,
                'cd_order_status' => $collect->cd_order_status,
                'cd_pickup_status' => getTMapOilPickupStatus(
                    cdOrderStatus: $collect->cd_order_status,
                    cdPickupStatus: $collect->cd_pickup_status,
                    cdPaymentStatus: $collect->cd_payment_status
                ),
                'cd_payment_status' => $collect->cd_payment_status,
                'cd_order_process' => $collect->orderProcess['cd_order_process']
                    ?? null,
//                ?
                'yn_cancel' => $collect->yn_cancel,
                'ds_uni' => $collect->shop->shopOil?->ds_uni,
            ];
        })->sortBy('biz_sort')->sortBy('dt_sort');

        $oilOrderList = [];
        $fnbOrderList = [];
        $retailOrderList = [];

        if (count($rows)) {
            foreach ($rows as $row) {
                if ($row['cd_biz_kind'] === (string)Code::conf(
                        'biz_kind.oil'
                    )
                ) {
                    if ($row['cd_pickup_status'] !== '602400') {
                        $row['yn_cancel'] = 'N';
                        if ($row['cd_biz_kind'] == '201300') {
                            if (date('H:i:s') < '23:30:01') {
                                $row['yn_cancel'] = 'Y';
                            }
                        } else {
                            if ($row['cd_pickup_status']
                                == '602100'
                            ) { // 주문요청은 접수전이라 취소가능
                                $row['yn_cancel'] = 'Y';
                            } else {
                                if ($row['cd_pickup_status'] == '602200'
                                    and data_get(
                                        $row,
                                        'at_add_delay_min'
                                    ) > 0
                                ) { // 접수할때 지연시킨경우
                                    $dp = date_parse(
                                        data_get($row, 'dt_pickup_status')
                                    );
                                    $pickup_status_stemp = mktime(
                                            $dp['hour'],
                                            $dp['minute'],
                                            $dp['second'],
                                            $dp['month'],
                                            $dp['day'],
                                            $dp['year']
                                        ) + 300;
                                    if ($pickup_status_stemp > time()
                                    ) { // 변경시간 5분이 지나지 않은경우 취소가능
                                        $row['yn_cancel'] = 'Y';
                                    }
                                }
                            }
                        }
                        $row['dt_reg'] = Carbon::createFromFormat(
                            'Y-m-d H:i:s',
                            $row['dt_reg']
                        )->format('Y-m-d H:i:s');
                        $oilOrderList[] = $row;
                    }
                }

                // tmap에서 주유만 사용하기 때문에 하단의 retail, fnb는 주석 처리함

//                elseif ($row['cd_biz_kind'] === (string)Code::conf('biz_kind.retail')) {
//                    if(isset($row['call_cnt']) && $row['call_cnt']) {
//                        //todo call count check
//                        continue;  ///// 점원호출 내역이 있는경우 제외
//                    }
//                    if ($row['cd_pickup_status'] !== '602400' && $row['cd_payment_status'] === '603300') {
//                        // 진행중인 주문건
//                        $row['yn_cancel'] = 'N';
//                        if($row['cd_order_status'] == "601200" && $row['cd_pickup_status'] == "602100" && $row['cd_payment_status'] == "603300" ) {
//                            $row['yn_cancel'] = "Y";
//                        }
//                        $row['reject_reason_msg'] = "";
//                    } else {
//                        //취소 주문건
//                        if($row['cd_order_status'] === "601950" && $row['cd_pickup_status'] === "602400" && $row['cd_payment_status'] === "603900" ) {
//                            $row['yn_cancel'] = 'N';
//                            if (isset($row['cd_reject_reason'])) {
//                                $row['reject_reason_msg'] = self::getRejectReason($row['cd_reject_reason'], $row['nm_order'], $row['nm_shop'], $row['no_reject_product_list']);
//                            }
//                            $row['cd_order_process'] = '616991';
//                        }
//                    }
//                    $row['cd_reject_reason'] = (String)$row['cd_reject_reason'];
//                    $row['no_reject_product_list'] = (String)$row['no_reject_product_list'];
//
//                    $retailOrderList[] = $row;
//                } else {
//                    if(isset($row['call_cnt']) && $row['call_cnt']) {
//                        //todo call count check
//                        continue;  ///// 점원호출 내역이 있는경우 제외
//                    }
//                    if($row['cd_pickup_status'] !== "602400" && $row['cd_payment_status'] === "603300") {
//                        // 진행중인 주문건
//                        $row['yn_cancel'] = 'N';
//                        if($row['cd_order_status'] === "601200" && $row['cd_pickup_status'] === "602100" && $row['cd_payment_status'] === "603300" ) {
//                            $row['yn_cancel'] = 'Y';
//                        }
//                    } else {
//                        // 취소주문건
//                        $row['yn_cancel'] = 'N';
//                        if (isset($row['cd_reject_reason'])) {
//                            $row['reject_reason_msg'] = self::getRejectReason($row['cd_reject_reason'], $row['nm_order'], $row['nm_shop'], $row['no_reject_product_list']);
//                        }
//                        $row['cd_order_process'] = '616991';
//                    }
//                    $row['cd_reject_reason'] = (String)$row['cd_reject_reason'];
//                    $row['no_reject_product_list'] = (String)$row['no_reject_product_list'];
//                    $fnbOrderList[] = $row;
//                }
            }
        }

        return [
            'result' => "1",
            'ct_order' => count($oilOrderList) + count($retailOrderList)
                + count($fnbOrderList),   // 주문건수 - 리스트내 주문건 갯수
            'oil_order_list' => $oilOrderList, // 주유주문리스트
//            'retail_order_list' => $retailOrderList, // FnB 주문리스트
//            'fnb_order_list' => $fnbOrderList, // 리테일 주문리스트
        ];
    }

    /**
     * @param $cdRejectReason
     * @param $nmOrder
     * @param $nmShop
     * @param $productIds
     * @return string
     */
    public static function getRejectReason(
        $cdRejectReason,
        $nmOrder,
        $nmShop,
        $productIds = []
    ) {
        $productNames = "";
        if ($cdRejectReason === '600630' && count($productIds)) {
            $productNameArrs = self::getProductByProductIds($productIds)->pluck(
                'nm_product'
            )->all();
            $productNames = implode(',', $productNameArrs);
        }

        return match ($cdRejectReason) {
            '606100', '606200' => "'{$nmOrder}' 재고 부족으로\n주문이 취소되었습니다.",
            '606300' => "'{$nmShop}'에서 주문을 수락하지 않아\n자동 취소되었습니다.",
            '606500' => '매장 휴무',
            '606600' => '상품 불일치',
            '606610' => "요청사항 반영이 어려워\n주문이 취소되었습니다.",
            '606620' => "도착예정시간까지 제조가 불가능하여\n주문이 취소되었습니다.",
            '606630' => "주문불가상품 [{$productNames}]",
            '606900' => "기타",
            default => '기타'
        };
    }

    /**
     * ,로 구분된 productNo 로 상품 조회
     *
     * @param array $productIds
     *
     * @return Collection
     */
    public static function getProductByProductIds(array $productIds): Collection
    {
        return Product::whereIn('no_product', $productIds)->get();
    }

    /**
     * 주유상세 - cd_order_process
     * 최근의 주문진행건 1건  :: 주유
     *
     * @param string $noOrder
     * @param int $noUser
     *
     * @return OrderProcess|null
     */
    public static function getRecentOrderProcess(
        string $noOrder,
        int $noUser
    ): ?OrderProcess {
        return OrderProcess::where([
            'no_user' => $noUser,
            'no_order' => $noOrder
        ])->orderByDesc('no')
            ->orderByDesc('dt_order_process')
            ->orderBy('cd_order_process')->first();
    }

    /**
     * @param array $parameter
     * @return MemberShopEnterLog|Model|object|null
     */
    public static function getMemberShopEnterLog(array $parameter)
    {
        return MemberShopEnterLog::where($parameter)->orderByDesc('dt_reg')
            ->first();
    }

    /**
     * @param $noUser
     * @param array|null $isInCodes
     * @param array|null $isNotInCodes
     * @return MemberShopEnterLog|Model|object|null
     */
    public static function getMemberShopEnterLogByIsInCode(
        $noUser,
        array $isInCodes = null,
        array $isNotInCodes = null
    ) {
        $memberShopEnterLog = MemberShopEnterLog::where('no_user', $noUser);

        if ($isInCodes) {
            $memberShopEnterLog = $memberShopEnterLog->whereIn(
                'yn_is_in',
                $isInCodes
            );
        }

        if ($isNotInCodes) {
            $memberShopEnterLog = $memberShopEnterLog->whereNotIn(
                'yn_is_in',
                $isNotInCodes
            );
        }

        return $memberShopEnterLog->orderByDesc('no')->first();
    }

    /**
     * @param $data
     * @return mixed
     */
    public static function registMemberShopEnterLog($data)
    {
        return MemberShopEnterLog::create($data);
    }

    /**
     * @param array $parameter
     * @return Collection
     */
    public static function getOrderStatusHistory(array $parameter): Collection
    {
//        추후 하나의 메소드로 가져오게 변경
        return OrderList::with(['partner', 'shop.oilInShop.shop'])->where($parameter)->get();
    }

    /**
     * 주차 - 자동결제 입차 시 주문 생성
     * @param MemberCarinfo $carInfo
     * @param ParkingSite $shop
     * @param Collection $request
     *
     * @return array
     * @throws MobilXException
     */
    public function autoParkingOrder(MemberCarinfo $carInfo, ParkingSite $shop, Collection $request, $serviceCodeEnum = ServiceCodeEnum::OWIN): array
    {
        // 서비스 코드가 오윈인 경우 르노 주문인지 확인 해야 한다
        if ($serviceCodeEnum === ServiceCodeEnum::OWIN) {
            $serviceCodeEnum = match (MemberLevel::from((int)$carInfo->user->cd_mem_level)) {
                MemberLevel::AVN => match (empty(data_get($carInfo->user->memberDetail, 'no_car_no_rsm')) == false) {
                    true => match ($carInfo->no == data_get($carInfo->user->memberDetail, 'no_car_no_rsm')) {
                        true => ServiceCodeEnum::RENAULT,
                        default => ServiceCodeEnum::OWIN,
                    },
                    default => ServiceCodeEnum::OWIN
                },
                default => ServiceCodeEnum::OWIN
            };
        }

        $appType = Common::getAppTypeFromServiceCodeEnum($serviceCodeEnum);

        $noOrder = CodeUtil::generateOrderCode($serviceCodeEnum);
        $nmOrder = "[자동결제] " . $shop['nm_shop'];

        // 카드 체크
        $card = $carInfo->cards->where('cd_pg', $shop->cd_pg)->whenEmpty(function () {
            throw new MobilXException('IF_0003', 9999);
        })->first();

        try {
            (new ParkingOrderList([
                'no_order' => $noOrder,
                'nm_order' => $nmOrder,
                'no_user' => $carInfo->no_user,
                'ds_car_number' => $carInfo->ds_car_number,
                'seq' => $carInfo->seq,
                'no_site' => $shop->no_site,
                'id_site' => $shop->id_site,
                'id_auto_parking' => $shop->id_auto_parking,
                'no_card' => $card['no_card'],
                'cd_card_corp' => $card['cd_card_corp'],
                'no_card_user' => $card['no_card_user'],
                'cd_pg' => $shop->cd_pg,
                'at_disct' => 0,
                'at_cpn_disct' => 0,
                'at_basic_time' => $shop->at_basic_time,
                'at_basic_price' => $shop->at_basic_price,
                'dt_entry_time' => Carbon::parse($request['entryTime']),
                'cd_service' => '900200',
                'cd_service_pay' => '901100',
                'cd_payment' => '501200',
                'cd_payment_kind' => '502100',
                'cd_payment_method' => '504100',
                'cd_third_party' => $appType->value,
            ]))->saveOrFail();

            (new ParkingOrderProcess([
                'no_order' => $noOrder,
                'no_user' => $carInfo->no_user,
                'id_auto_parking' => $shop->id_auto_parking,
                'cd_order_process' => '616603', // 입차완료
            ]))->saveOrFail();

            return [
                'result' => true,
                'no_order' => $noOrder,
                'nm_order' => $nmOrder,
                'no_user' => $carInfo->no_user
            ];
        } catch (Exception $e) {
            throw new MobilXException('IF_0003', 9999, null, $e->getMessage());
        }
    }

    /**
     * @param ParkingOrderList $order
     * @param ParkingSite $shop
     * @param MemberCarinfo $car
     * @param Collection $request
     * @return array
     * @throws GuzzleException
     * @throws MobilXException
     * @throws OwinException
     * @throws Throwable
     */
    public function autoParkingPayment(
        ParkingOrderList $order,
        ParkingSite $shop,
        MemberCarinfo $car,
        Collection $request
    ): array {
        $shop->cd_pg = Pg::kcp->value; //kcp 결제만 사용
        $verify = $this->verifyAutoParkingOrder($order, $shop, $car, $request);
        //card, paymentFee
        $noOrder = $request['txId'] ?? $request['no_order'];
        $parameter = [
            'nmPg' => Pg::incarpayment_kcp->name,
            'noOrder' => $noOrder,
            'noShop' => $shop->no_site,
            'noUser' => $car->no_user,
            'nmBuyer' => $car->user->nm_user,
            'email' => $car->user->id_user,
            'phone' => $car->user->ds_phone,
            'price' => $verify['paymentFee'],
            'atCupDeposit' => 0,
            'billkey' => $verify['card']->ds_billkey,
            'nmOrder' => $order['nm_order']
        ];

        if (empty(data_get($request, 'no_order'))) {
            ParkingOrderList::where('no_order', $noOrder)->update([
                'dt_exit_time' => Carbon::parse($request['exitTime']),
                'at_price' => $verify['paymentFee'],
                'at_price_pg' => $verify['paymentFee'],
            ]);
        }

//        $pg = (new PgService(Pg::incarpayment_kcp->name))->setPg();
        $dtReq = now();
        $paymentInfo = match ($verify['paymentFee']) {
            0 => [
                'res_cd' => '0000',
                'res_msg' => Code::message('0000'),
                'ds_res_order_no' => null,
                'ds_req_param' => $parameter,
                'ds_res_param' => [],
            ],
            default => \App\Utils\Pg::payment($parameter)
        };
        try {
            $dtRes = now();

            ParkingOrderList::where('no_order', $noOrder)->update([
                'no_card' => $verify['card']->no_card,
                'cd_card_corp' => $verify['card']->cd_card_corp,
                'no_card_user' => $verify['card']->no_card_user,
                'dt_req' => $dtReq,
                'dt_res' => $dtRes,
                'ds_res_code' => $paymentInfo['res_cd'],
                'ds_res_msg' => $paymentInfo['res_msg'],
                'ds_req_param' => json_encode(
                    $paymentInfo['ds_req_param'],
                    JSON_UNESCAPED_UNICODE
                ),
                'ds_res_param' => json_encode(
                    $paymentInfo['ds_res_param'],
                    JSON_UNESCAPED_UNICODE
                ),
                'ds_server_reg' => now()->format('YmdHis'),
                'product_num' => 1,
                'dt_order_status' => now(),
                'dt_payment_status' => now(),
                'ds_res_order_no' => $paymentInfo['ds_res_order_no'],
                'cd_order_status' => match ($paymentInfo['res_cd']) {
                    '0000' => '601200',
                    default => '601900'
                },
                'cd_payment_status' => match ($paymentInfo['res_cd']) {
                    '0000' => '603300',
                    default => '603200'
                },
                'cd_pg_result' => match ($paymentInfo['res_cd']) {
                    '0000' => '604100',
                    default => '604999'
                },
                'cd_pg_bill_result' => match ($paymentInfo['res_cd']) {
                    '0000' => '902000',
                    default => Code::conf("payment_response.{$paymentInfo['res_cd']}.code") ?? '902900',
                },
            ]);
            if ($paymentInfo['res_cd'] == '0000') {
                (new ParkingOrderProcess([
                    'no_order' => $noOrder,
                    'no_user' => $car->no_user,
                    'id_auto_parking' => $shop->id_auto_parking,
                    'cd_order_process' => '616600',
                ]))->saveOrFail();
            }

            AutoParkingUtil::resultPayment([
                'txId' => $noOrder,
                'storeId' => $shop['id_site'],
                'storeCategory' => $shop['ds_category'],
                'plateNumber' => $car['ds_car_number'],
                'approvalPrice' => $verify['encryptFee'],
                'approvalDate' => $dtRes,
                'approvalNumber' => $paymentInfo['res_cd'] == '0000' ? $paymentInfo['ds_res_order_no'] : '1',
                'approvalResult' => "1",
                'approvalMessage' => $paymentInfo['res_cd'] == '0000' ? "결제 완료" : $paymentInfo['res_msg'],
            ]);
        } catch (Throwable $t) {
            Log::channel('error')->critical($t->getMessage(), [$t->getFile(), $t->getLine(), $t->getTraceAsString()]);
            Log::channel('slack')->critical(env('APP_ENV'), [
                'exception' => $t::class,
                'message' => $t->getMessage(),
                'time' => now()
            ]);

            if ($paymentInfo && $paymentInfo['res_cd'] == '0000') {
                $this->parkingRefund(
                    $order->user,
                    $shop,
                    $noOrder,
                    '601900',
                    Pg::incarpayment_kcp->name
                );
            }

            ParkingOrderList::where('no_order', $noOrder)->update([
                'cd_order_status' => '601900',
                'cd_payment_status' => '603200',
                'cd_pg_result' => '604999',
                'cd_pg_bill_result' => '902900',
            ]);

            (new ParkingOrderProcess([
                'no_order' => $noOrder,
                'no_user' => $car->no_user,
                'id_auto_parking' => $shop->id_auto_parking,
                'cd_order_process' => '616605',
            ]))->saveOrFail();

            AutoParkingUtil::resultPayment([
                'txId' => $noOrder,
                'storeId' => $shop['id_site'],
                'storeCategory' => $shop['ds_category'],
                'plateNumber' => $car['ds_car_number'],
                'approvalPrice' => $verify['encryptFee'],
                'approvalDate' => now(),
                'approvalNumber' => $paymentInfo['res_cd'] == '0000' ? $paymentInfo['ds_res_order_no'] : '1',
                'approvalResult' => "0",
                'approvalMessage' => $t->getMessage(),
            ]);

            return [
                'result' => false,
                'no_order' => $noOrder,
                'pg_msg' => $paymentInfo['res_msg'],
                'msg' => Code::message('P2010'),
                'at_price_pg' => $verify['paymentFee'],
            ];
        }

        return [
            'result' => $paymentInfo['res_cd'] == '0000',
            'no_order' => $noOrder,
            'pg_msg' => $paymentInfo['res_msg'],
            'msg' => match ($paymentInfo['res_cd']) {
                '0000' => Code::message('P2024'),
                default => Code::message('P2010')
            },
            'no_user' => $car->no_user,
            'at_price_pg' => $verify['paymentFee'],
        ];
    }

    /**
     * @param User $user
     * @param ParkingSite $shop
     * @param Collection $request
     * @return array
     * @throws OwinException
     */
    public function parkingPayment(
        User $user,
        ParkingSite $shop,
        Collection $request
    ): array {
        $shop->cd_pg = Pg::kcp->value; //kcp 결제만 사용
        $verify = $this->verifyParkingOrder($user, $shop, $request);

        $pgPrice = $request['at_price_total'] - $request['at_cpn_disct'];
        $noOrder = CodeUtil::generateOrderCode(ServiceCodeEnum::OWIN);

        $nmOrder = $shop['nm_shop'] . '-' . $verify['ticket']['nm_product'];
        $carInfo = $user->memberCarInfo->where(
            'ds_car_number',
            $request['car_number']
        )->get()->whenEmpty(function () {
            throw new OwinException(Code::message('M1510'));
        })->first();
        $bookingUid = null;
        $paymentInfo = null;
        $parameter = [
            'nmPg' => Pg::incarpayment_kcp->name,
            'noOrder' => $noOrder,
            'noShop' => $shop->id_site,
            'noUser' => $user->no_user,
            'nmBuyer' => $user->nm_user,
            'email' => $user->id_user,
            'phone' => $user->ds_phone,
            'price' => $pgPrice,
            'atCupDeposit' => 0,
            'billkey' => $verify['card']->ds_billkey,
            'nmOrder' => $nmOrder
        ];
//        $pg = (new PgService(Pg::incarpayment_kcp->name))->setPg();

        try {
            $dtReq = now();
            $paymentInfo = match ($pgPrice > 0) {
                true => \App\Utils\Pg::payment($parameter),
                false => [
                    'res_cd' => '0000',
                    'res_msg' => Code::message('0000'),
                    'ds_req_param' => $parameter,
                    'ds_res_param' => [],
                    'ds_res_order_no' => null,
                    'at_price_pg' => 0
                ]
            };
            $dtRes = now();
            if ($paymentInfo['res_cd'] == '0000') {
                $bookingResult = Parking::setTicket(
                    $request['no_product'],
                    $request['car_number'],
                    $user['no_user']
                );
                if (!$bookingResult || !$bookingResult['bookingUid'] ||
                    !$bookingResult['createdAt']) {
                    return [
                        'result' => false,
                        'no_order' => null,
                        'nm_order' => null,
                        'pg_msg' => '',
                        'msg' => Code::message('P2010')
                    ];
                }

                $bookingUid = $bookingResult['bookingUid'];

                $orderList = new ParkingOrderList([
                    'no_order' => $noOrder,
                    'nm_order' => $nmOrder,
                    'no_user' => $user->no_user,
                    'no_site' => $shop->no_site,
                    'id_site' => $shop->id_site,
                    'ds_car_number' => $request['car_number'],
                    'seq' => $carInfo['seq'],
                    'no_parking_site' => $shop->id_site,
                    'no_product' => $request['no_product'],
                    'ds_parking_start_time' => $verify['ticket']['ds_parking_start_time'],
                    'ds_parking_end_time' => $verify['ticket']['ds_parking_end_time'],
                    'cd_ticket_type' => $verify['ticket']['cd_ticket_type'],
                    'cd_ticket_day_type' => $verify['ticket']['cd_ticket_day_type'],
                    'cd_parking_status' => 'WAIT',
                    'cd_service' => '900200',
                    'cd_service_pay' => ServicePayCode::case(
                        (string)$request['cd_service_pay']
                    ),
                    'cd_order_status' => '601200',
                    'cd_pg' => $shop->cd_pg,
                    'cd_payment' => '501200',
                    'cd_payment_kind' => '502100',
                    'cd_payment_method' => '504100',
                    'cd_payment_status' => '603300',
                    'no_card' => $verify['card']->no_card,
                    'cd_card_corp' => $verify['card']->cd_card_corp,
                    'no_card_user' => $verify['card']->no_card_user,
                    'at_price' => $request['at_price_total'],
                    'at_price_pg' => $pgPrice,
                    'at_disct' => $request['at_disct'] ?? 0,
                    'at_cpn_disct' => $request['at_cpn_disct'],
                    'dt_req' => $dtReq,
                    'dt_res' => $dtRes,
                    'cd_pg_result' => '604100',
                    'cd_pg_bill_result' => '902000',
                    'ds_res_code' => $paymentInfo['res_cd'],
                    'ds_res_msg' => $paymentInfo['res_msg'],
                    'ds_res_order_no' => $paymentInfo['ds_res_order_no'],
                    'ds_req_param' => json_encode(
                        $paymentInfo['ds_req_param'],
                        JSON_UNESCAPED_UNICODE
                    ),
                    'ds_res_param' => json_encode(
                        $paymentInfo['ds_res_param'],
                        JSON_UNESCAPED_UNICODE
                    ),
                    'ds_server_reg' => now()->format('YmdHis'),
                    'product_num' => 1,
                    'no_booking_uid' => $bookingResult['bookingUid'],
                    'dt_booking' => $bookingResult['createdAt'],
                    'dt_order_status' => now(),
                    'dt_payment_status' => now(),
                    'at_pg_commission_rate' => $shop->at_pg_commission_rate ?? 0,
                    'cd_commission_type' => $shop->cd_commission_type ?? null,
                    'at_commission_amount' => $shop->at_commission_amount ?? 0,
                    'at_commission_rate' => match ($shop->cd_commission_type
                        == '205300') {
                        true => $shop->at_commission_rate,
                        default => 0
                    },
                    'at_sales_commission_rate' => $shop->at_sales_commission_rate ?? 0,
                ]);
                $orderList->saveOrFail();

                (new ParkingOrderProcess([
                    'no_order' => $noOrder,
                    'no_user' => $user->no_user,
                    'id_site' => $shop->id_site,
                    'no_parking_site' => $shop->id_site,
                    'cd_order_process' => '616600',
                ]))->saveOrFail();

                if ($request['at_cpn_disct'] > 0) {
                    (new CouponService())->usedMemberParkingCoupon(
                        $noOrder,
                        data_get($request, 'discount_info.coupon.no'),
                        $user->no_user,
                        $request['at_price_total'],
                    );
                }
            }
        } catch (Throwable $t) {
            Log::channel('error')->critical($t->getMessage(), [$t->getFile(), $t->getLine(), $t->getTraceAsString()]);
            Log::channel('slack')->critical(env('APP_ENV'), [
                'exception' => $t::class,
                'message' => $t->getMessage(),
                'time' => now()
            ]);

            if ($paymentInfo && $paymentInfo['res_cd'] == '0000') {
                $this->parkingRefund(
                    $user,
                    $shop,
                    $noOrder,
                    '601900',
                    Pg::incarpayment_kcp->name
                );
            }
            if ($bookingUid) {
                $cancelResult = Parking::cancelTicket($bookingUid);
                if ($cancelResult['bookingUid']
                    && $cancelResult['canceledAt']
                ) {
                    ParkingService::updateParkingOrder([
                        'no_order' => $noOrder
                    ], [
                        'no_booking_uid' => 'CANCELED',
                        'dt_user_parking_canceled' => $cancelResult['canceledAt']
                    ]);
                }
            }
            if ($request['at_cpn_disct'] > 0) {
                (new CouponService())->refundMemberParkingCoupon(
                    $noOrder,
                    $user->no_user,
                );
            }

            return [
                'result' => false,
                'no_order' => null,
                'nm_order' => null,
                'pg_msg' => $t->getMessage(),
                'msg' => Code::message('P2010')
            ];
        }

        // 결제 성공
        return match ($paymentInfo['res_cd']) {
            '0000' => [
                'result' => true,
                'no_order' => $noOrder,
                'nm_order' => $nmOrder,
                'pg_msg' => $paymentInfo['res_msg'],
                'msg' => Code::message('P2024')
            ],
            default => [
                'result' => false,
                'no_order' => null,
                'nm_order' => null,
                'pg_msg' => $paymentInfo['res_msg'],
                'msg' => Code::message('P2010')
            ]
        };
    }

    /**
     * @param ParkingOrderList $order
     * @param ParkingSite $shop
     * @param MemberCarinfo $car
     * @param Collection $request
     * @return array
     * @throws MobilXException
     */
    public function verifyAutoParkingOrder(
        ParkingOrderList $order,
        ParkingSite $shop,
        MemberCarinfo $car,
        Collection $request
    ): array {
        $orderInfo = ParkingService::getAutoParkingOrderInfo([
            'no_order' => $order['no_order'],
        ], $request['interfaceCode'] ?? "IF_0006");

        //entryTime 같은지 확인
        if (empty($request['entryTime']) == false && Carbon::parse($request['entryTime']) != $orderInfo['dt_entry_time']) {
            throw new MobilXException("IF_0005", 9007);
        }

        //차량 정보의 카드 번호 확인
        if (empty($request['plateNumber']) == false && $request['plateNumber'] != $orderInfo['ds_car_number']) {
            throw new MobilXException('IF_0005', 9004);
        }

        $card = MemberCard::where([
            'no_card' => $request['no_card'] ?? $car['no_card'],
            'cd_pg' => Pg::kcp->value,
            'yn_delete' => 'N',
        ])->get()->whenEmpty(function () {
            throw new MobilXException("IF_0005", 9007);
        })->first();

        $paymentFee = $orderInfo['at_price'];
        if (empty($request['paymentFee']) == false) {
            $paymentFee = AutoParkingUtil::decryptFee($request['paymentFee']);
        }

        return [
            'card' => $card,
            'paymentFee' => (int)$paymentFee,
            'encryptFee' => $paymentFee,
        ];
    }

    /**
     * @param User $user
     * @param ParkingSite $shop
     * @param Collection $request
     * @return array
     * @throws GuzzleException
     * @throws OwinException
     */
    public function verifyParkingOrder(
        User $user,
        ParkingSite $shop,
        Collection $request
    ): array {
        (new ParkingService())->ordering($user['no_user'], [
            'id_site' => ['=', $shop['id_site']],
            'ds_car_number' => ['=', $request['car_number']],
            'cd_parking_status' => ['=', 'WAIT'],
            'cd_order_status' => ['=', '601200'],
            'cd_payment_status' => ['=', '603300'],
        ])->whenNotEmpty(function () {
            throw new OwinException(Code::message('W1000'));
        });

        // 티켓 조회
        $ticket = ParkingService::getActiveTicket(
            $shop['id_site'],
            $request['no_product']
        );

        if (intval($ticket['at_price']) != intval($request['at_price_total'])) {
            throw new OwinException(Code::message('1009'));
        }

        // pg 결제 최소 금액 체크
        if (between(
            1,
            Code::conf('pg_min_price') - 1,
            $request['at_price_total'] + data_get($request, 'at_commission_rate')
            - $request['at_cpn_disct']
        )
        ) {
            throw new OwinException(sprintf(Code::message('P2021'), Code::conf('pg_min_price')));
        }

        // 쿠폰 체크
        if (empty(data_get($request, 'discount_info.coupon.no')) === false) {
            (new CouponService())->getParkingUsableCoupon($user->no_user, $shop->no_site, $request['at_price_total'], $ticket)
                ->where('no', Arr::get($request, 'discount_info.coupon.no'))
                ->whenEmpty(function () {
                    throw new OwinException(Code::message('P2300'));
                })->whenNotEmpty(function ($coupon) use ($request) {
                    if (
                        $coupon->first()['at_discount'] != Arr::get(
                            $request,
                            'discount_info.coupon.at_coupon'
                        )
                        || $coupon->first()['at_discount']
                        != $request['at_cpn_disct']
                    ) {
                        throw new OwinException(Code::message('P2330'));
                    }
                });
        }

        // 카드 체크
        $card = $user->memberCard->where('no_card', $request['no_card'])->where(
            'cd_pg',
            Pg::kcp->value
        )->whenEmpty(
            function () {
                throw new OwinException(Code::message('P1020'));
            }
        )->first();

        return [
            'ticket' => $ticket,
            'card' => $card
        ];
    }

    /**
     * 웹할인권 결제 취소
     * @param User $user
     * @param ParkingSite $shop
     * @param PgService $pg
     * @param string $noOrder
     * @param string $cdOrderStatus
     * @return array
     * @throws MobilXException
     * @throws OwinException
     * @throws Throwable
     */
    public function parkingRefund(
        User $user,
        ParkingSite $shop,
        string $noOrder,
        string $cdOrderStatus,
        string $nmPg,
        string $cdRejectReason = null
    ): array {
        $orderList = ParkingOrderList::where([
            'no_order' => $noOrder
        ])->get()->whenEmpty(function () {
            throw new OwinException(Code::message('P2120'));
        })->first();

        if (in_array(
            $orderList->cd_order_status,
            ['601900', '601950', '601999']
        )
        ) {
            throw new OwinException(Code::message('P2401'));
        }

        $response = match ($orderList->at_price_pg > 0) {
            true => \App\Utils\Pg::refund([
                'nmPg' => $nmPg,
                'noOrder' => $orderList->no_order,
                'dsServerReg' => $orderList->ds_server_reg,
                'nmOrder' => $orderList->nm_order,
                'dsResOrderNo' => $orderList->ds_res_order_no,
                'price' => $orderList->at_price_pg,
                'reason' => $cdRejectReason ?? Code::message('PG9999')
            ]),
            default => [
                'res_cd' => '0000',
                'res_msg' => Code::message('0000'),
            ]
        };

        $orderList->update([
            'ds_res_code_refund' => $response['res_cd'],
            'dt_req_refund' => now(),
            'dt_res_refund' => now()
        ]);

        if ($response['res_cd'] === '0000') {
            if ($shop->ds_type == 'WEB'
                && $orderList->cd_parking_status == 'WAIT'
            ) {
                $cancelResult = Parking::cancelTicket(
                    $orderList->no_booking_uid
                );
                if ($cancelResult['bookingUid']
                    && $cancelResult['canceledAt']
                ) {
                    ParkingService::updateParkingOrder([
                        'no_order' => $orderList->no_order
                    ], [
                        'cd_parking_status'  => match ($cdRejectReason) {
                            '606300' => 'EXPIRED',
                            default => 'CANCELED'
                        },
                        'dt_user_parking_canceled' => $cancelResult['canceledAt']
                    ]);
                }
            } elseif ($shop->ds_type == 'AUTO') {
                AutoParkingUtil::refund([
                    'txId'        => $orderList->no_order,
                    'storeId'     => $shop->id_site,
                    'plateNumber' => $orderList->ds_car_number,
                    'cancelPrice' => $orderList->at_price_pg,
                    'cancelDate'  => date('YmdHis')
                ]);
            }

            $orderList->update([
                'cd_pickup_status'  => '602400',
                'cd_payment_status' => '603900',
                'cd_order_status'   => $cdOrderStatus,
                'cd_reject_reason' => $cdRejectReason,
                'dt_order_status'   => now(),
                'dt_pickup_status'  => now(),
                'dt_payment_status' => now(),
            ]);

            (new ParkingOrderProcess([
                'no_order' => $orderList->no_order,
                'no_user' => $user->no_user,
                'id_site' => $shop->id_site,
                'no_parking_site' => $shop->id_site,
                'cd_order_process' => match ($cdRejectReason) {
                    '606300' => '616602',
                    default => '616601'
                },
            ]))->saveOrFail();

            if ($orderList['at_cpn_disct'] > 0) {
                (new CouponService())->refundMemberParkingCoupon(
                    (string)$orderList->no_order,
                    $user->no_user,
                );
            }
        }
        return $response;
    }

    /**
     * @param int $noUser
     * @param int $size
     * @param int $offset
     * @return LengthAwarePaginator
     */
    public function getOrderListByMember(int $noUser, int $size, int $offset): LengthAwarePaginator
    {
        $orderList = OrderList::with([
            'shop.partner'
        ])->select([
            'no_order',
            'nm_order',
            'cd_order_status',
            'cd_pickup_status',
            'cd_payment_status',
            DB::raw('NULL AS cd_parking_status'),
            'order_list.dt_reg',
            DB::raw('partner.cd_biz_kind as cd_biz_kind'),
            DB::raw('partner.cd_biz_kind_detail as cd_biz_kind_detail'),
            'no_shop',
            DB::raw('NULL as no_site'),
            DB::raw('NULL as nm_site'),
            'cd_pickup_type',
        ])->join('partner', 'order_list.no_partner', '=', 'partner.no_partner')
            ->where('no_user', $noUser)
            ->where('order_list.cd_third_party', getAppType()->value);

        $parkingList = ParkingOrderList::select([
            'no_order',
            'nm_order',
            'cd_order_status',
            DB::raw('NULL AS cd_pickup_status'),
            'cd_payment_status',
            'cd_parking_status',
            'dt_reg',
            DB::raw('201500 AS cd_biz_kind'),
            DB::raw('NULL AS cd_biz_kind_detail'),
            DB::raw('NULL AS no_shop'),
            'no_site',
            DB::raw('NULL AS cd_pickup_type'),
        ])
            ->addSelect([
                'nm_site' => ParkingSite::select('nm_shop')
                    ->where('id_site', DB::raw('parking_order_list.id_site'))
            ])
            ->where('no_user', $noUser)
            ->where('cd_third_party', getAppType()->value);

        return $orderList->union($parkingList)->orderByDesc('dt_reg')->paginate(perPage: $size, page: $offset);
    }

    /**
     * @param int $noUser
     * @param string $bizKind
     * @param int $size
     * @param int $offset
     * @return LengthAwarePaginator
     */
    public static function getOrderList(int $noUser, string $bizKind, int $size, int $offset): LengthAwarePaginator
    {
        $bizKinds = match ($bizKind) {
            SearchBizKind::FNB->name => json_decode(
                SearchBizKind::case('FNB')->value,
                true
            ),
            default => json_decode(SearchBizKind::case($bizKind)->value, true)
        };

        return OrderList::leftJoin(
            'partner',
            'order_list.no_partner',
            'partner.no_partner'
        )
            ->where('order_list.no_user', $noUser)
            ->whereIn('partner.cd_biz_kind', $bizKinds)
            ->where('order_list.cd_third_party', getAppType()->value)
            ->select([
                'order_list.no_order',
                'order_list.nm_order',
                'order_list.cd_order_status',
                'order_list.cd_pickup_status',
                'order_list.cd_payment_status',
                'order_list.dt_reg',
                'order_list.no_shop',
                'partner.cd_biz_kind',
                'partner.cd_biz_kind_detail',
                'partner.nm_partner',
                'order_list.cd_pickup_type',
            ])
            ->with(['shop.partner'])->orderByDesc('order_list.dt_reg')->paginate(
                perPage: $size,
                page: $offset
            );
    }

    /**
     * @param int $noUser
     * @param int $size
     * @param int $offset
     * @return LengthAwarePaginator
     */
    public static function getParkingOrderList(int $noUser, int $size, int $offset): LengthAwarePaginator
    {
        return ParkingOrderList::select([
            'no_site',
            'id_site',
            'no_parking_site',
            'no_order',
            'nm_order',
            'cd_order_status',
            'cd_payment_status',
            'cd_parking_status',
            'dt_reg',
            DB::raw('201500 AS cd_biz_kind'),
            DB::raw('NULL AS cd_biz_kind_detail'),
            DB::raw('NULL AS cd_pickup_type'),
            DB::raw('NULL AS yn_disabled_pickup'),
        ])->with(['parkingSite'])
            ->where('no_user', $noUser)
            ->where('cd_third_party', getAppType()->value)
            ->orderByDesc('dt_reg')->paginate(
                perPage: $size,
                page: $offset
            );
    }

    /**
     * [처리완료전]  주문내역 조회 (회원)
     *
     * @param array $parameter
     * @return Model|null
     */
    public static function getUserOrderInfo(array $parameter): ?Model
    {
        return OrderList::select([
            'a.no_order',
            'a.no_user',
            'a.no_shop',
            'a.cd_gas_kind',
            'a.yn_cash_receipt',
            'a.at_price',
            'a.cd_pickup_status',
            'a.cd_order_status',
            'a.dt_reg',
            'a.cd_payment_status',
            'a.yn_gas_order_liter',
            DB::raw("CONCAT(b.nm_partner, ' ', s.nm_shop) AS nm_shop"),
            'b.no_partner',
            'b.nm_partner',
            'b.cd_biz_kind',
            'b.cd_biz_kind',
            DB::raw("RIGHT (c.ds_car_number ,4) AS ds_car_number"),
            DB::raw("c.ds_car_number AS  ds_car_full_number"),
            'c.seq',
            DB::raw(
                "( SELECT ds_kind FROM car_list WHERE  seq = c.seq ) AS ds_kind"
            )
        ])->from('order_list AS a')
            ->join('shop AS s', 's.no_shop', '=', 'a.no_shop')
            ->join('partner AS b', 'a.no_partner', '=', 'b.no_partner')
            ->leftJoin('beacon AS c', 'a.no_user', '=', 'c.no_user')
            ->where($parameter)
            ->where('a.cd_third_party', getAppType()->value)
            ->with([
                'shop',
                'partner',
                'shopDetail',
                'shopOil',
            ])
            ->orderByDesc('dt_reg')->first();
    }

    /**
     * @param int $noUser
     * @return void
     */
    public function checkIncompleteOrder(int $noUser)
    {
        (new OrderService())->ordering(
            $noUser,
            ['cd_pickup_status' => ['<', '602400']],
            ['cd_payment_status' => ['603200', '603900']]
        )->whenNotEmpty(function () {
            throw new OwinException(Code::message('P2400'));
        });

        (new ParkingService())->ordering(
            $noUser,
            ['cd_payment_status' => ['<', '603300'],]
        )->whenNotEmpty(function () {
            throw new OwinException(Code::message('P2400'));
        });
    }

    /**
     * @param User $user
     * @param OrderList $order
     * @param Collection $request
     * @return array
     * @throws GuzzleException
     * @throws OwinException
     * @throws Throwable
     */
    public function incompletePayment(User $user, OrderList $order, Collection $request): array
    {
        $card = $user->memberCard->where('no_card', $request['no_card'])->where(
            'cd_pg',
            $order['cd_pg']
        )->whenEmpty(
            function () {
                throw new OwinException(Code::message('P1020'));
            }
        )->first();
        $noPayment = makePaymentNo();
        $parameter = [
            'nmPg' => Pg::from($order['cd_pg'])->name,
            'noOrder' => $order['no_order'],
            'noShop' => $order['no_shop'],
            'noUser' => $user->no_user,
            'nmBuyer' => $user->nm_user,
            'email' => $user->id_user,
            'phone' => $user->ds_phone,
            'price' => $order['at_price_pg'],
            'atCupDeposit' => 0,
            'billkey' => $card->ds_billkey,
            'nmOrder' => $order['nm_order']
        ];

//        $pg = (new PgService(Pg::from($order['cd_pg'])->name))->setPg();
        $paymentInfo = match ($order['at_price_pg']) {
            0 => [
                'res_cd' => '0000',
                'res_msg' => Code::message('0000'),
                'ds_req_param' => $parameter,
                'ds_res_param' => [],
            ],
            default => \App\Utils\Pg::payment($parameter),
        };
        try {
            $orderPayment = new OrderPayment([
                'no_order' => $order['no_order'],
                'no_payment' => $noPayment,
                'no_partner' => $order['no_partner'],
                'no_shop' => $order['no_shop'],
                'no_user' => $user->no_user,
                'cd_pg' => $order['cd_pg'],
                'ds_res_order_no' => $paymentInfo['ds_res_order_no'],
                'cd_payment' => '501200',
//            'cd_payment_kind' => '',
                'cd_payment_status' => match ($paymentInfo['res_cd']) {
                    '0000' => '603300',
                    default => '603200'
                },
                'ds_req_param' => json_encode(
                    $paymentInfo['ds_req_param'],
                    JSON_UNESCAPED_UNICODE
                ),
                'ds_server_reg' => now()->format('YmdHis'),
                'ds_res_param' => json_encode(
                    $paymentInfo['ds_res_param'],
                    JSON_UNESCAPED_UNICODE
                ),
                'cd_pg_result' => match ($paymentInfo['res_cd']) {
                    '0000' => '604100',
                    default => '604999'
                },
                'ds_res_msg' => $paymentInfo['res_msg'],
                'ds_res_code' => $paymentInfo['res_cd'],
                'at_price' => $order['at_price'],
                'at_price_pg' => $paymentInfo['at_price_pg'],
                'cd_card_corp' => $card->cd_card_corp,
                'no_card' => $card->no_card,
                'no_card_user' => $card->no_card_user,
                'product_num' => $order->orderPayment?->product_num ?? 0,
            ]);
            $orderPayment->saveOrFail();
            if ($paymentInfo['res_cd'] == '0000') {
                OrderList::where('no_order', $order['no_order'])->update([
                    'no_payment_last' => $noPayment,
                    'cd_calc_status' => '609100',
                    'cd_send_status' => '610100',
                    'no_card' => $card->no_card,
                    'cd_payment_status' => $orderPayment->cd_payment_status,
                    'cd_order_status' => '601200',
                    'cd_pickup_status' => '602100',
                    'dt_pickup_status' => now(),
                    'dt_order_status' => now(),
                    'dt_payment_status' => now(),
                ]);
            }
        } catch (Throwable $t) {
            Log::channel('error')->critical($t->getMessage(), [$t->getFile(), $t->getLine(), $t->getTraceAsString()]);
            Log::channel('slack')->critical(env('APP_ENV'), [
                'exception' => $t::class,
                'message' => $t->getMessage(),
                'time' => now()
            ]);

//            카드결제 취소
            $this->refund(
                $user,
                $order['shop'],
                $order['no_order'],
                '601900',
                Pg::from($order['cd_pg'])->name,
                Code::message('PG9999')
            );

            return [
                'result' => false,
                'no_order' => $order['no_order'],
                'nm_order' => $order['nm_order'],
                'pg_msg' => $t->getMessage(),
                'msg' => Code::message('P2010')
            ];
        }

//        결제 성공
        return match ($paymentInfo['res_cd']) {
            '0000' => [
                'result' => true,
                'no_order' => $order['no_order'],
                'nm_order' => $order['nm_order'],
                'pg_msg' => $paymentInfo['res_msg'],
                'msg' => Code::message('P2024')
            ],
            default => [
                'result' => false,
                'no_order' => $order['no_order'],
                'nm_order' => $order['nm_order'],
                'pg_msg' => $paymentInfo['res_msg'],
                'msg' => Code::message('P2010')
            ]
        };
    }

    /**
     * @param $data
     * @return void
     * @throws Throwable
     */
    public static function createParkingOrderProcess($data)
    {
        (new ParkingOrderProcess($data))->saveOrFail();
    }

    /**
     * SPC 주문상태 수정
     * @param OrderList  $orderInfo
     * @param array  $request
     *
     * @return void
     * @throws Throwable
     */
    public function updateOrderStatus(OrderList $orderInfo, array $request) {
        (new OrderAlarmEventLog([
            'cd_alarm_event_type' => SpcAlarmEventStatus::case(
                $request['status']
            )->value,
            'no_order'            => $orderInfo->no_order,
            'no_shop'             => $orderInfo->no_shop,
            'no_user'             => $orderInfo->no_user
        ]))->saveOrFail();

        unset($orderInfo['nm_shop']);

        $parameter = [
            'cd_pickup_status' => match ($request['status']) {
                'IC' => '602100',
                'DR' => '602200',
                'PW' => '602300',
                default => '602400'
            },
            'dt_pickup_status' => now(),
        ];

        if ($request['status'] == 'IC'
            && $orderInfo->cd_send_type == SendType::PU->value
            && empty(data_get($request, 'deliveryTime')) == false
        ) {
            $parameter['dt_pickup'] = $orderInfo->dt_reg->addMinutes($request['deliveryTime']);
        } elseif ($request['status'] == 'DR') {
            $parameter['confirm_date'] = now();
        } elseif ($request['status'] == 'PW') {
            $parameter['ready_date'] = now();
        } elseif ($request['status'] == 'PC') {
            $parameter['pickup_date'] = now();
        }
        $orderInfo->updateOrFail($parameter);
    }

    public static function getOrderListJoinPartner(array $parameter, ?array $whereIn = []): Collection
    {
        return OrderList::join('partner', 'order_list.no_partner', '=', 'partner.no_partner')
            ->where($parameter)->when(empty($whereIn) === false, function ($query) use ($whereIn) {
                foreach ($whereIn as $key => $values) {
                    $query->whereIn($key, $values);
                }
            })->get();
    }


    /**
     * @param int $noShop
     * @return string
     */
    public function getMaxOrderNo(int $noShop): string
    {
        return now()->format('ymd')
            . $noShop
            . sprintf(
                '%04d',
                OrderList::where('no_shop', $noShop)->where(
                    'dt_reg',
                    '>',
                    now()->startOfDay()
                )->count() + 1
            )
            . mt_rand(100, 999);
    }
}

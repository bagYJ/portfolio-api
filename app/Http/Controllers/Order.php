<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BenefitDetailType;
use App\Enums\BenefitType;
use App\Enums\EnumYN;
use App\Enums\Pickup;
use App\Enums\SearchBizKind;
use App\Enums\SearchBizKindDetail;
use App\Enums\ServiceCode;
use App\Enums\ServicePayCode;
use App\Exceptions\OwinException;
use App\Exceptions\TMapException;
use App\Models\OrderAlarmEventLog;
use App\Queues\Fcm\Fcm;
use App\Services\CodeService;
use App\Services\CouponService;
use App\Services\DirectOrderService;
use App\Services\OrderService;
use App\Services\ReviewService;
use App\Services\ShopService;
use App\Utils\Ark;
use App\Utils\Code;
use App\Utils\Spc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class Order extends Controller
{
    /**
     * @param string $bizKind
     * @param string $noOrder
     * @return JsonResponse
     *
     * 주문상세
     */
    public function detail(string $bizKind, string $noOrder): JsonResponse
    {
        $orderInfo = match ($bizKind) {
            SearchBizKind::PARKING->name => OrderService::getParkingOrderInfo([
                'no_user' => Auth::id(),
                'no_order' => $noOrder
            ])->first(),
            default => OrderService::getOrderInfo([
                'no_user' => Auth::id(),
                'no_order' => $noOrder
            ])->first()
        };

        $atSendPrice = array_filter([
            data_get($orderInfo, 'at_third_party_send_price'),
            data_get($orderInfo, 'at_send_price')
        ], 'strlen');

        $atSendPrice = match (empty($atSendPrice)) {
            false => min($atSendPrice),
            default => null
        };

        return response()->json([
            'result' => true,
            'nm_shop' => $orderInfo->nm_shop ?? $orderInfo->parkingSite?->nm_shop ?? $orderInfo->autoParking?->nm_shop,
            'no_order' => $orderInfo->no_order,
            'no_order_user' => substr($orderInfo->no_order, -7),
            'biz_kind' => $bizKind,
            'biz_kind_detail' => match ($bizKind) {
                SearchBizKind::PARKING->name => SearchBizKindDetail::PARKING->name,
                default => SearchBizKindDetail::getBizKindDetail($orderInfo->partner->cd_biz_kind_detail)?->name
            },
            'ds_spc_order' => $orderInfo->ds_spc_order,
            'nm_order' => $orderInfo->nm_order,
            'dt_reg' => $orderInfo->dt_reg->format('Y-m-d H:i:s'),
            'at_commission_rate' => match ($bizKind) {
                SearchBizKind::RETAIL->name => 0,
                default => $orderInfo->at_commission_rate
            },
            'at_send_price' => $atSendPrice,
            'at_send_disct' => min($atSendPrice, $orderInfo->at_send_disct),
            'at_send_sub_disct' => $orderInfo?->at_send_sub_disct,
            'at_disct' => $orderInfo->at_disct,
            'at_cpn_disct' => $orderInfo->at_cpn_disct,
            'cd_gas_kind' => $orderInfo?->cd_gas_kind,
            'at_gas_price' => $orderInfo?->at_gas_price,
            'at_price' => $orderInfo->at_price,
            'at_price_pg' => $orderInfo->at_price_pg,
            'cd_status' => $orderInfo->cd_status,
            'nm_status' => $orderInfo->nm_status,
            'list_product' => $orderInfo->list_product,
            'is_direct_order' => in_array($orderInfo->cd_status, [
                    '800400',
                    '800410'
                ]) && DirectOrderService::hasDirectOrder([
                'no_user' => Auth::id(),
                'no_order' => $noOrder
            ]),
            'pickup_type' => $orderInfo?->pickup_type,
            'is_car_pickup' => $orderInfo?->is_car_pickup,
            'is_shop_pickup' => $orderInfo?->is_shop_pickup,
            'no_shop' => $orderInfo?->no_shop,
            'no_site' => $orderInfo?->no_site,
            'cd_card_corp' => $orderInfo->card?->cd_card_corp,
            'card_corp' => CodeService::getCode($orderInfo->card?->cd_card_corp)->nm_code ?? '',
            'no_card_user' => $orderInfo->card?->no_card_user,
            'ds_car_number' => $orderInfo->ds_car_number,
            'dt_entry_time' => $orderInfo->dt_entry_time?->format('Y-m-d H:i:s'),
            'dt_exit_time' => $orderInfo->dt_exit_time?->format('Y-m-d H:i:s'),
            'ds_res_order_no' => $orderInfo->ds_res_order_no ?? $orderInfo->orderPayment?->ds_res_order_no,
            'no_approval' => $orderInfo->no_approval,
            'dt_res' => $orderInfo->dt_res?->format('Y-m-d H:i:s') ?? $orderInfo->orderPayment?->dt_res?->format('Y-m-d H:i:s'),
            'pg_bill_result' => CodeService::getCode($orderInfo->cd_pg_bill_result ?? $orderInfo->orderPayment?->cd_pg_bill_result)?->nm_code,
            'ds_res_msg' => $orderInfo->ds_res_msg ?? $orderInfo->orderPaymnet?->ds_res_msg,
            'is_review' => $bizKind == SearchBizKind::FNB->name && $orderInfo->cd_status == '800410' && ReviewService::getReview([
                    'no_user' => Auth::id(),
                    'no_order' => $orderInfo->no_order
                ])->count() <= 0,
            'ds_address' => $orderInfo?->ds_address,
            'ds_address2' => $orderInfo?->ds_address2,
            'ds_display_ark_id' => $orderInfo?->shopOilUnit?->ds_display_ark_id,
            'at_liter_real' => $orderInfo?->at_liter_real
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 주문 알람
     */
    public function gpsAlarm(Request $request): JsonResponse
    {
        $request->validate([
            'no_shop' => 'required|integer',
            'no_order' => 'required|string',
            'cd_alarm_event_type' => 'required',
            'at_lat' => 'nullable',
            'at_lng' => 'nullable',
            'at_distance' => 'nullable',
            'at_yn_gps_statuslng' => 'nullable',
        ]);

        $orderService = new OrderService();

        $orderList = $orderService->getOrderInfo([
            'no_user' => Auth::id(),
            'no_order' => $request->input('no_order')
        ])->whenEmpty(function () {
            throw new OwinException(Code::message('P2120'));
        })->first();

        $parameter = [
            'at_distance' => $request->input('at_distance'),
            'cd_alarm_event_type' => $request->input('cd_alarm_event_type')
        ];
        if (in_array($request->input('cd_alarm_event_type'), ['607300', '607350'])) {
            $parameter['cd_call_shop'] = match ($request->input('cd_alarm_event_type')) {
                '607300' => '611200',
                '607350' => '611300',
                default => null
            };
        }
        $whereNot = match (in_array($request->input('cd_alarm_event_type'), ['607100', '607200'])) {
            true => ['cd_alarm_event_type' => ['!=', '607300']],
            default => []
        };

        $orderService->updateOrderList($parameter, [
            'no_order' => $request->input('no_order')
        ], $whereNot);

        (new OrderAlarmEventLog([
            'cd_alarm_event_type' => $request->input('cd_alarm_event_type'),
            'no_order' => $orderList->no_order,
            'no_shop' => $orderList->no_shop,
            'no_user' => $orderList->no_user
        ]))->saveOrFail();

        if (in_array($request->input('cd_alarm_event_type'), ['607100', '607200'])) {
            Ark::client(env('ARK_API_PATH_ORDER'), [
                'body' => sprintf('%s0', $request->no_shop)
            ]);
        }

        if ($request->input('cd_alarm_event_type') == '607350') {
            Ark::client(env('ARK_API_PATH_CALL'), [
                'body' => sprintf('%s%s', $request->input('no_shop'), $request->input('no_order'))
            ]);

            try {
                (new Fcm(
                    'arrived',
                    (int)$request->input('no_shop'),
                    $request->input('no_order'),
                    [
                        'no_order' => $request->input('no_order'),
                        'no_order_user' => makeNoOrderUser(
                            $request->input('no_order')
                        ),
                        'ds_maker' => Auth::user()->memberCarInfo?->carList?->ds_maker,
                        'ds_kind' => Auth::user()->memberCarInfo?->carList?->ds_kind,
                        'ds_car_number' => $orderList->ds_car_number,
                        'isCurrent' => true,
                        'channel_id' => 'arrived',
                    ]
                ))->init();
            } catch (Throwable $t) {
                Log::channel('slack')->critical('FCM: ', [$t->getMessage()]);
            }

            if (SearchBizKindDetail::getBizKindDetail($orderList->partner->cd_biz_kind_detail)
                == SearchBizKindDetail::SPC
            ) {
                Spc::uptime(
                    $orderList->partner->cd_spc_brand,
                    $orderList->shop->cd_spc_store,
                    $orderList->no_order
                );
            }
        }

        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @return JsonResponse
     *
     * 회원 주문 갯수
     */
    public function historyCnt(): JsonResponse
    {
        $orderService = new OrderService();

        $orderCount = $orderService->getOrderCount([
            'order_list.cd_service' => '900100',
            'order_list.no_user' => Auth::id()
        ], [
            'order_list.cd_pickup_status' => ['<', '602400'],
            'order_list.cd_third_party' => ['!=', '110200']
        ]);

        return response()->json([
            'result' => true,
            'order_cnt' => $orderCount
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     * @throws TMapException
     *
     * 주문 시작
     */
    public function init(Request $request): JsonResponse
    {
        $request->validate([
            'cd_service' => ['required', Rule::in(ServiceCode::keys())],
            'no_shop' => 'required|numeric:8',
            'at_price_total' => 'required|numeric',
            'list_product' => 'required|array',
            'pickup_type' => 'nullable',
        ]);

        if ($request->pickup_type == Pickup::CAR->name && empty(Auth::user()->memberCarInfo->seq)) {
            throw new OwinException(Code::message('PA141'));
        }

        $shop = ShopService::getShop($request->no_shop);
        if ($shop->ds_status != EnumYN::Y->name) {
            throw new OwinException(Code::message('M1304'));
        }

        $couponService = new CouponService();
        $shopService = new ShopService();

        $atSendPrice = match ($request->pickup_type) {
            Pickup::SHOP->name => 0,
            default => $shop->at_order_send_price
        };

        $atSendDisct = match ($request->pickup_type) {
            Pickup::SHOP->name => 0,
            default => min($atSendPrice, data_get($shop, 'at_send_disct', 0))
        };

        $user = Auth::user();
        return response()->json([
            'result' => true,
            'at_make_ready_time' => match (SearchBizKindDetail::getBizKindDetail($shop->partner->cd_biz_kind_detail)) {
                SearchBizKindDetail::SPC => match ($request->pickup_type) {
                    'SHOP' => $shop->partner->at_make_ready_time,
                    default => $shop->at_make_ready_time
                },
                default => $shop->at_make_ready_time
            },

            'at_commission_rate' => match ($shop->cd_commission_type == '205300') {
                true => $shop->at_commission_rate,
                default => 0
            },
            'at_send_price' => $atSendPrice,
            'at_send_disct' => $atSendDisct,
            'at_send_sub_disct' => match ($request->pickup_type) {
                Pickup::SHOP->name => 0,
                default => match (!empty($user->useSubscription?->benefit?->{BenefitType::SEND->name})) {
                    true => match (SearchBizKind::getBizKind($shop->partner->cd_biz_kind) == SearchBizKind::FNB && BenefitDetailType::saleUse(Auth::user()->useSubscription?->benefit->{BenefitType::SEND->name}->type)) {
                        true => (function () use ($user, $shop, $atSendPrice) {
                            $percent = $user->useSubscription?->benefit->{BenefitType::SEND->name}->{BenefitDetailType::SALE->name}->price * 0.01;
                            return floor(($atSendPrice * $percent) * 0.1) * 10;
                        })(),
                        default => 0
                    },
                    default => 0
                }
            },
            'is_car_pickup' => match (SearchBizKind::getBizKind($shop->partner->cd_biz_kind)) {
                SearchBizKind::FNB => $shop->shopDetail->yn_car_pickup == 'Y',
                default => true
            },
            'is_shop_pickup' => $shop->shopDetail->is_shop_pickup,
            'is_booking_pickup' => $shop->shopDetail->is_booking_pickup,
            'ds_car_number' => match ($request->pickup_type) {
                Pickup::SHOP->name => null,
                default => Auth::user()->memberCarInfo->ds_car_number
            },
            'shop_opt_info' => $shopService->getOperate($shop->no_shop),
            'shop_holiday_info' => $shopService->getHoliday($shop->no_shop),
            'coupon_info' => match (SearchBizKind::getBizKind($shop->partner->cd_biz_kind)) {
                SearchBizKind::FNB => $couponService->getFnbUsableCoupon(
                    Auth::id(),
                    $shop->no_shop,
                    $request->at_price_total,
                    collect($request->list_product)
                ),
                SearchBizKind::RETAIL => $couponService->getRetailUsableCoupon(
                    Auth::id(),
                    $shop->no_partner,
                    $request->at_price_total
                ),
                default => null
            },
            'benefit' => match (SearchBizKind::getBizKind($shop->partner->cd_biz_kind) == SearchBizKind::FNB && BenefitDetailType::saleUse(Auth::user()->useSubscription?->benefit->{BenefitType::FNB->name}->type)) {
                true => Auth::user()->useSubscription?->benefit->{BenefitType::FNB->name}->{BenefitDetailType::SALE->name},
                default => null
            },
            'at_pg_min_price' => Code::conf('pg_min_price'),
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     * @throws TMapException
     *
     * 주문 결제
     */
    public function payment(Request $request): JsonResponse
    {
        $request->validate([
            'cd_service' => ['required', Rule::in(ServiceCode::keys())],
            'cd_service_pay' => ['required', Rule::in(ServicePayCode::keys())],
            'pickup_type' => ['required', Rule::in(Pickup::keys())],
            'no_shop' => 'required|numeric:8',
            'at_price_total' => 'required|numeric',
            'at_price_calc' => 'required|numeric',
            'no_card' => 'required|numeric',
            'car_number' => 'required_if:pickup_type,CAR',
            'arrived_time' => 'required|date_format:"Y-m-d H:i:s',
            'at_commission_rate' => 'numeric',
            'at_send_price' => 'nullable|numeric',
            'at_send_disct' => 'nullable|numeric',
            'at_send_sub_disct' => 'nullable|numeric',
            'at_cpn_disct' => 'required|numeric',
            'at_cup_deposit' => 'required|numeric',
            'at_disct' => 'nullable|numeric',
            'at_request_msg' => 'nullable|string|max:40',
            'discount_info' => 'nullable|array',
            'ds_address' => 'nullable|string',
            'ds_address2' => 'nullable|string',
            'list_product' => 'required|array',
        ]);

        if ($request->pickup_type == Pickup::CAR->name) {
            Auth::user()->memberCarInfoAll->where('ds_car_number', $request->car_number)->whenEmpty(function () {
                throw new OwinException(Code::message('M1510'));
            });
        }

        $shop = ShopService::getShop($request->no_shop);
        if ($shop->ds_status != EnumYN::Y->name) {
            throw new OwinException(Code::message('M1304'));
        }

        $orderService = new OrderService();
        $response = $orderService->payment(Auth::user(), $shop, collect($request->post()));
        if ($response['result'] === true) {
            if (SearchBizKindDetail::sendArk($shop->partner->cd_biz_kind_detail)) {
                Ark::client(env('ARK_API_PATH_PAYMENT'), [
                    'body' => sprintf('%s00000000%s', $request->no_shop, $response['no_order'])
                ]);
            }

            try {
                (new Fcm('neworder', $request->no_shop, $response['no_order'], [
                    'no_order' => $response['no_order'],
                    'no_order_user' => makeNoOrderUser($response['no_order']),
                    'nm_order' => $response['nm_order'],
                    'isCurrent' => true,
                    'channel_id' => 'neworder',
                ]))->init();
            } catch (Throwable $t) {
                Log::channel('slack')->critical('FCM: ', [$t->getMessage()]);
            }
        }

        return response()->json([
            'result' => $response['result'],
            'no_order' => $response['no_order'],
            'message' => $response['msg'],
            'detail_message' => $response['pg_msg']
        ]);
    }

    /**
     * @param string $noOrder
     * @return JsonResponse
     *
     * 주문 상태 변경 시각
     */
    public function orderStatusHistory(string $noOrder): JsonResponse
    {
        $orderStatusHistory = OrderService::getOrderStatusHistory([
            'no_user' => Auth::id(),
            'no_order' => $noOrder
        ])->first();

        $orderStatus = getOrderStatus(
            cdBizKind: $orderStatusHistory->partner->cd_biz_kind,
            cdOrderStatus: $orderStatusHistory['cd_order_status'],
            cdPickupStatus: $orderStatusHistory['cd_pickup_status'],
            cdPaymentStatus: $orderStatusHistory['cd_payment_status'],
            cdBizKindDetail: $orderStatusHistory->partner->cd_biz_kind_detail,
        );

        return response()->json([
            'result' => true,
            'order_status' => Arr::last($orderStatus),
            'biz_kind_detail' => SearchBizKindDetail::getBizKindDetail($orderStatusHistory->partner->cd_biz_kind_detail)?->name,
            'ds_spc_order' => $orderStatusHistory->ds_spc_order,
            'dt_reg' => $orderStatusHistory->dt_reg->format('Y-m-d H:i:s'),
            'dt_pickup' => $orderStatusHistory->dt_pickup->format('Y-m-d H:i:s'),
            'order_reserve' => $orderStatusHistory->dt_payment_status,
            'order_confirm' => $orderStatusHistory->confirm_date,
            'order_ready' => $orderStatusHistory->ready_date,
            'order_pickup' => $orderStatusHistory->pickup_date,
            'pickup_type' => match (SearchBizKind::getBizKind($orderStatusHistory->partner->cd_biz_kind)) {
                SearchBizKind::FNB => Pickup::tryFrom((int)$orderStatusHistory->cd_pickup_type)->name,
                SearchBizKind::RETAIL => Pickup::CAR->name,
                default => null,
            },
            'employee_call' => $orderStatusHistory->cd_alarm_event_type >= '607350' && $orderStatusHistory->cd_alarm_event_type != '607505',
            'no_oil_in_shop' => $orderStatusHistory->shop?->oilInShop?->no_shop_in,
            'safe_number' => match (empty($orderStatusHistory->ds_safe_number) == false) {
                false => null,
                default => substr($orderStatusHistory->ds_safe_number, -4)
            },
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 회원 주문 목록
     */
    public function getOrderList(Request $request): JsonResponse
    {
        return (new Member($request))->getOrderList($request);
    }

    /**
     * @param Request $request
     * @param string $bizKind
     * @return JsonResponse
     * @throws ValidationException
     *
     * 회원 주문 목록 (업종구분)
     */
    public function getOrderListByBizKind(Request $request, string $bizKind): JsonResponse
    {
        Validator::make([
            'bizKind' => $bizKind
        ], [
            'bizKind' => Rule::in(SearchBizKind::keys())
        ])->validate();

        $size = (int)$request->get('size') ?: Code::conf('default_size');
        $offset = (int)$request->get('offset') ?: 0;

        $items = match ($bizKind) {
            SearchBizKind::PARKING->name => OrderService::getParkingOrderList(Auth::id(), $size, $offset),
            default => OrderService::getOrderList(Auth::id(), $bizKind, $size, $offset)
        };

        return response()->json([
            'result' => true,
            'total_cnt' => $items->total(),
            'per_page' => $items->perPage(),
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'order_list' => collect($items->items())->map(function ($list) {
                $orderStatus = getOrderStatus(
                    cdBizKind: $list->cd_biz_kind,
                    cdOrderStatus: $list->cd_order_status,
                    cdPickupStatus: $list->cd_pickup_status,
                    cdPaymentStatus: $list->cd_payment_status,
                    parkingStatus: $list->cd_parking_status,
                    cdBizKindDetail: $list->cd_biz_kind_detail,
                );
                return [
                    'no_order' => $list->no_order,
                    'nm_order' => $list->nm_order,
                    'cd_order_status' => Arr::first($orderStatus),
                    'order_status' => Arr::last($orderStatus),
                    'dt_reg' => $list->dt_reg->format('Y-m-d H:i:s'),
                    'nm_partner' => $list->nm_partner,
                    'no_shop' => $list->no_shop ?? $list->id_site,
                    'nm_shop' => $list->shop?->nm_shop ?? $list->parkingSite?->nm_shop,
                    'cd_biz_kind' => $list->cd_biz_kind,
                    'biz_kind' => SearchBizKind::getBizKind((string)$list->cd_biz_kind)->name,
                    'pickup_type' => match (!empty($list->cd_pickup_type)) {
                        true => match (!empty(Pickup::tryFrom((int)$list->cd_pickup_type))) {
                            true => Pickup::tryFrom((int)$list->cd_pickup_type)->name,
                            default => Pickup::CAR->name
                        },
                        default => Pickup::CAR->name
                    },
                ];
            })
        ]);
    }

    /**
     * @param string $bizKind
     * @param string $noOrder
     * @return JsonResponse
     * @throws OwinException
     *
     * 미결제 주문내역 확인
     */
    public function getIncompleteOrder(string $bizKind, string $noOrder): JsonResponse
    {
        $orderInfo = match ($bizKind) {
            SearchBizKind::PARKING->name => OrderService::getParkingOrderInfo([
                'no_user' => Auth::id(),
                'no_order' => $noOrder
            ])->first(),
            default => OrderService::getOrderInfo([
                'no_user' => Auth::id(),
                'no_order' => $noOrder
            ])->first()
        };

        if (!$orderInfo) {
            throw new OwinException(Code::message('P2028'));
        }

        if (!in_array($orderInfo['cd_status'], ['800800', '800810'])) {
            throw new OwinException(Code::message('P2029'));
        }

        return response()->json([
            'result' => true,
            'order' => [
                'nm_shop' => $orderInfo->nm_shop ?? $orderInfo->parkingSite?->nm_shop ?? $orderInfo->autoParking?->nm_shop,
                'no_order' => $orderInfo->no_order,
                'no_order_user' => substr($orderInfo->no_order, -7),
                'biz_kind' => $bizKind,
                'nm_order' => $orderInfo->nm_order,
                'dt_reg' => $orderInfo->dt_reg->format('Y-m-d H:i:s'),
                'at_commission_rate' => $orderInfo->at_commission_rate,
                'at_send_price' => $orderInfo?->at_send_price,
                'at_send_disct' => $orderInfo?->at_send_disct,
                'at_send_sub_disct' => $orderInfo?->at_send_sub_disct,
                'at_disct' => $orderInfo->at_disct,
                'at_cpn_disct' => $orderInfo->at_cpn_disct,
                'at_price' => $orderInfo->at_price,
                'at_price_pg' => $orderInfo->at_price_pg,
                'cd_status' => $orderInfo->cd_status,
                'nm_status' => $orderInfo->nm_status,
                'no_shop' => $orderInfo?->no_shop,
                'no_site' => $orderInfo?->no_site,
            ],
            'cards' => Auth::user()->memberCard->unique('no_card')->map(function ($card) {
                return [
                    'no_card' => $card->no_card,
                    'no_card_user' => $card->no_card_user,
                    'cd_card_corp' => $card->cd_card_corp,
                    'card_corp' => CodeService::getCode($card->cd_card_corp)->nm_code ?? '',
                    'yn_main_card' => $card->yn_main_card
                ];
            })->sortByDesc('yn_main_card')->values()
        ]);
    }
}

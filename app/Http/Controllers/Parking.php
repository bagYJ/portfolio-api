<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ParkingStatus;
use App\Enums\Pg;
use App\Enums\ServicePayCode;
use App\Exceptions\OwinException;
use App\Queues\Fcm\Fcm;
use App\Services\CodeService;
use App\Services\CouponService;
use App\Services\OrderService;
use App\Services\ParkingService;
use App\Utils\Code;
use App\Utils\Parking as ParkingUtil;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class Parking extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 주차 주문 정보 조회
     */
    public function intro(Request $request): JsonResponse
    {
        $request->validate([
            'no_site' => 'required|integer',
            'at_price_total' => 'required|numeric',
        ]);

        $member = Auth::user();
        $noShop = intval($request->get('no_site'));

        //이전주문내역
        $orderInfo = (new ParkingService())->ordering($member->no_user, [
            'no_site' => ['=', $noShop],
            'cd_parking_status' => ['=', 'WAIT'],
            'cd_order_status' => ['=', '601200'],
            'cd_payment_status' => ['=', '603300'],
        ])->first();

        $response = [
            'no_order' => $orderInfo?->no_order,
            'cars' => $member->memberCarInfoAll->sortByDesc('yn_main_car'),
            'cards' => $member->memberCard->filter(function ($query) {
                return $query['cd_pg'] == Pg::kcp->value;
            })->map(function ($collect) {
                return [
                    'no_seq' => $collect->no_seq,
                    'cd_card_corp' => $collect->cd_card_corp, //const 로 변경 필요
                    'card_corp' => CodeService::getCode($collect->cd_card_corp)->nm_code ?? '',
                    'no_card' => $collect->no_card,
                    'no_card_user' => $collect->no_card_user,
                    'nm_card' => $collect->nm_card,
                    'yn_main_card' => $collect->yn_main_card,
                    'yn_credit' => $collect->yn_credit,
                ];
            })->sortByDesc('yn_main_card')->values(),
            'coupons' => (new CouponService())->getParkingUsableCoupon(
                $member['no_user'],
                $noShop,
                intval($request->at_price_total)
            ),
        ];

        return response()->json($response);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * 지도 위치 기준으로 주차장 조회
     */
    public function gets(Request $request): JsonResponse
    {
        $request->validate([
            'radius' => 'required',
            'position' => 'required|array'
        ]);

        $parkingSite = ParkingService::gets($request->radius, data_get($request->position, 'x'), data_get($request->position, 'y'));
        if (count($parkingSite)) {
            return response()->json([
                'result' => true,
                'rows' => $parkingSite,
            ]);
        } else {
            throw new OwinException(Code::message('404'));
        }
    }

    /**
     * @param string $noSite
     * @return JsonResponse
     * @throws OwinException
     *
     * 주차장 단일 조회
     */
    public function get(string $noSite): JsonResponse
    {
        $parkingSite = ParkingService::get([
            'no_site' => $noSite,
            'ds_status' => 'Y'
        ]);
        return response()->json([
            'result' => true,
            ...$parkingSite->toArray()
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * 티켓 구매 및 결제
     */
    public function orderTicket(Request $request): JsonResponse
    {
        $request->validate([
            'no_site' => 'required|integer',
            'no_product' => 'required|integer',
            'cd_service_pay' => ['required', Rule::in(ServicePayCode::keys())],
            'at_price_total' => 'required|numeric',
            'at_cpn_disct' => 'required|numeric',
            'at_commission_rate' => 'numeric',
            'no_card' => 'required|numeric',
            'car_number' => 'required',
            'discount_info' => 'nullable',
        ]);

        if (empty(Auth::user()->memberCarInfo->seq)) {
            throw new OwinException(Code::message('PA141'));
        }

        $parkingSite = ParkingService::get([
            'no_site' => $request->get('no_site'),
            'ds_status' => 'Y'
        ]);

        $orderService = new OrderService();
        $response = $orderService->parkingPayment(Auth::user(), $parkingSite, collect($request->post()));
        if ($response['result'] === true) {
            try {
                (new Fcm('neworder', intval($request->input('no_shop')), (string)$response['no_order'], [
                    'no_order' => (string)$response['no_order'],
                    'no_order_user' => makeNoOrderUser((string)$response['no_order']),
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
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * 주차장 할인권 구매 정보 내역 조회
     */
    public function getMyTickets(Request $request): JsonResponse
    {
        $response = ParkingService::getOrderList(['no_user' => Auth::id()]);
        if ($response && $response['rows']) {
            return response()->json($response);
        } else {
            throw new OwinException(Code::message('404'));
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * 구매한 웹 할인권 조회
     */
    public function getTicket(Request $request): JsonResponse
    {
        $request->validate([
            'no_order' => 'required',
        ]);

        return response()->json(ParkingService::getOrderInfo(Auth::id(), $request->get('no_order')));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * 웹 할인권 취소
     */
    public function cancelTicket(Request $request): JsonResponse
    {
        $request->validate([
            'no_order' => 'required|string',
        ]);
        $noOrder = $request->get('no_order');
        $order = ParkingService::getOrderInfo(Auth::id(), $noOrder);

        $parkingSite = ParkingService::get([
            'no_parking_site' => $order['no_parking_site']
        ]);

        $ticket = ParkingUtil::getTicket($order['no_booking_uid']);
        if ($ticket['status'] == ParkingStatus::USED->name || $order['cd_parking_status'] == ParkingStatus::USED->name) {
            throw new OwinException(Code::message('P2100'));
        } elseif (empty($order['dt_user_parking_canceled']) == false) {
            throw new OwinException(Code::message('P2401'));
        }

        $orderService = new OrderService();
        $response = $orderService->parkingRefund(
            user: Auth::user(),
            shop: $parkingSite,
            noOrder: $noOrder,
            cdOrderStatus: '601900',
            nmPg: Pg::incarpayment_kcp->name
        );

        return response()->json([
            'result' => $response['res_cd'] == '0000',
            'message' => $response['res_msg']
        ]);
    }

    /**
     * @return array
     * @throws OwinException
     *
     * 자동취소 배치
     */
    public function autoCancelTicket(): array
    {
        $orders = ParkingService::getOrderList([
            ['cd_parking_status', '=', 'WAIT'],
            ['cd_payment_status', '=', '603300'],
            ['cd_order_status', '=', '601200'],
            ['dt_reg', '<', now()->format('Y-m-d 00:00:00')]
        ]);

        $count = 0;
        if (count($orders['rows'])) {
            try {
                $orderService = new OrderService();
                foreach ($orders['rows'] as $row) {
                    if ($row['user'] && $row['parkingSite']) {
                        $response = $orderService->parkingRefund(
                            user: $row['user'],
                            shop: $row['parkingSite'],
                            noOrder: $row['no_order'],
                            cdOrderStatus: '601999',
                            nmPg: Pg::incarpayment_kcp->name,
                            cdRejectReason: '606300'
                        );
                        $count ++;

                        if ($response['res_cd'] === '0000') {
                            try {
                                (new Fcm(
                                    'PARKING',
                                    $row['no_site'],
                                    $row['no_order'],
                                    [
                                        'ordering' => 'N',
                                        'nm_shop' => $row['parkingSite']['nm_shop'],
                                        'at_price_pg' => $row['at_price_pg']
                                    ],
                                    true,
                                    'user',
                                    $row['no_user'],
                                    'expire'
                                ))->init();
                            } catch (Throwable $t) {
                                Log::channel('slack')->critical('FCM: ', [$t->getMessage()]);
                            }
                        }
                    }
                }
                return [
                    'result' => true,
                    'count' => $count
                ];
            } catch (Exception $e) {
                return [
                    'result' => false,
                    'message' => $e->getMessage()
                ];
            }
        }
        return [
            'result' => false,
            'message' => Code::message('404')
        ];
    }

    /**
     * @param string $noOrder
     * @return JsonResponse
     *
     * 관리자 취소
     */
    public function adminCancelTicket(string $noOrder): JsonResponse
    {
        try {
            $orderService = new OrderService();
            $orders = ParkingService::getOrderList([
                'no_order' => $noOrder
            ]);

            $response = $orderService->parkingRefund(
                user: $orders['rows']->first()->user,
                shop: $orders['rows']->first()->parkingSite,
                noOrder: $orders['rows']->first()->no_order,
                cdOrderStatus: '601999',
                nmPg: Pg::incarpayment_kcp->name

            );

            return response()->json([
                'result' => $response['res_cd'] == '0000',
                'message' => $response['res_msg']
            ]);
        } catch (Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}

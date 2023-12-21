<?php

namespace App\Http\Controllers;

use App\Enums\EnumYN;
use App\Enums\GasKind;
use App\Enums\Pg;
use App\Exceptions\MobilXException;
use App\Exceptions\OwinException;
use App\Models\MemberAutoParkingHistory;
use App\Models\ParkingSite;
use App\Queues\Fcm\Fcm;
use App\Services\CarService;
use App\Services\CodeService;
use App\Services\MemberService;
use App\Services\OrderService;
use App\Services\ParkingService;
use App\Utils\AutoParking as AutoParkingUtil;
use App\Utils\Code;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Owin\OwinCommonUtil\CodeUtil;
use Owin\OwinCommonUtil\Enums\ServiceSchemaEnum;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class AutoParking extends Controller
{
    /**
     * @return JsonResponse
     *
     * 미결제내역 체크
     */
    public function checkPayment(): JsonResponse
    {
        $payment = ParkingService::checkPayment(Auth::id());
        if ($payment) {
            return response()->json([
                'result' => true,
                'order' => $payment,
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
        } else {
            throw new NotFoundHttpException();
        }
    }

    public function test()
    {
        $rows = AutoParkingUtil::parkingLotsList();
        $body = array();
        foreach ($rows AS $row) {
            $body[] = [
                'id_auto_parking' => $row['storeId'],
                'nm_shop' => $row['storeName'],
                'ds_category' => $row['storeCategory'],
                'ds_address' => $row['storeAddress'],
                'at_basic_price' => $row['addPrice'],
                'at_basic_time' => $row['addTime'],
                'at_lat' => $row['storeLatitude'],
                'at_lng' => $row['storeLongitude'],
                'auto_biz_type' => $row['parkBizType'],
                'auto_biz_time' => $row['parkBizTime'],
                'auto_sat_biz_type' => $row['parkSatBizType'],
                'auto_sat_biz_time' => $row['parkSatTime'],
                'auto_hol_biz_type' => $row['parkHolBizType'],
                'auto_hol_biz_time' => $row['parkHolTime'],
            ];
        }

        ParkingSite::insert($body);

        return response()->json($rows);
    }
    /**
     * @return JsonResponse
     *
     * 자동 결제 차량 정보 등록/해제
     */
    public function getMyAutoParking(): JsonResponse
    {

        ParkingService::checkAutoParkingPayment(Auth::id());
        $cars = CarService::getCars([
            ['no_user', '=', Auth::id()],
            ['yn_use_auto_parking', '=', EnumYN::Y->name],
            ['yn_delete', '=', 'N'],
            ['no_card', '<>', null]
        ])->map(function ($collect) {
            return [
                'ds_car_number' => $collect->ds_car_number,
                'cd_gas_kind' => $collect->cd_gas_kind,
                'gas_kind' => GasKind::from(intval($collect->cd_gas_kind))->name,
                'yn_use_auto_parking' => $collect->yn_use_auto_parking,
                'no_maker' => $collect->carList->no_maker,
                'ds_maker' => $collect->carList->ds_maker,
                'ds_kind' => $collect->carList->ds_kind,
                'card' => $collect->cards->unique('no_card')->map(function ($card) {
                    return [
                        'no_card' => $card->no_card,
                        'no_card_user' => $card->no_card_user,
                        'cd_card_corp' => $card->cd_card_corp,
                        'card_corp' => CodeService::getCode($card->cd_card_corp)->nm_code ?? '',
                    ];
                }),
            ];
        })->sortByDesc('dt_auto_parking')->values();

        return response()->json($cars);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     * @throws GuzzleException
     *
     * 자동 결제 차량 등록
     */
    public function registerCar(Request $request): JsonResponse
    {
        $request->validate([
            'yn_use_auto_parking' => ['required', Rule::in(EnumYN::keys()),],
            'ds_car_number' => 'required|string',
            'no_card' => 'nullable',
        ]);

        $ynUseAutoParking = $request->get('yn_use_auto_parking');
        $dsCarNumber = $request->get('ds_car_number');
        $noCard = $request->get('no_card');

        $carInfo = CarService::getCars([
            ['ds_car_number', '=', $dsCarNumber],
            ['yn_delete', '=', 'N'],
            ['yn_use_auto_parking', '=', 'Y'],
            ['no_card', '<>', null]
        ])->first();

        if ($ynUseAutoParking == 'Y' && $carInfo) {
            //등록시 기등록된 차량이 있으면 오류
            throw new OwinException(Code::message('AP0001'));
        } elseif ($ynUseAutoParking == 'N') {
            //해제시 미결제건이 있을 경우 오류
            (new ParkingService())->ordering(
                Auth::id(),
                ['cd_payment_status' => ['<', '603300'],]
            )->whenNotEmpty(function () {
                throw new OwinException(Code::message('P2400'));
            });
        }

        Auth::user()->memberCard->where('no_card', $noCard)->whenEmpty(function () {
            throw new OwinException(Code::message('P1020'));
        });

        Auth::user()->memberCarInfoAll->where('ds_car_number', $dsCarNumber)->whenEmpty(function () {
            throw new OwinException(Code::message('AP0007'));
        }, function () use ($dsCarNumber, $ynUseAutoParking, $noCard) {
            AutoParkingUtil::registerCar([$dsCarNumber], $ynUseAutoParking == 'Y');
            try {
                DB::beginTransaction();
                MemberService::updateAutoParkingInfo([
                    'no_user' => Auth::id(),
                    'ds_car_number' => $dsCarNumber
                ], [
                    'yn_use_auto_parking' => $ynUseAutoParking,
                    'no_card' => $ynUseAutoParking ? $noCard : null,
                    'dt_auto_parking' => Carbon::now(),
                ]);
                (new MemberAutoParkingHistory([
                    'no_user' => Auth::id(),
                    'ds_car_number' => $dsCarNumber,
                    'yn_use_auto_parking' => $ynUseAutoParking,
                    'no_card' => $ynUseAutoParking ? $noCard : null,
                ]))->saveOrFail();
                DB::commit();
            } catch (Throwable $t) {
                DB::rollBack();
                Log::channel('error')->error($t->getMessage(), [$t->getFile(), $t->getLine(), $t->getTraceAsString()]);
                throw new OwinException(Code::message($ynUseAutoParking == 'Y' ? 'AP0004' : 'AP0005'));
            }
        });
        return response()->json([
            'result' => true,
            'ds_car_number' => $dsCarNumber,
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws MobilXException
     * @throws Throwable
     *
     * MobilX -> OWIN 요청
     * 입차 차량 정보 전달
     */
    public function carEntered(Request $request): JsonResponse
    {
        $request->validate([
            'interfaceCode' => 'required|string',
            'plateNumber' => 'required|string',
            'storeId' => 'required|string',
            'storeName' => 'required|string',
            'entryTime' => 'required|string',
        ]);
        $plateNumber = $request->get('plateNumber');
        $shop = ParkingService::get([
            'id_site' => $request->get('storeId'),
        ]);

        $shop['cd_pg'] = Pg::kcp->value;

        // 스키마 순회하며 서비스를 구분한다
        // owin 에 기존 데이터가 남아있을 수도 있으니, 역순으로 조회한다
        $serviceCodeEnum = null;
        foreach (array_reverse(ServiceSchemaEnum::cases()) as $schema) {
            DB::statement('use ' . $schema->value);

            $carInfo = CarService::getCars([
                ['ds_car_number', '=', str_replace(' ', '', $plateNumber)],
                ['yn_use_auto_parking', '=', 'Y'],
                ['no_card', '<>', null]
            ])->first();

            if ($carInfo) {
                $serviceCodeEnum = CodeUtil::getServiceCodeEnumFromSchema($schema->value);
                break;
            }
        }

        if (!$serviceCodeEnum) {
            throw new MobilXException('IF_0003', 9004);
        }

        $response = (new OrderService())->autoParkingOrder($carInfo, $shop, collect($request->post()), $serviceCodeEnum);

        try {
            (new Fcm(
                'PARKING',
                $shop->no_site,
                $response['no_order'],
                [
                    'ordering' => 'Y',
                    'nm_shop' => $shop->nm_shop
                ],
                true,
                'user',
                $response['no_user'],
                'enter'
            ))->init();
        } catch (Throwable $t) {
            Log::channel('slack')->critical('FCM: ', [$t->getMessage()]);
        }

        return response()->json([
            'interfaceCode' => "IF_0003",
            'resultMessage' => "",
            'resultCode' => '0000',
            'plateNumber' => $plateNumber,
            'txId' => $response['no_order'],
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     * @throws OwinException
     *
     * 주차 비용 조회 요청
     */
    public function checkFee(Request $request): JsonResponse
    {
        $request->validate([
            'no_order' => 'required'
        ]);

        $noOrder = $request->get('no_order');
        $orderInfo = ParkingService::getAutoParkingOrderInfo([
            'no_user' => Auth::id(),
            'no_order' => $noOrder,
        ]);

        $feeInfo = AutoParkingUtil::checkFee($orderInfo['ds_car_number'], $orderInfo['id_site'], $noOrder);

        return response()->json($feeInfo);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws MobilXException
     *
     * MobilX -> OWIN 요청
     * 출차 차량 정보 전달
     */
    public function carExited(Request $request): JsonResponse
    {
        $request->validate([
            'interfaceCode' => 'required|string',
            'plateNumber' => 'required|string',
            'storeCategory' => 'required|string',
            'storeId' => 'required|string',
            'entryTime' => 'required|string',
            'exitTime' => 'required|string',
            'txId' => 'required|string',
            'paymentFee' => 'required|string',
        ]);
        $interfaceCode = $request->get('interfaceCode');

        $serviceSchemaEnum = CodeUtil::getServiceSchemaEnumFromOrderCode($request->txId);
        DB::statement('use ' . $serviceSchemaEnum->value);

        $orderInfo = ParkingService::getAutoParkingOrderInfo([
            'no_order' => $request->txId,
        ], $interfaceCode);

        if ($orderInfo['cd_pg_result'] == '604100') {
            throw new MobilXException($interfaceCode, 9008);
        }

        //주차장 조회
        $shop = ParkingService::get([
            'id_site' => $request->get('storeId'),
        ]);
        if (!$shop) {
            throw new MobilXException('IF_0003', 9004);
        }
        $shop['cd_pg'] = Pg::kcp->value;
        //차량조회
        $carInfo = CarService::getCars([
            ['ds_car_number', '=', str_replace(' ', '', $request->get('plateNumber'))],
            ['yn_use_auto_parking', '=', 'Y'],
            ['no_card', '<>', null]
        ])->whenEmpty(function () {
            throw new MobilXException('IF_0005', 9004);
        })->first();

        OrderService::createParkingOrderProcess([
            'no_order' => $request->txId,
            'no_user' => $carInfo->no_user,
            'id_site' => $shop->id_site,
            'id_auto_parking' => $shop->id_site,
            'cd_order_process' => '616604'
        ]);

        $response = (new OrderService())->autoParkingPayment($orderInfo, $shop, $carInfo, collect($request->post()));
        if ($response['result'] === true) {
            try {
                (new Fcm(
                    'PARKING',
                    $shop->no_site,
                    $response['no_order'],
                    [
                        'ordering' => 'N',
                        'nm_shop' => $shop->nm_shop,
                        'at_price_pg' => number_format($response['at_price_pg'])
                    ],
                    true,
                    'user',
                    $response['no_user'],
                    'complete'
                ))->init();
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


}

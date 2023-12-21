<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\TMapException;
use App\Services\TmapService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Tmap extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 회원인증
     */
    public function authorization(Request $request): JsonResponse
    {
        $request->validate([
            'ci' => 'required',
        ]);

        $response = (new TmapService())->authorization($request);
        return response()->json([
            'result' => "1",
            'access_token' => $response->plainTextToken
        ]);
    }

    /**
     * @return JsonResponse
     *
     * 로그아웃
     */
    public function logout(): JsonResponse
    {
        Auth::user()->tokens()->delete();

        return response()->json([
            'result' => true,
        ]);
    }

    /**
     * @return JsonResponse
     *
     * 회원 정보 리턴
     */
    public function getInfo(): JsonResponse
    {
        return response()->json((new TmapService())->getMemberInfo(Auth::id()));
    }

    /**
     * @return JsonResponse
     *
     * userCarList:: 회원의 등록한 자동차 리스트를 가지고 온다
     */
    public function userCarList(): JsonResponse
    {
        $cars = Auth::user()->memberCarInfoAll->map(function ($car) {
            return [
                'no_seq' => intval($car['seq']),
                'ds_kind' => $car->carList->ds_kind,
                'no_maker' => (string)$car->carList->no_maker,
                'ds_maker' => $car->carList->ds_maker,
                'ds_car_number' => $car->ds_car_number,
                'cd_gas_kind' => $car->cd_gas_kind,
            ];
        });

        return response()->json([
            'result' => "1",
            'car_info' => $cars,
        ]);
    }

    /**
     * @return JsonResponse
     * @throws TMapException
     *
     * userCardList:: 회원이 등록한 카드리스트
     */
    public function userCardList(): JsonResponse
    {
        $user = Auth::user();
        if ($user->memberDetail->yn_account_status_rsm !== 'Y') {
            throw new TMapException('M1505', 400);
        }

        $memberCards = Auth::user()->memberCard->unique('no_card')->map(
            function ($card, $index) {
                return [
                    'no_card'           => (string)$card['no_card'],
                    'no_card_user'      => $card['no_card_user'],
                    'cd_card_corp'      => $card['cd_card_corp'],
                    'cd_payment_method' => '504100',
                    'dt_reg'            => Carbon::createFromFormat(
                        'Y-m-d H:i:s',
                        $card['dt_reg']
                    )->format('Y-m-d H:i:s'),
                    'img_card'          => $card['img_card'],
                    'yn_main_card'      => $index === 0 ? 'Y' : 'N',
                ];
        })->values();

        return response()->json([
            'result' => '1',
            'list_card' => $memberCards
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws TMapException
     *
     * 주문내역
     */
    public function userOrderList(Request $request): JsonResponse
    {
        $request->validate([
            'cd_service' => 'required|string',
            'ct_page_now' => 'nullable|integer',
            'ct_page_num' => 'nullable|integer'
        ]);

        if ($request->get('cd_service') != '900100') {
            throw new TMapException('C0900', 400);
        }

        $currentPage = intval($request->get('ct_page_now') ?? 1);
        $size = intval($request->get('ct_page_num') ?? 10);
        $response = (new TmapService())->getOrderList(Auth::id(), $currentPage, $size);

        return response()->json([
            'result'        => "1",
            'ct_page_now'   => $currentPage,
            'ct_page_num'   => $size,
            'ct_page_total' => ceil($response['count'] / $size),
            'ct_total'      => $response['count'],
            'list_order'    => $response['rows'],
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 주문상세
     */
    public function userOrderDetail(Request $request): JsonResponse
    {
        $request->validate([
            'no_order' => 'required',
        ]);
        
        return response()->json(
            (new TmapService())->getOrderDetail($request->get('no_order'), Auth::user())
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 주유소 리스트
     */
    public function opinetList(Request $request): JsonResponse
    {
        $list = (new TmapService())->getOilShopList($request->all());
        return response()->json($list);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 주유소 단일 조회
     */
    public function get(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:OWIN,OPINET',
            'code' => 'required'
        ]);

        $shop = (new TmapService())->getOilShopList($request->all())->whenEmpty(function () {
            throw new TMapException('M1303', 400);
        })->first();

        return response()->json($shop);
    }
}

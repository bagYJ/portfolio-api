<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MemberLevel;
use App\Enums\Pg;
use App\Enums\SearchBizKind;
use App\Enums\SearchBizKindDetail;
use App\Exceptions\OwinException;
use App\Exceptions\TMapException;
use App\Queues\Fcm\Fcm;
use App\Queues\Rkm\Rkm;
use App\Services\CodeService;
use App\Services\OrderService;
use App\Services\ShopService;
use App\Utils\Ark;
use App\Utils\Code;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class Payment extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     * @throws TMapException
     *
     * 결제 취소
     */
    public function cancel(Request $request): JsonResponse
    {
        $request->validate([
            'no_order' => 'required|string',
            'cd_reject_reason' => 'nullable|string',
        ]);

        // 주문정보조회
        $orderInfo = OrderService::getOrder($request->no_order);
        if (!$orderInfo) {
            throw new OwinException(Code::message('P2120'));
        }

        $pgName = match ($orderInfo->cd_pg) {
            500600 => Pg::incarpayment_kcp->name,
            default => Pg::from($orderInfo->cd_pg)->name
        };

        $response = (new OrderService())->refund(
            user: Auth::user(),
            shop: ShopService::getShop($orderInfo->no_shop),
            noOrder: $orderInfo->no_order,
            cdOrderStatus: '601900',
            nmPg: $pgName,
            reason: CodeService::getCode($request->cd_reject_reason)?->nm_code
        );

        if ($response['res_cd'] === '0000' && SearchBizKindDetail::sendArk($orderInfo->partner->cd_biz_kind_detail)) {
            Ark::client(env('ARK_API_PATH_ORDER'), [
                'body' => sprintf('%s1', $orderInfo->no_shop)
            ]);
        }

        return response()->json([
            'result' => match ($response['res_cd']) {
                '0000' => true,
                default => false
            },
            'message' => $response['res_msg']
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     * @throws TMapException
     *
     * 관리자 PG 결제 취소
     */
    public function cancelAdmin(Request $request): JsonResponse
    {
        $request->validate([
            'no_order' => 'required|string',
            'cd_reject_reason' => 'nullable|string',
        ]);

        // 주문정보조회
        $orderInfo = OrderService::getOrder($request->no_order);
        if (!$orderInfo) {
            throw new OwinException(Code::message('P2120'));
        }

        $pgName = match ($orderInfo->cd_pg) {
            500600 => Pg::incarpayment_kcp->name,
            default => Pg::from($orderInfo->cd_pg)->name
        };

        $response = (new OrderService())->refundAdmin(
            shop: ShopService::getShop($orderInfo->no_shop),
            noOrder: $orderInfo->no_order,
            cdOrderStatus: '601999',
            nmPg: $pgName,
            reason: $request->cd_reject_reason
        );

        if ($response['res_cd'] === '0000') {
            $nmShop = sprintf('%s %s', $orderInfo->partner->nm_partner, $orderInfo->shop->nm_shop);

            if (SearchBizKindDetail::sendArk($orderInfo->partner->cd_biz_kind_detail)) {
                Ark::client(env('ARK_API_PATH_ORDER'), [
                    'body' => sprintf('%s1', $orderInfo->no_shop)
                ]);
            }

            try {
                (new Fcm(SearchBizKind::getBizKind($orderInfo->partner->cd_biz_kind)->name, $orderInfo->no_shop, $orderInfo->no_order, [
                    "ordering" => 'N',
                    "nm_shop" => $nmShop,
                    'biz_kind_detail' => SearchBizKindDetail::getBizKindDetail($orderInfo->partner->cd_biz_kind_detail)?->name
                ], true, 'user', $orderInfo->no_user, 'cancel_etc'))->init();

                if ($orderInfo->member->cd_mem_level == MemberLevel::AVN->value && empty($orderInfo->member->memberDetail->ds_access_vin_rsm) === false) {
                    (new Rkm(
                        vin: $orderInfo->member->memberDetail->ds_access_vin_rsm,
                        title: sprintf(Code::fcm('user.RETAIL.cancel_etc.title'), ''),
                        body: $nmShop . ' ' . Code::fcm('user.RETAIL.cancel_etc.body')
                    ))->init();
                }
            } catch (Throwable $t) {
                Log::channel('slack')->critical('FCM: ', [$t->getMessage()]);
            }
        }

        return response()->json([
            'result' => match ($response['res_cd']) {
                '0000' => true,
                default => false
            },
            'msg' => $response['res_msg']
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * 미결제건 결제
     */
    public function incompletePayment(Request $request): JsonResponse
    {
        $request->validate([
            'biz_kind' => ['required', Rule::in(SearchBizKind::keys())],
            'no_order' => 'required|string',
            'no_card' => 'required|numeric'
        ]);

        $orderInfo = match ($request->biz_kind) {
            SearchBizKind::PARKING->name => OrderService::getParkingOrderInfo([
                'no_user' => Auth::id(),
                'no_order' => $request->no_order
            ])->first(),
            default => OrderService::getOrderInfo([
                'no_user' => Auth::id(),
                'no_order' => $request->no_order
            ])->first()
        };

        if (!$orderInfo) {
            throw new OwinException(Code::message('P2028'));
        }

        if (!in_array($orderInfo['cd_status'], ['800800', '800810'])) {
            throw new OwinException(Code::message('P2029'));
        }

        $response = match ($request->biz_kind) {
            SearchBizKind::PARKING->name => (new OrderService())->autoParkingPayment($orderInfo, $orderInfo['parkingSite'] ?? $orderInfo['autoParking'], $orderInfo['carInfo'], collect($request->post())),
            default => (new OrderService())->incompletePayment(Auth::user(), $orderInfo, collect($request->post())),
        };

        return response()->json([
            'result' => $response['result'],
            'no_order' => $response['no_order'],
            'message' => $response['msg'],
            'detail_message' => $response['pg_msg']
        ]);
    }
}

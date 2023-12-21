<?php

declare(strict_types=1);

namespace App\Services;


use App\Models\MemberLocationEnterLog;
use App\Models\OrderList;
use App\Utils\Code;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ActionService extends Service
{
    /**
     * @param int $now
     * @param string $article
     * @return array
     *
     * 주유주문관련 시간체크
     */
    public function uptimeCheck(int $now, string $article): array
    {
        return match ($article) {
            'O' => match ($now >= env('DT_OIL_UNORDER_SOON') && $now < env('DT_OIL_UNORDER')) {
                true => [
                    'code' => 301,
                    'message' => sprintf(
                        Code::message('AC0001'),
                        env('DT_OIL_UNORDER_WORD'),
                        env('DT_OIL_UNORDER_WORD')
                    )
                ],
                default => ['code' => 302, 'message' => sprintf(Code::message('AC0002'), env('DT_OIL_UNORDER_WORD'))]
            },
            default => match ($now >= env('DT_OIL_UNORDER') && $now <= env('DT_OIL_CHECK')) {
                true => ['code' => 303, 'message' => sprintf(Code::message('AC0002'), env('DT_OIL_UNORDER_WORD'))],
                default => ['code' => 300, 'message' => null]
            }
        };
    }

    /**
     * @param int $noUser
     * @param array $request
     * @return void
     * @throws Throwable
     *
     * 주유주문관련 현재위치 체크
     */
    public static function locationSave(int $noUser, array $request): void
    {
        MemberLocationEnterLog::where([
            'no_user' => $noUser,
            'no_order' => $request['no_order']
        ])->orderByDesc('no')->limit(1)->get()->where('yn_inside', data_get($request, 'yn_inside'))->whenEmpty(function () use ($noUser, $request) {
            $order = OrderList::with('shop')->where([
                'no_order' => $request['no_order'],
                'no_user' => $noUser
            ])->first();

            (new MemberLocationEnterLog([
                'no_user' => Auth::id(),
                'no_shop' => $order->no_shop,
                'no_order' => $order->no_order,
                'cd_pickup_status' => $order->cd_pickup_status,
                'shop_lat' => $order->shop->at_lat,
                'shop_lng' => $order->shop->at_lng,
                'user_lat' => data_get($request, 'user_lat'),
                'user_lng' => data_get($request, 'user_lng'),
                'yn_inside' => data_get($request, 'yn_inside'),
                'ds_addr' => data_get($request, 'ds_addr')
            ]))->saveOrFail();
        });
    }

    /**
     * @param string $cacheKey
     * @return bool
     *
     * 캐시 삭제
     */
    public function cacheClear(string $cacheKey): bool
    {
        if (Cache::has($cacheKey) === true) {
            return (Cache::forget($cacheKey));
        } else {
            return false;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\OwinException;
use App\Models\MemberShopWashLog;
use App\Models\OrderList;
use App\Models\WashInshop;
use App\Models\WashProduct;
use App\Utils\Code;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WashService extends Service
{

    /**
     * [주문요청] 매장 상품정보 반환
     *
     * @param int $noShop
     * @param string|null $cdCarKind
     *
     * @return Collection
     */
    public static function getWashProductList(
        int $noShop,
        ?string $cdCarKind = null
    ): Collection {
        $where = [
            'no_shop' => $noShop,
            'yn_status' => 'Y'
        ];

        if ($cdCarKind) {
            $where['cd_car_kind'] = $cdCarKind;
        }

        return WashProduct::where($where)->with(['washOptions'])->get()->map(function ($product) {
            $product->cd_car_kind = CodeService::getCode($product->cd_car_kind)->nm_code;
            return $product;
        })->sortBy('no_product');
    }

    /**
     * @param $data
     * @return void
     */
    public static function registMemberShopWashLog($data)
    {
        MemberShopWashLog::create([
            'no_order' => $data['no_order'],
            'no_user' => $data['no_user'],
            'no_shop' => $data['no_shop'],
            'cd_alarm_event_type' => $data['cd_alarm_event_type'],
        ]);
    }

    /**
     * @param $member
     * @param $orderInfo
     * @return void
     * @throws OwinException
     */
    public static function washComplete($member, $orderInfo)
    {
        try {
            DB::beginTransaction();
            ## [3] 미처리 주문일경우  도착완료처리 (2020.09.14 세차완료->도착완료(세차완료 전 단계))
            OrderList::where([
                'no_user' => $member['no_user'],
                'no_order' => $orderInfo['no_order'],
            ])->update([
                'cd_pickup_status' => '602300',
                'dt_pickup_status' => Carbon::now(),
            ]);
            ## [4] 처리완료 로그
            self::registMemberShopWashLog([
                'no_user' => $member['no_user'],
                'no_shop' => $orderInfo['no_shop'],
                'no_order' => $orderInfo['no_order'],
                'cd_alarm_event_type' => '619210'
            ]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('error')->error('[P2500] washComplete', [$e->getMessage()]);
            throw new OwinException(Code::message('P2500'));
        }
    }

    /**
     * @param $noShop
     * @return WashInshop|Model|object|null
     */
    public static function getWashShopInShopInfo($noShop)
    {
        return WashInshop::select([
            'wash_inshop.*',
            'shop.ds_status'
        ])->join('shop', 'wash_inshop.no_shop', '=', 'shop.no_shop')
            ->where([
                'wash_inshop.no_shop_in' => $noShop
            ])->first();
    }

}

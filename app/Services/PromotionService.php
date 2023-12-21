<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GsSaleCard;
use App\Models\MemberDeal;
use App\Models\PromotionDeal;
use App\Models\PromotionOverlap;
use App\Models\PromotionPin;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PromotionService extends Service
{
    public static array $gsTermRequire = ['01', '02', '09', '71', '81'];

    /**
     * @param string $noPin
     * @return Collection
     */
    public function pinInfo(string $noPin): Collection
    {
        return PromotionPin::where('no_pin', $noPin)->whereNull('no_user')->whereNotNull('no_deal')->get();
    }

    /**
     * @param array $parameter
     * @return Collection
     */
    public static function promotionOverlap(array $parameter): Collection
    {
        return PromotionOverlap::where($parameter)->get();
    }

    /**
     * @param array $noUsers
     * @param int $noDeal
     * @param array $noDeals
     * @return Collection
     */
    public static function memberDealbyNoDeal(array $noUsers, int $noDeal, array $noDeals): Collection
    {
        return MemberDeal::whereIn('no_user', $noUsers)
            ->where(function ($query) use ($noDeal, $noDeals) {
                $query->where('no_deal', $noDeal);
                if (empty($noDeals) === false) {
                    $query->orWhereNotIn('no_deal', $noDeals);
                }
            })->get();
    }

    public static function getMemberDealCount(array $parameter): int
    {
        return (new MemberDeal())->where($parameter)->count();
    }

    /**
     * @param array $parameter
     * @return Collection
     */
    public function promotionPin(array $parameter): Collection
    {
        return PromotionPin::where($parameter)->get();
    }

    /**
     * @param array|null $parameter
     * @param array|null $whereNotNull
     * @return Collection
     */
    public static function promotionDeal(?array $parameter, ?array $whereNotNull = null): Collection
    {
        return PromotionDeal::with([
            'gsCpnEvent', 'retailCouponEvent.retailCouponEventUsepartner', 'couponEvent'
        ])->where(function ($query) use ($parameter) {
            if (empty($parameter) === false) {
                $query->where($parameter);
            }
        })->where(function ($query) use ($whereNotNull) {
            if (empty($whereNotNull) === false) {
                $query->whereNotNull($whereNotNull);
            }
        })->get();
    }

    /**
     * @param array|null $parameter
     * @param array|null $whereNotNull
     * @return PromotionDeal
     */
    public static function promotionDealFirst(?array $parameter, ?array $whereNotNull = null): PromotionDeal
    {
        return self::promotionDeal($parameter, $whereNotNull)->first();
    }

    /**
     * @param array $parameter
     * @param array $where
     * @return void
     */
    public static function promotionPinUpdate(array $parameter, array $where): void
    {
        PromotionPin::where($where)->update($parameter);
    }

    public static function maxGsSaleCard(User $user, string $min, string $max, string $lastPointcard): ?string
    {
        $card = GsSaleCard::where('no_user', $user->no_user)->whereBetween('id_pointcard', [$min, $max])->first();

        return match ($card?->exists) {
            true => (function () use ($card, $user) {
                $card->update([
                    'yn_used' => 'Y'
                ]);
                CardService::upsertMemberPointcard([
                    'no_user' => $user->no_user
                ], [
                    'yn_sale_card' => 'Y',
                    'cd_point_cp' => env('GS_CD_POINT_SALE_CP'),
                    'yn_agree01' => 'Y',
                    'yn_agree02' => 'Y',
                    'yn_agree03' => 'Y',
                    'yn_agree04' => 'N',
                    'yn_agree05' => 'Y',
                    'yn_agree06' => 'N',
                    'yn_agree07' => 'N',
                    'yn_delete' => 'N',
                    'id_pointcard' => $card->id_pointcard,
                    'no_deal' => self::getNoDeal($card->id_pointcard)
                ]);

                return null;
            })(),
            default => $lastPointcard
        };
    }

    public static function getNoDeal(string $idPointcard): ?int
    {
        return PromotionDeal::whereBetween(DB::raw($idPointcard), [DB::raw('ds_bandwidth_st'), DB::raw('ds_bandwidth_end')])->select('no_deal')->first()?->no_deal;
    }
}

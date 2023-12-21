<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SearchBizKind;
use App\Enums\SearchBizKindDetail;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PartnerService extends Service
{
    /**
     * @param $noPartner
     * @return Partner|Model|object|null
     */
    public static function get($noPartner): Partner|null
    {
        return Partner::where([
            'no_partner' => $noPartner
        ])->first();
    }

    /**
     * @param string  $bizKind
     * @param Request $collection
     *
     * @return Collection
     */
    public static function gets(string $bizKind, Request $collection): Collection
    {

        $bizKinds = match ($bizKind) {
            SearchBizKind::FNB->name => json_decode(
                SearchBizKind::case('FNB')->value,
                true
            ),
            default => json_decode(SearchBizKind::case($bizKind)->value, true)
        };

        $bizKindDetails = match (isset($collection['biz_kind_detail'])) {
            true => match ($collection['biz_kind_detail']) {
                SearchBizKindDetail::SPC->name => json_decode(
                    SearchBizKindDetail::case('SPC')->value,
                    true
                ),
                default => json_decode(SearchBizKindDetail::case($collection['biz_kind_detail'])->value, true)
            },
            default => null
        };

        $partner = Partner::where('yn_status', 'Y')
            ->whereIn('cd_biz_kind', $bizKinds);

        if ($bizKindDetails) {
            $partner = $partner->whereIn('cd_biz_kind_detail', $bizKindDetails);
        }

        return $partner->get();
    }

    /**
     * bizKind 별 브랜드 리스트 조회(매장이 있는 브랜드만 출력)
     * @param string  $bizKind
     * @param Request $collection
     *
     * @return Collection
     */
    public static function getGroupingPartners(string $bizKind): Collection
    {
        $bizKinds = match ($bizKind) {
            SearchBizKind::FNB->name => json_decode(
                SearchBizKind::case('FNB')->value,
                true
            ),
            default => json_decode(SearchBizKind::case($bizKind)->value, true)
        };

        return collect(
            DB::select(
                "SELECT 
*
,(SELECT COUNT(*)
               FROM    shop
               WHERE   partner.no_partner = shop.no_partner
               AND     ds_status          = 'Y'
               AND     shop.dt_del  IS NULL
       ) as cnt
FROM   partner
WHERE  
    yn_status = 'Y'
AND    cd_biz_kind IN (" . implode(',', $bizKinds) . ")
AND    partner.dt_del IS NULL
HAVING cnt > 0"
            )
        );
    }
}

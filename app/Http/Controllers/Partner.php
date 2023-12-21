<?php

namespace App\Http\Controllers;

use App\Enums\SearchBizKind;
use App\Exceptions\OwinException;
use App\Services\PartnerService;
use App\Utils\Code;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Partner extends Controller
{
    public function getFilters(Request $request, string $bizKind): JsonResponse
    {
        $partners = PartnerService::gets($bizKind, $request)
            ->whenEmpty(function () {
                throw new OwinException(Code::message('P1000'));
            })->map(function ($collect) use ($bizKind) {
                return [
                    'no_partner' => $collect->no_partner,
                    'nm_partner' => $collect->nm_partner,
                    'biz_kind' => $bizKind,
                    'ds_bi' => $collect->ds_bi,
                    'ds_pin' => $collect->ds_pin,
                ];
            });

        return response()->json([
            'result' => true,
            'count' => $partners->count(),
            'rows' => $partners
        ]);
    }

    /**
     * @param string $bizKind
     * @return JsonResponse
     */
    public function getGroupFilters(string $bizKind): JsonResponse
    {
        $partners = PartnerService::getGroupingPartners($bizKind)
            ->whenEmpty(function () {
                throw new OwinException(Code::message('P1000'));
            })->map(function ($collect) use ($bizKind) {
                return [
                    'detail_biz_kind' => SearchBizKind::getDetailBizKind($collect->cd_biz_kind)->name,
                    'biz_kind' => $bizKind,
                    'cd_biz_kind' => $collect->cd_biz_kind,
                    'no_partner' => $collect->no_partner,
                    'nm_partner' => $collect->nm_partner,
                    'ds_bi' => $collect->ds_bi,
                    'ds_pin' => $collect->ds_pin,
                ];
            })->groupBy('detail_biz_kind');

        return response()->json([
            'result' => true,
            'count' => $partners->count(),
            'rows' => $partners
        ]);
    }
}

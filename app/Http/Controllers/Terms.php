<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\BbsTerms;
use App\Models\ParkingSite;
use Illuminate\Http\Request;

class Terms extends Controller
{
    const BIZ_KINDS = [
        '201100' => '커피/식사',
        '201800' => '편의점',
        '201500' => '주차',
        '201600' => '세차'
    ];

    public function show(Request $request, $termsCategory)
    {
        $noVersion = $request->get('no_version');
        $terms = BbsTerms::where('terms_category', $termsCategory)->orderBy('no_version', 'desc')->get()->whenEmpty(function () {
            abort(404);
        });

        $selectedTerms = $terms->when($noVersion, function ($query, $noVersion) {
            return $query->where('no_version', $noVersion);
        })->whenEmpty(function () {
            abort(404);
        })->first();

        $versions = $terms->pluck('dt_reg', 'no_version');

        return view('terms.terms', [
            'terms' => $selectedTerms,
            'versions' => $versions
        ]);
    }

    public function showBizKinds(Request $request)
    {
        return view('terms.bizKinds', ['bizKinds' => self::BIZ_KINDS]);
    }

    public function showShops(Request $request, $bizKind)
    {
        // 주차는 별도의 테이블 존재
        if ($bizKind === '201500') {
            $shops = ParkingSite::where('ds_status', 'Y')->get();
        } else {
            if ($bizKind === '201100') {
                $bizKinds = ['201100', '201200', '201400'];
            } else {
                $bizKinds = [$bizKind];
            }

            $shops = Shop::with('partner')->where('ds_status', 'Y')
                ->whereHas('shopDetail', function ($query) {
                    $query->where('cd_contract_status', '207100');
                })
                ->whereHas('partner', function ($query) use ($bizKinds) {
                    $query->whereIn('cd_biz_kind', $bizKinds);
                })->get()->map(function ($shop) {
                    return [
                        'nm_partner' => $shop->partner->nm_partner,
                        'nm_shop' => $shop->nm_shop
                    ];
                });
        }

        return view('terms.shops', [
            'bizKindName' => self::BIZ_KINDS[$bizKind],
            'shops' => $shops
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EnumYN;
use App\Exceptions\OwinException;
use App\Models\MemberPartnerCoupon;
use App\Models\RetailCouponEventUsepartner;
use App\Queues\Socket\ArkServer;
use App\Services\CardService;
use App\Services\CodeService;
use App\Services\CouponService;
use App\Services\Gs\GsService;
use App\Services\MemberService;
use App\Services\PromotionService;
use App\Utils\Code;
use App\Utils\Common;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class Promotion extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * 오윈 쿠폰등록 (/promotion/owin_coupon_regist)
     */
    public function couponRegist(Request $request): JsonResponse
    {
        $request->validate([
            'no_event' => 'required'
        ]);
        $noPin = substr(preg_replace(['/\s+/', '/\r\n|\r|\n/'], ['', ''], $request->post('no_event')), 0, 200);
        $memberService = new MemberService();
        $couponService = new CouponService();
        $promotionService = new PromotionService();

        if (empty(Auth::user()->ds_ci) === true || Auth::user()->ds_status == EnumYN::N->name) {
            throw new OwinException(Code::message('M1501'));
        }
        if (str_starts_with($request->post('no_event'), 'owin') && strlen($request->post('no_event')) == 9) {
            throw new OwinException(Code::message('P2302'));
        }

        try {
            DB::beginTransaction();
            $pinInfo = $promotionService->pinInfo($noPin)->whenEmpty(function () {
                throw new OwinException(Code::message('P2360'));
            })->first();

            if ($pinInfo->no_deal != env('NO_DEAL_PRESS')) {
                MemberService::getMember([
                    'ds_ci' => Auth::user()->ds_ci
                ])->whenNotEmpty(function ($ciMemberList) use ($pinInfo) {
                    $noUsers = $ciMemberList->pluck('no_user')->all();
                    $noOverlapSeq = PromotionService::promotionOverlap([
                        'no_basis_seq' => $pinInfo->no_deal,
                        'ds_status' => EnumYN::Y->name,
                    ]);

                    PromotionService::memberDealbyNoDeal(
                        $noUsers,
                        $pinInfo->no_deal,
                        $noOverlapSeq->where('ds_type', 'P')->all()
                    )->whenNotEmpty(function () {
                        throw new OwinException(Code::message('P2370'));
                    });

                    MemberService::memberEvent(
                        $noUsers,
                        $noOverlapSeq->where('ds_type', 'E')->all(),
                        env('FNB_EVENT_SEQ')
                    )->whenNotEmpty(function () {
                        throw new OwinException(Code::message('P2371'));
                    });
                });
            }

            $couponService->todayMemberOwinCouponRequest([
                'no_user' => Auth::id(),
                'yn_success' => EnumYN::N->name
            ])->whenNotEmpty(function ($collect) {
                if ($collect->count() > 10) {
                    throw new OwinException(Code::message('P2380'));
                }
            });

            $dealInfo = $promotionService->promotionDealFirst([
                'no_deal' => $pinInfo->no_deal
            ]);

            $dealCnt = PromotionService::getMemberDealCount([
                'no_deal' => $dealInfo->no_deal
            ]);

            if ($dealInfo->at_pin_total > 0 && $dealCnt > $dealInfo->at_pin_total) {
                throw new OwinException(Code::message('P2372'));
            }

            MemberService::memberDeal([
                'no_user' => Auth::id(),
                'no_deal' => $pinInfo->no_deal
            ])->whenNotEmpty(function () {
                throw new OwinException(Code::message('P2370'));
            }, function () use ($noPin, $pinInfo, $dealInfo) {
                if ($dealInfo->yn_single_pin == 'N') {
                    MemberService::memberDeal([
                        'no_pin' => $noPin,
                        'no_deal' => $pinInfo->no_deal
                    ])->whenNotEmpty(function () {
                        throw new OwinException(Code::message('P2340'));
                    });
                } else {
                    MemberService::memberDeal([
                        'no_user' => Auth::id(),
                        'no_pin' => $noPin,
                        'no_deal' => $pinInfo->no_deal
                    ])->whenNotEmpty(function () {
                        throw new OwinException(Code::message('P2340'));
                    });
                }
            });

            if (
                strlen($noPin) >= 8
                && empty($dealInfo->ds_index_char) === false
                && !(
                    strcasecmp($dealInfo->dt_deal_use_end->format('Y-m-d H:i:s'), substr($noPin, 0, 2))
                    && $dealInfo->cd_deal_type == 129100
                )
            ) {
                throw new OwinException(Code::message('P2300'));
            }
            if ($dealInfo->dt_deal_use_end < date('Y-m-d') || $dealInfo->dt_deal_use_st > date('Y-m-d')) {
                throw new OwinException(Code::message('P2300'));
            }

            if ($pinInfo->no_deal != env('NO_DEAL_PRESS') && $dealInfo->yn_single_pin != EnumYN::Y->name) {
                $message = match ($pinInfo->cd_deal_status) {
                    '128100' => null,
                    '128900' => 'P2303',
                    default => match ($pinInfo->no_user) {
                        Auth::id() => match ($pinInfo->cd_deal_status) {
                            '128300' => 'P2326',
                            default => 'P2327',
                        },
                        default => 'P2301'
                    }
                };

                if (is_null($message) === false) {
                    throw new OwinException($message);
                }
            }

            $memberService->memberDealFirstOrCreate([
                'no_pin' => $noPin,
                'no_deal' => $pinInfo->no_deal,
                'no_user' => Auth::id()
            ], [
                'yn_pointcard_issue' => EnumYN::N->name,
                'dt_deal_use_end' => $dealInfo->dt_deal_use_end
            ]);
            $noCoupon = Common::getMakeCouponNo();

            if ($dealInfo->cd_biz_kind == '201300') {
                $partnerCouponCnt = match (empty($dealInfo->gsCpnEvent->no_part_cpn_event)) {
                    false => $couponService->memberPartnerCoupon([
                        'no_user' => Auth::id(),
                        'no_event' => $dealInfo->gsCpnEvent->no_part_cpn_event
                    ])->count(),
                    default => 0
                };
                if ((empty(Auth::user()->ds_ci) === false && $partnerCouponCnt < 1) === false) {
                    throw new OwinException(Code::message('P2329'));
                }

                CouponService::memberPartnerCouponRegist([
                    'ds_cpn_no_internal' => $noCoupon,
                    'ds_cpn_no' => $noCoupon,
                    'no_user' => Auth::id(),
                    'no_partner' => env('GS_NO_PARTNER'),
                    'use_coupon_yn' => EnumYN::Y->name,
                    'ds_cpn_nm' => $dealInfo->gsCpnEvent->ds_cpn_title,
                    'use_disc_type' => '00',
                    'at_disct_money' => $dealInfo->at_disct_price,
                    'at_limit_money' => 0,
                    'cd_cpe_status' => '121100',
                    'cd_mcp_status' => '122100',
                    'no_event' => $dealInfo->no_part_cpn_event,
                    'dt_use_start' => now()->startOfDay(),
                    'dt_use_end' => now()->addDays($dealInfo->gsCpnEvent->at_expire_day - 1)->endOfDay(),
                    'dt_start_from_made' => now()->startOfDay(),
                    'dt_end_from_made' => now()->addDays($dealInfo->gsCpnEvent->at_expire_day - 1)->endOfDay(),
                    'id_admin' => Auth::user()->nm_user,
                    'yn_is_reused' => EnumYN::N->name,
                    'yn_real_pubs' => EnumYN::N->name,
                ]);

                $promotionService->promotionPinUpdate([
                    'no_user' => Auth::id(),
                    'cd_deal_status' => '128200',
                    'ds_cpn_no' => $noCoupon
                ], [
                    'no_pin',
                    $noPin
                ]);
            } elseif ($dealInfo->cd_biz_kind == '201800') {
                if ($dealInfo->retailCouponEvent->exists) {
                    $dealInfo->retailCouponEvent->retailCouponEventUsepartner->map(function (RetailCouponEventUsepartner $usepartner) use($noCoupon) {
                        CouponService::registRetailCouponEventUsepartner([
                            'no_user' => Auth::id(),
                            'no_coupon' => $noCoupon,
                            'cd_cpn_condi_type' => '125100',
                            'ds_target' => $usepartner->no_partner
                        ]);
                    });
                }

                $couponService->memberRetailCouponRegist([
                    'no_user' => Auth::id(),
                    'no_coupon' => $noCoupon,
                    'no_event' => $dealInfo->retail_no_event,
                    'nm_event' => $dealInfo->retailCouponEvent->nm_event,
                    'use_coupon_yn' => EnumYN::Y->name,
                    'cd_mcp_status' => '122100',
                    'at_disct_money' => $dealInfo->retailCouponEvent->at_disct_money,
                    'at_expire_day' => $dealInfo->retailCouponEvent->at_expire_day,
                    'dt_use_start' => now()->startOfDay(),
                    'dt_use_end' => now()->addRealDays($dealInfo->retailCouponEvent->at_expire_day)->endOfDay(),
                    'at_min_price' => $dealInfo->retailCouponEvent->at_min_price,
                    'cd_issue_kind' => '131300',
                    'cd_calculate_main' => $dealInfo->retailCouponEvent->cd_calculate_main,
                    'user_type' => 'C',
                ]);

                $promotionService->promotionPinUpdate([
                    'no_pin' => $noPin
                ], [
                    'no_user' => Auth::id(),
                    'cd_deal_status' => '128200',
                    'ds_cpn_no' => $noCoupon
                ]);

                $couponService->memberRetailCouponRequestRegist([
                    'no_user' => Auth::id(),
                    'no_coupon' => $noCoupon,
                    'no_event' => $dealInfo->retail_no_event,
                    'nm_event' => $dealInfo->retailCouponEvent->nm_event,
                    'use_coupon_yn' => EnumYN::Y->name,
                    'cd_mcp_status' => '122100',
                    'at_disct_money' => $dealInfo->retailCouponEvent->at_disct_money,
                    'at_expire_day' => $dealInfo->retailCouponEvent->at_expire_day,
                    'dt_use_start' => now()->startOfDay(),
                    'dt_use_end' => now()->addRealDays($dealInfo->retailCouponEvent->at_expire_day)->endOfDay(),
                    'at_min_price' => $dealInfo->retailCouponEvent->at_min_price,
                    'cd_issue_kind' => '131300',
                    'cd_calculate_main' => $dealInfo->retailCouponEvent->cd_calculate_main,
                    'user_type' => 'C',
                    'list_usepartner' => $dealInfo->retailCouponEvent->retailCouponEventUsepartner->pluck('no_partner')->implode(
                        ','
                    ),
                    'yn_success' => EnumYN::Y->name,
                ]);
            } elseif ($dealInfo->cd_biz_kind == '201200') {
                $coupon = CouponService::getMakeMemberCoupon(
                    noUser: Auth::id(),
                    noEvent: $dealInfo->couponEvent?->no_event,
                    startDt: $dealInfo->couponEvent->dt_start,
                    endDt: $dealInfo->couponEvent->at_expire_day > 0 ? $dealInfo->couponEvent->dt_start->addRealDays($dealInfo->couponEvent->at_expire_day) : $dealInfo->couponEvent->dt_expire
                );

                if ($dealInfo->yn_single_pin == 'N') {
                    PromotionService::promotionPinUpdate([
                        'no_user' => Auth::id(),
                        'ds_cpn_no' => $coupon->no_coupon,
                        'cd_deal_status' => '128200'
                    ], [
                        'no_pin' => $noPin
                    ]);
                }
            }

            if ($pinInfo->no_deal == env('NO_DEAL_PRESS')) {
                $memberService->memberGroupFirstOrCreate([
                    'ds_phone' => Auth::user()->ds_phone,
                    'id_user' => Auth::user()->id_user,
                    'cd_mem_group' => '112100'
                ], [
                    'no_user' => Auth::id()
                ]);
            }

            $memberService->owinCouponRequest([
                'no_pin' => $noPin,
                'no_user' => Auth::id(),
                'no_deal' => $pinInfo->no_deal,
                'yn_success' => EnumYN::Y->name,
                'reg_msg' => Code::message('P2399'),
                'reg_code' => Code::message('P2399')
            ]);
            DB::commit();

            return response()->json([
                'result' => true
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw new OwinException($e->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * GS 쿠폰 등록
     */
    public function gsCouponRegist(Request $request): JsonResponse
    {
        $request->validate([
            'no_event' => 'required'
        ]);

        try {
            DB::beginTransaction();
            CouponService::memberPartnerCoupon([
                'ds_cpn_no' => $request->no_event
            ])->whenNotEmpty(function () {
                throw new OwinException(Code::message('P2340'));
            });

            CouponService::todayCouponRequest([
                'no_user' => Auth::id(),
                'yn_success' => 'N'
            ])->whenNotEmpty(function ($collect) {
                if ($collect->count() > 2) {
                    throw new OwinException(Code::message('P2380'));
                }
            });

            CouponService::memberPartnerCouponRegist([
                'ds_cpn_no_internal' => $request->post('no_event'),
                'ds_cpn_no' => $request->post('no_event'),
                'no_user' => Auth::id(),
                'no_partner' => env('GS_NO_PARTNER'),
                'use_coupon_yn' => EnumYN::N->name,
                'use_disc_type' => '00',
                'at_disct_money' => 0,
                'at_limit_money' => 0,
                'cd_cpe_status' => '121200',
                'cd_mcp_status' => '122100',
                'yn_real_pubs' => EnumYN::Y->name,
            ]);

            CouponService::registMemberCouponRequestRegist([
                'no_user' => Auth::id(),
                'no_partner' => env('GS_NO_PARTNER'),
                'ds_cpn_no' => $request->post('no_event'),
                'yn_success' => EnumYN::Y->name,
            ]);

            try {
                (new ArkServer(
                    type: 'SOCKET',
                    method: 'oilCoupon',
                    body: $request->no_event,
                    header: 'KS'
                ))->init();
            } catch (Throwable $t) {
                Log::channel('slack')->critical('FCM: ', [$t->getMessage()]);
            }
            DB::commit();

            return response()->json([
                'result' => true
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::channel('error')->error('[P2328] gsCouponRegist error', [$e->getMessage()]);
            throw new OwinException(Code::message('P2328'));
        }
    }

    /**
     * @param string $noEvent
     * @return JsonResponse
     *
     * gs 쿠폰 등록 후 쿠폰 등록 체크 (정상쿠폰 확인 후 사용불가 쿠폰일 경우 삭제)
     */
    public function gsCouponDetail(string $noEvent): JsonResponse
    {
        CouponService::memberPartnerCoupon([
            'ds_cpn_no' => $noEvent,
            'no_user' => Auth::id()
        ])->whenEmpty(function () {
            throw new OwinException(Code::message('P2360'));
        }, function ($coupon) {
            if ($coupon->first()->use_coupon_yn == 'N') {
                CouponService::removeMemberPartnerCoupon($coupon->first());

                $cardList = Code::conf('gs_card_list');
                $message = match (empty(data_get($cardList, $coupon->first()->cd_payment_card))) {
                    false => sprintf(Code::message('C0001'), data_get($cardList, $coupon->first()->cd_payment_card)),
                    default => match ($coupon->first()->ds_result_code) {
                        '99998' => Code::message('C0003'),
                        default => Code::message('C0002')
                    }
                };

                throw new OwinException($message);
            }
        });

        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @param string $noEvent
     * @return JsonResponse
     *
     * 쿠폰 삭제
     */
    public function gsCouponRemove(string $noEvent): JsonResponse
    {
        CouponService::memberPartnerCoupon([
            'ds_cpn_no' => $noEvent,
            'no_user' => Auth::id()
        ])->whenEmpty(function () {
            throw new OwinException(Code::message('P2360'));
        }, function ($coupon) {
            CouponService::removeMemberPartnerCoupon($coupon->first());
        });

        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * gs 포인트카드 등록
     */
    public function pointCardRegist(Request $request): JsonResponse
    {
        $request->validate([
            'agree_result' => ['required', 'array']
        ]);
        if (!empty(array_diff(PromotionService::$gsTermRequire, $request->agree_result))) {
            throw new OwinException(Code::message('SC1080'));
        }
        $cardService = new CardService();
        $memberService = new MemberService();

        $pointCardInfo = $cardService->memberPointCard([
            'no_user' => Auth::id(),
            'cd_point_cp' => env('GS_CD_POINT_SALE_CP')
        ])->whenNotEmpty(function ($collection) {
            if ($collection->first()->yn_delete == EnumYN::N->name) {
                throw new OwinException(Code::message('SC1110'));
            }
        })->first();

        $memberDeal = $memberService->memberDeal([
            'no_user' => Auth::id()
        ])->first();

//        beacon.ds_sn 조건 제거 (beacon 삭제)
        $promotionInfo = PromotionService::promotionDealFirst([
            'no_deal' => '1004'
        ]);

        $pointcards = $cardService->gsSaleCard(bandwidthSt: $promotionInfo->ds_bandwidth_st, bandwidthEnd: $promotionInfo->ds_bandwidth_end);
        $maxPointcard = $pointcards->where('no_user', Auth::id())->max('id_pointcard');

        $cardNumber = match (!empty($maxPointcard)) {
            true => $maxPointcard,
            default => $promotionInfo->nextPointcard()
        };

        if ($cardNumber > $promotionInfo->ds_bandwidth_end) {
            throw new OwinException(Code::message('P2203'));
        }

        $response = (new ArkServer(
            type: 'ARK',
            method: 'card',
            body: ArkServer::makeMemberPacketSale('member', Auth::user(), $promotionInfo->ds_gs_sale_code, $cardNumber, $request->agree_result)
        ))->init();

        if ($response['result_code'] != '00000') {
            throw new OwinException(Code::message('SC1091'));
        }

        $promotionInfo->last_pointcard = $promotionInfo->nextPointcard();
        $promotionInfo->save();

        CardService::upsertGsSalesCard([
            'id_pointcard' => $response['no_card'],
            'no_user' => Auth::id()
        ], [
            'ds_validity' => $response['validity'],
            'ds_card_name' => $response['nm_card']
        ]);

        $pointCard = $cardService->gsSaleCard([
            'no_user' => Auth::id(),
            'id_pointcard' => $response['no_card']
        ])->first();

        $pointResponse = (new ArkServer(
            type: 'ARK',
            method: 'oil',
            body: ArkServer::makeCardInfoPacketSale('card_info', Auth::user(), $response['no_card'])
        ))->init();

        $cardInfo = match ($pointResponse['result_code']) {
            '00000' => [
                    'ds_sale_start' => $pointCard->ds_sale_start ?? env('OWIN_GS_SALE_START'),
                    'ds_sale_end' => $pointCard->ds_sale_end ?? env('OWIN_GS_SALE_END'),
                    'at_limit_one_use' => env('OWIN_GS_SALE_PRICE_ONE'),
                    'at_limit_price' => env('OWIN_GS_SALE_PRICE_MONTH'),
                    'at_limit_total_use' => env('OWIN_GS_SALE_PRICE_TOTAL'),
                    'yn_used' => 'Y'
                ] + $pointResponse,
            default => [
                'ds_validity' => null,
                'ds_card_name' => null
            ]
        };
        CardService::updateGsSalesCard($pointCard, $cardInfo);

        $memberPointCardParameter = match (empty($pointCardInfo)) {
                false => [],
                default => [
                    'yn_sale_card' => 'Y',
                    'cd_point_cp' => env('GS_CD_POINT_SALE_CP'),
                    'yn_agree01' => data_get($request->agree_result, 0, 'N'),
                    'yn_agree02' => data_get($request->agree_result, 1, 'N'),
                    'yn_agree03' => data_get($request->agree_result, 2, 'N'),
                    'yn_agree04' => data_get($request->agree_result, 3, 'N'),
                    'yn_agree05' => data_get($request->agree_result, 4, 'N'),
                    'yn_agree06' => data_get($request->agree_result, 5, 'N'),
                    'yn_agree07' => data_get($request->agree_result, 6, 'N')
                ]
            } + [
                'yn_delete' => 'N',
                'id_pointcard' => $response['no_card']
            ];
        CardService::upsertMemberPointcard([
            'no_user' => Auth::id()
        ], $memberPointCardParameter);

        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @param string $idPointcard
     * @return JsonResponse
     * @throws OwinException
     *
     * 포인트 조회
     */
    public function cardPoint(string $idPointcard): JsonResponse
    {
        $response = (new ArkServer(
            type: 'ARK',
            method: 'point',
            body: ArkServer::makePointPacket('point', Auth::user(), $idPointcard)
        ))->init();

        if ($response['result_code'] != '00000') {
            throw new OwinException(Code::message('SC1100'));
        }

        return response()->json([
            'result' => true,
            'point' => (int)$response['point']
        ]);
    }

    /**
     * @param string $idPointcard
     * @return JsonResponse
     * @throws OwinException
     *
     * 포인트카드 삭제
     */
    public function removePointCard(string $idPointcard): JsonResponse
    {
        Auth::user()->memberPointCard->where('id_pointcard', $idPointcard)->whenNotEmpty(function ($card) use ($idPointcard) {
            if ($card->first()->yn_sale_card == 'Y') {
                CardService::upsertMemberPointcard([
                    'no_user' => Auth::id(),
                    'id_pointcard' => $idPointcard
                ], [
                    'yn_delete' => 'Y'
                ]);
            } else {
                $card->first()->delete();
            }
        }, function () {
            throw new OwinException(Code::message('SC9999'));
        });

        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @return JsonResponse
     *
     * gs 포인트카드 리스트
     */
    public function pointCardList(): JsonResponse
    {
        $pointType = CodeService::getGroupCode('124');

        return response()->json([
            'result' => true,
            'card_list' => Auth::user()->memberPointCard->map(function ($card) use ($pointType) {
                return [
                    'id_pointcard' => $card->id_pointcard,
                    'cd_point_cp' => $card->cd_point_cp,
                    'point_cp' => $pointType->firstWhere('no_code', $card->cd_point_cp)->nm_code
                ];
            })
        ]);
    }

    public function search(string $no): JsonResponse
    {
        $coupon = CouponService::memberPartnerCoupon([
            'no_user' => Auth::id()
        ], [$no])->whenEmpty(function () {
            throw new OwinException(Code::message('P2360'));
        })->first(function (MemberPartnerCoupon $coupon) {
            $cpnNo = match ($coupon->yn_real_pubs) {
                'N' => (function () use ($coupon) {
                    if (empty($coupon->gsCpnEvent?->cdn_cpn_amt)) {
                        throw new OwinException(Code::message('P2360'));
                    }
                    $issue = GsService::issue($coupon->gsCpnEvent->cdn_cpn_amt, "owin" . date('YmdHis') . Auth::id());

                    return data_get($issue, 'couponInfo.cupn_No');
                })(),
                default => $coupon->ds_cpn_no
            };
            $coupon->search = match (!empty($coupon->gsCpnEvent->cdn_cpn_amt)) {
                true => GsService::search($coupon->gsCpnEvent->cdn_cpn_amt, $cpnNo),
                default => (function () use ($cpnNo, $coupon) {
                    (new ArkServer(
                        type: 'SOCKET',
                        method: 'oilCoupon',
                        body: $cpnNo,
                        header: 'KS'
                    ))->init();

                    return [
                        'couponInfo' => [
                            'USE_YN' => ($coupon->ds_result_code == '0000' || empty($coupon->ds_result_code)) ? 'N' : 'Y'
                        ]
                    ];
                })()
            };

            if ($coupon->use_coupon_yn == 'Y' && data_get($coupon->search, 'couponInfo.USE_YN') == 'Y') {
                CouponService::updateMemberPartnerCoupon([
                    'use_coupon_yn' => 'N',
                    'cd_mcp_status' => '122200',
                    'cd_cpe_status' => '121200'
                ], [
                    'no' => $coupon->no,
                    'no_user' => Auth::id()
                ]);
            }

            return $coupon;
        });

        return response()->json([
            'result' => true,
            'is_used' => data_get($coupon->search, 'couponInfo.USE_YN') == 'Y'
        ]);
    }
}

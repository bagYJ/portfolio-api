<?php
declare(strict_types=1);

namespace App\Services;

use App\Enums\BenefitDetailType;
use App\Enums\BenefitKind;
use App\Enums\BenefitType;
use App\Enums\McpStatus;
use App\Enums\Pg;
use App\Enums\SubscriptionAffiliateCode;
use App\Enums\SubscriptionStatus;
use App\Exceptions\OwinException;
use App\Models\GsSaleCard;
use App\Models\MemberCard;
use App\Models\MemberCoupon;
use App\Models\MemberParkingCoupon;
use App\Models\MemberPointcard;
use App\Models\PromotionDeal;
use App\Models\Subscription;
use App\Models\SubscriptionAffiliate;
use App\Models\SubscriptionIssue;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionProduct;
use App\Models\User;
use App\Queues\Socket\ArkServer;
use App\Utils\Code;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Owin\OwinCommonUtil\CodeUtil;
use Owin\OwinCommonUtil\Enums\ServiceCodeEnum;
use Throwable;

class SubscriptionService extends Service
{
    public static array $productHidden = ['dt_reg', 'dt_upt', 'dt_del', 'yn_visible', 'benefit', 'list_image_url', 'ds_sale_code'];

    public static function listIssue(array $parameter): SubscriptionIssue
    {
        return SubscriptionIssue::firstWhere($parameter);
    }


    public static function list(array $parameter): Collection
    {
        return SubscriptionProduct::where($parameter)->orderBy('no')->get()->map(function ($subscription) {
            $subscription->detail_image_url = env('IMAGE_PATH') . $subscription->detail_image_url;

            return $subscription;
        })->makeHidden(self::$productHidden);
    }

    public static function detail(int $no): SubscriptionProduct
    {
        return SubscriptionProduct::where('no', $no)->get()->whenEmpty(function () {
            throw new OwinException(Code::message('SUB000'));
        })->first();
    }

    public static function updateSubscription(Subscription $subscription, array $parameter): void
    {
        $subscription->update($parameter);
    }

    public static function payment(User $user, Request $request): array
    {
        $noOrder = CodeUtil::generateOrderCode(ServiceCodeEnum::OWIN);
        $verify = self::varifySubscriptionOrder($user, $request);
        if ($verify['card']?->exists === false) {
            throw new OwinException(Code::message('P1020'));
        }
        $paymentInfo = self::regist(
            user: $user,
            noOrder: $noOrder,
            amount: $verify['product']->amount,
            verify: $verify,
            endDt: now()->addMonthWithNoOverflow()->startOfDay()->subSecond(),
            idPointcard: $user->memberPointCard?->first()?->id_pointcard
        );

        return [
            'result' => $paymentInfo['res_cd'] == '0000',
            'no_order' => $noOrder,
            'nm_order' => $verify['product']->title,
            'product' => $verify['product']->makeHidden(self::$productHidden),
            'pg_msg' => data_get($paymentInfo, 'res_msg'),
            'msg' => match ($paymentInfo['res_cd']) {
                '0000' => Code::message('P2024'),
                default => Code::message('P2010')
            }
        ];
    }

    public static function refund(SubscriptionPayment $subscriptionPayment, string $nmPg, ?string $reason = null, ?Subscription $subscription = null): array
    {
        $response = match ($subscriptionPayment->amount) {
            0 => [
                'res_cd'  => '0000',
                'res_msg' => Code::message('0000'),
            ],
            default => \App\Utils\Pg::refund([
                'nmPg' => $nmPg,
                'noOrder' => $subscriptionPayment->no_order,
                'dsServerReg' => null,
                'nmOrder' => $subscriptionPayment->product->title,
                'dsResOrderNo' => $subscriptionPayment->tid,
                'price' => $subscriptionPayment->amount,
                'reason' => $reason ?? Code::message('PG9999')
            ])
        };
        if ($response['res_cd'] != '0000') {
            throw new OwinException(Code::message('SUB006'));
        }

//        쿠폰 회수
        if ($subscription?->exists) {
            foreach (BenefitType::benefit() as $benefitType) {
                if (BenefitDetailType::couponUse($subscription->benefit->{$benefitType->name}->type)) {
                    match ($benefitType) {
                        BenefitType::FNB => CouponService::setWithdrawMemberCoupon($subscriptionPayment->no_user, $subscription->benefit->{$benefitType->name}->{BenefitDetailType::COUPON->name}),
                        BenefitType::PARKING => CouponService::setWithdrawMemberParkingCoupon($subscriptionPayment->no_user, $subscription->benefit->{$benefitType->name}->{BenefitDetailType::COUPON->name}),
                        default => null
                    };
                }
            }
        }

        match ($subscriptionPayment->product->benefit->kind) {
            BenefitKind::OIL->name => (function () use ($subscription, $subscriptionPayment) {
                GsSaleCard::where([
                    'no_user' => $subscriptionPayment->no_user,
                    'yn_used' => 'Y'
                ])->update([
                    'yn_used' => 'N'
                ]);
                MemberPointcard::where([
                    'no_user' => $subscriptionPayment->no_user,
                    'yn_delete' => 'N'
                ])->update([
                    'id_pointcard' => '',
                    'yn_delete' => 'Y'
                ]);

                if (empty($subscription?->used_id_pointcard) === false) {
                    GsSaleCard::where([
                        'no_user' => $subscriptionPayment->no_user,
                        'id_pointcard' => $subscription?->used_id_pointcard
                    ])->update([
                        'yn_used' => 'Y'
                    ]);
                    MemberPointcard::where([
                        'no_user' => $subscriptionPayment->no_user,
                        'id_pointcard' => $subscription?->used_id_pointcard
                    ])->update([
                        'yn_delete' => 'N'
                    ]);
                }
            })(),
            default => null
        };

        return $response;
    }

    public static function orderList(array $parameter): Collection
    {
        return Subscription::with(['subscriptionPayment'])->where($parameter)->get();
    }

    public static function orderListBrief(?Collection $subscription): Collection
    {
        return $subscription->map(function (Subscription $subscription) {
            return [
                'no' => $subscription->no,
                'year' => $subscription->start_date->format('Y'),
                'no_order' => $subscription->no_order,
                'subscription_date' => $subscription->start_date->format('m월 d일'),
                'title' => $subscription->subscriptionPayment->product->title,
                'amount' => $subscription->subscriptionPayment->amount,
                'kind' => $subscription->benefit->kind,
                'affiliate_code' => $subscription->affiliate_code,
                'affiliate_code_name' => SubscriptionAffiliateCode::case($subscription->affiliate_code)->value
            ];
        })->values();
    }

    public static function varifySubscriptionOrder(User $user, Request $request): array
    {
//        구독사용여부
        if ($user->useSubscription?->exists) {
            throw new OwinException(Code::message('SUB001'));
        }

        return [
            'product' => self::detail($request->no_subscription),
            'card' => $user->memberCard?->where('no_card', $request?->no_card,)->where('cd_pg', Pg::kcp->value)->first()
        ];
    }

    public static function subscriptionStatus(User $user): SubscriptionStatus
    {
        return $user->subscription->whenEmpty(function () {
            return SubscriptionStatus::NOT_USE;
        }, function () use ($user) {
            return match ($user->useSubscription?->exists) {
                true => SubscriptionStatus::USE,
                default => SubscriptionStatus::USED
            };
        });
    }

    public static function subscriptionInfo(User $user, int $no): array
    {
        $subscription = $user->subscription->where('no', $no)->first();
        return [
            'no' => $subscription?->no,
            'no_order' => $subscription?->no_order,
            'title' => $subscription?->subscriptionPayment->product->title,
            'affiliate_code' => $subscription?->affiliate_code,
            'benefit' => self::subscriptionInfoBenefit($user, $subscription),
            'subscription_date' => $subscription?->start_date->format('Y-m-d'),
            'subscription_end_date' => $subscription?->end_date->format('Y-m-d'),
            'is_changed' => $user->useSubscription?->dt_change !== null,
            'next_no_subsciption_product' => $user->useSubscription?->next_no_subscription_product,
            'next_subscription_date' => match (empty($user->useSubscription?->next_no_subscription_product)) {
                false => $user->useSubscription?->end_date->addDays()->format('Y-m-d'),
                default => null
            },
            'amount' => $subscription?->subscriptionPayment->amount,
            'no_card' => $subscription?->subscriptionPayment->card?->no_card,
            'no_card_user' => $subscription?->subscriptionPayment->card?->no_card_user,
            'card_corp' => CodeService::getCode($subscription?->subscriptionPayment->card?->cd_card_corp)->nm_code ?? ''
        ];
    }

    public static function subscriptionInfoBenefit(User $user, Subscription $subscription): array
    {
        $fnbInfo = $subscription->benefit->{BenefitType::FNB->name};
        $washInfo = $subscription->benefit->{BenefitType::WASH->name};
        $parkingInfo = $subscription->benefit->{BenefitType::PARKING->name};
        $sendInfo = $subscription->benefit->{BenefitType::SEND->name};

        return [
            [
                'benefit_type' => BenefitKind::OIL->name,
                'type' => BenefitDetailType::SALE->name,
                BenefitDetailType::SALE->name => self::getOilSaleInfo(
                    user: $user,
                    title: collect($subscription->subscriptionPayment->product->benefit_text)->firstWhere('type', BenefitKind::OIL->name)->title,
                    benefit: collect($subscription->subscriptionPayment->product->benefit_text)->firstWhere('type', BenefitKind::OIL->name)->content,
                    startDt: $subscription->start_date,
                    endDt: $subscription->end_date
                ),
                BenefitDetailType::COUPON->name => null
            ],
            [
                'benefit_type' => BenefitType::WASH->name,
                'type' => $washInfo->type,
                BenefitDetailType::SALE->name => BenefitDetailType::saleUse($washInfo->type) ? self::getWashSaleInfo(
                    user: $user,
                    benefit: collect($subscription->subscriptionPayment->product->benefit_text)->firstWhere('type', BenefitType::WASH->name)->content,
                    startDt: $subscription->start_date,
                    endDt: $subscription->end_date
                ) : null,
                BenefitDetailType::COUPON->name => null
            ],
            [
                'benefit_type' => BenefitType::FNB->name,
                'type' => $fnbInfo->type,
                BenefitDetailType::SALE->name => BenefitDetailType::saleUse($fnbInfo->type) ? self::getFnbSaleInfo(
                    user: $user,
                    benefit: collect($subscription->subscriptionPayment->product->benefit_text)->firstWhere('type', BenefitType::FNB->name)->content,
                    startDt: $subscription->start_date,
                    endDt: $subscription->end_date
                ) : null,
                BenefitDetailType::COUPON->name => BenefitDetailType::couponUse($fnbInfo->type) ? self::getFnbCouponInfo($user, $fnbInfo?->{BenefitDetailType::COUPON->name}) : null
            ],
            [
                'benefit_type' => BenefitType::PARKING->name,
                'type' => $parkingInfo->type,
                BenefitDetailType::SALE->name => null,
                BenefitDetailType::COUPON->name => BenefitDetailType::couponUse($parkingInfo->type) ? self::getParkingCouponInfo(
                    user: $user,
                    couponNos: $parkingInfo->{BenefitDetailType::COUPON->name},
                    benefit: collect($subscription->subscriptionPayment->product->benefit_text)->firstWhere('type', BenefitType::PARKING->name)->content
                ) : null
            ],
            [
                'benefit_type' => BenefitType::SEND->name,
                'type' => $sendInfo->type,
                BenefitDetailType::SALE->name => BenefitDetailType::saleUse($sendInfo->type) ? self::getSendSaleInfo(
                    user: $user,
                    benefit: collect($subscription->subscriptionPayment->product->benefit_text)->firstWhere('type', BenefitType::SEND->name)->content,
                    startDt: $subscription->start_date,
                    endDt: $subscription->end_date
                ) : null,
                BenefitDetailType::COUPON->name => null
            ]
        ];
    }

    public static function getSendSaleInfo(User $user, string $benefit, Carbon $startDt, Carbon $endDt): array
    {
        $orderList = OrderService::getOrderListJoinPartner([
            ['no_user', '=', $user->no_user],
            ['order_list.dt_reg', '>=', $startDt],
            ['order_list.dt_reg', '<=', $endDt],
            ['order_list.cd_order_status', '=', '601200']
        ], [
            'partner.cd_biz_kind' => ['201100', '201200']
        ]);

        return [
            'name' => sprintf('%s %s', BenefitType::SEND->value, BenefitDetailType::SALE->value),
            'benefit' => $benefit,
            'count' => $orderList->where('at_send_sub_disct', '>', 0)->count(),
            'amount' => $orderList->pluck('at_send_sub_disct')->sum(),
            'is_used' => $orderList->where('at_send_sub_disct', '>', 0)->count() > 0
        ];
    }

    public static function getFnbCouponInfo(User $user, array $couponNos): array
    {
        return $user->memberCoupon->whereIn('no', $couponNos)->map(function (MemberCoupon $coupon) {
            return [
                'no' => $coupon->no,
                'no_coupon' => $coupon->no_coupon,
                'no_event' => $coupon->no_event,
                'nm_event' => $coupon->couponEvent->nm_event,
                'at_cpn_disct' => $coupon->no_order ? $coupon->orderList->at_cpn_disct : 0,
                'at_discount' => $coupon->couponEvent->at_discount,
                'cd_mcp_status' => $coupon->cd_mcp_status,
                'mcp_status' => McpStatus::text($coupon->cd_mcp_status)
            ];
        })->groupBy('at_discount')->map(function (Collection $coupon, $key) {
            return [
                'name' => sprintf('%s %s', BenefitType::FNB->value, BenefitDetailType::COUPON->value),
                'benefit' => sprintf('%s%s', number_format($key), Code::message('126100')),
                'count' => $coupon->where('cd_mcp_status', McpStatus::USE->value)->count(),
                'amount' => $coupon->where('cd_mcp_status', McpStatus::USE->value)->pluck('at_cpn_disct')->sum(),
                'is_used' => $coupon->count() == $coupon->where('cd_mcp_status', McpStatus::USE->value)->count()
            ];
        })->values()->toArray();
    }

    public static function getFnbSaleInfo(User $user, string $benefit, Carbon $startDt, Carbon $endDt): array
    {
        $orderList = OrderService::getOrderListJoinPartner([
            ['no_user', '=', $user->no_user],
            ['order_list.dt_reg', '>=', $startDt],
            ['order_list.dt_reg', '<=', $endDt],
            ['order_list.cd_order_status', '=', '601200']
        ], [
            'partner.cd_biz_kind' => ['201100', '201200']
        ]);

        return [
            'name' => sprintf('%s %s', BenefitType::FNB->value, BenefitDetailType::SALE->value),
            'benefit' => $benefit,
            'count' => $orderList->where('at_disct', '>', 0)->count(),
            'amount' => $orderList->pluck('at_disct')->sum(),
            'is_used' => $orderList->where('at_disct', '>', 0)->count() > 0
        ];
    }

    public static function getOilSaleInfo(User $user, string $title, string $benefit, Carbon $startDt, Carbon $endDt): array
    {
        $orderList = OrderService::getOrderListJoinPartner([
            ['no_user', '=', $user->no_user],
            ['order_list.dt_reg', '>=', $startDt],
            ['order_list.dt_reg', '<=', $endDt],
            ['order_list.cd_order_status', '=', '601200']
        ], [
            'partner.cd_biz_kind' => ['201300']
        ]);

        return [
            'name' => $title,
            'benefit' => $benefit,
            'count' => $orderList->where('at_point_disct', '>', 0)->count(),
            'amount' => $orderList->pluck('at_point_disct')->sum(),
            'is_used' => $orderList->where('at_point_disct', '>', 0)->count() > 0
        ];
    }

    public static function getParkingCouponInfo(User $user, array $couponNos, string $benefit): array
    {
        return $user->memberParkingCoupon->whereIn('no', $couponNos)->map(function (MemberParkingCoupon $coupon) {
            return [
                'no' => $coupon->no,
                'no_coupon' => $coupon->no_coupon,
                'no_event' => $coupon->no_event,
                'nm_event' => $coupon->nm_event,
                'at_cpn_disct' => $coupon->no_order ? $coupon->orderList->at_cpn_disct : 0,
                'cd_mcp_status' => $coupon->cd_mcp_status,
                'mcp_status' => McpStatus::text($coupon->cd_mcp_status)

            ];
        })->groupBy('no_event')->map(function (Collection $coupon) use ($benefit) {
            return [
                'name' => sprintf('%s %s', BenefitType::PARKING->value, BenefitDetailType::COUPON->value),
                'benefit' => $benefit,
                'count' => $coupon->where('cd_mcp_status', McpStatus::USE->value)->count(),
                'amount' => $coupon->where('cd_mcp_status', McpStatus::USE->value)->pluck('at_cpn_disct')->sum(),
                'is_used' => $coupon->count() == $coupon->where('cd_mcp_status', McpStatus::USE->value)->count()
            ];
        })->values()->toArray();
    }

    public static function getWashSaleInfo(User $user, string $benefit, Carbon $startDt, Carbon $endDt): array
    {
        $orderList = OrderService::getOrderListJoinPartner([
            ['no_user', '=', $user->no_user],
            ['order_list.dt_reg', '>=', $startDt],
            ['order_list.dt_reg', '<=', $endDt],
            ['order_list.cd_order_status', '=', '601200']
        ], [
            'partner.cd_biz_kind' => ['201600']
        ]);

        return [
            'name' => sprintf('%s %s', BenefitType::WASH->value, BenefitDetailType::SALE->value),
            'benefit' => $benefit,
            'count' => $orderList->where('at_disct', '>', 0)->count(),
            'amount' => $orderList->pluck('at_disct')->sum(),
            'is_used' => $orderList->where('at_disct', '>', 0)->count() > 0
        ];
    }

    public static function registCoupon(User $user, Request $request): void
    {
        Subscription::where([
            'expression_no' => $request->expression_no
        ])->get()->whenNotEmpty(function () {
            throw new OwinException(Code::message('SUB011'));
        });

        $issue = self::getSubscriptionIssue([
            'expression_no' => $request->expression_no,
            'yn_use' => 'N'
        ])->whenEmpty(function () {
            throw new OwinException(Code::message('SUB009'));
        }, function ($issue) {
            if ($issue->first()->subscriptionAffiliate->exists === false) {
                throw new OwinException(Code::message('SUB009'));
            }
        })->first();

        $noOrder = CodeUtil::generateOrderCode(ServiceCodeEnum::OWIN);
        $verify = self::varifySubscriptionOrder($user, $request->merge([
            'no_subscription' => $issue->no_subscription_product,
        ]));

//        $predStatus = \App\Utils\Subscription::prdtStatus($request->expression_no);
//        if ($predStatus->resultCode != '0000') {
//            throw new OwinException($predStatus->resultMsg);
//        }

        $paymentInfo = self::regist(
            user: $user,
            noOrder: $noOrder,
            amount: 0,
            verify: $verify,
            endDt: now()->addRealDays(($issue->subscriptionAffiliate->subscription_date - 1))->endOfDay(),
            issue: $issue,
            idPointcard: $user->memberPointCard?->first()?->id_pointcard
        );
        if ($paymentInfo['res_cd'] != '0000') {
            throw new OwinException(Code::message('SUB010'));
        }

        $issue->update([
            'yn_use' => 'Y'
        ]);
    }

    private static function regist(User $user, string $noOrder, int $amount, array $verify, Carbon $endDt, ?SubscriptionIssue $issue = null, ?string $idPointcard = null): array
    {
//        $pg = (new PgService(Pg::subscription_kcp->name))->setPg();
        $paymentInfo = match ($amount) {
            0 => [
                'res_cd' => '0000',
                'at_price_pg' => $amount
            ],
            default => \App\Utils\Pg::payment([
                'nmPg' => Pg::subscription_kcp->name,
                'noOrder' => $noOrder,
                'noShop' => null,
                'noUser' => $user->no_user,
                'nmBuyer' => $user->nm_user,
                'email' => $user->id_user,
                'phone' => $user->ds_phone,
                'price' => $verify['product']->amount,
                'atCupDeposit' => 0,
                'billkey' => $verify['card']->ds_billkey,
                'nmOrder' => $verify['product']->title
            ])
        };
        $subscriptionPayment = (new SubscriptionPayment([
            'no_order' => $noOrder,
            'no_user' => $user->no_user,
            'tid' => data_get($paymentInfo, 'ds_res_order_no'),
            'amount' => data_get($paymentInfo, 'at_price_pg') ?? 0,
            'product' => $verify['product'],
            'card' => $verify['card'],
            'ds_req_param' => data_get($paymentInfo, 'ds_req_param'),
            'ds_res_param' => data_get($paymentInfo, 'ds_res_param')
        ]));

        try {
            $subscriptionPayment->saveOrFail();
            $promotion = PromotionService::promotionDealFirst([
                'ds_gs_sale_code' => $verify['product']->ds_sale_code
            ]);
            $promotion->last_pointcard = $promotion->nextPointcard();
            $promotion->save();

            DB::beginTransaction();
            if ($paymentInfo['res_cd'] == '0000') {
                $startDt = now()->startOfDay();
                $benefitDetail = $verify['product']->benefit->type;
                $fnbCoupon = match (BenefitDetailType::couponUse($benefitDetail->{BenefitType::FNB->name}->type)) {
                    true => collect($benefitDetail->{BenefitType::FNB->name}->{BenefitDetailType::COUPON->name})->map(function ($coupon) use($user, $startDt, $endDt) {
                        $couponNo = [];
                        for ($i = 0; $i < $coupon->count; $i++) {
                            $couponNo[] = CouponService::getMakeMemberCoupon($user->no_user, $coupon->no_event, $startDt, $endDt)->no;
                        }

                        return $couponNo;
                    })->flatten()->toArray(),
                    default => []
                };
                $parkingCoupon = match (BenefitDetailType::couponUse($benefitDetail->{BenefitType::PARKING->name}->type)) {
                    true => collect($benefitDetail->{BenefitType::PARKING->name}->{BenefitDetailType::COUPON->name})->map(function ($coupon) use($user, $startDt, $endDt) {
                        $couponNo = [];
                        for ($i = 0; $i < $coupon->count; $i++) {
                            $couponNo[] = CouponService::getMakeMemberParkingCoupon($user->no_user, $coupon->no_event, $startDt, $endDt);
                        }

                        return $couponNo;
                    })->flatten()->toArray(),
                    default => []
                };

                $subscription = (new Subscription([
                    'no_order' => $noOrder,
                    'no_subscription_product' => $verify['product']->no,
                    'no_subscription_payment' => $subscriptionPayment->no,
                    'no_subscription_issue' => $issue?->no,
                    'expression_no' => $issue?->expression_no,
                    'no_user' => $user->no_user,
                    'affiliate_code' => $issue?->subscriptionAffiliate->affiliate_code ?? SubscriptionAffiliateCode::OWIN->name,
                    'benefit' => [
                        'kind' => $verify['product']->benefit->kind,
                        'unit' => $verify['product']->benefit->unit,
                        'max' => $verify['product']->benefit->max,
                        BenefitType::FNB->name => [
                            'type' => $benefitDetail->{BenefitType::FNB->name}->type,
                            BenefitDetailType::COUPON->name => $fnbCoupon,
                            BenefitDetailType::SALE->name => $benefitDetail->{BenefitType::FNB->name}->{BenefitDetailType::SALE->name}
                        ],
                        BenefitType::PARKING->name => [
                            'type' => $benefitDetail->{BenefitType::PARKING->name}->type,
                            BenefitDetailType::COUPON->name => $parkingCoupon,
                            BenefitDetailType::SALE->name => $benefitDetail->{BenefitType::PARKING->name}->{BenefitDetailType::SALE->name}
                        ],
                        BenefitType::WASH->name => [
                            'type' => $benefitDetail->{BenefitType::WASH->name}->type,
                            BenefitDetailType::COUPON->name => [],
                            BenefitDetailType::SALE->name => $benefitDetail->{BenefitType::WASH->name}->{BenefitDetailType::SALE->name}
                        ],
                        BenefitType::SEND->name => [
                            'type' => $benefitDetail->{BenefitType::SEND->name}->type,
                            BenefitDetailType::COUPON->name => [],
                            BenefitDetailType::SALE->name => $benefitDetail->{BenefitType::SEND->name}->{BenefitDetailType::SALE->name}
                        ]
                    ],
                    'start_date' => $startDt,
                    'end_date' => $endDt,
                    'used_id_pointcard' => $idPointcard,
                    'next_no_subscription_product' => !$issue?->exists ? $verify['product']->no : null,
                ]));
                $subscription->saveOrFail();

                match ($verify['product']->benefit->kind) {
                    BenefitKind::OIL->name => (function () use ($user, $verify, $promotion) {
                        self::registOilPointCard($user, $verify['product'], $promotion);
                    })(),
                    default => null
                };
            }
            DB::commit();
        } catch (Throwable $t)  {
            DB::rollBack();
            Log::channel('error')->critical($t->getMessage(), [$t->getFile(), $t->getLine(), $t->getTraceAsString()]);
            Log::channel('slack')->critical(env('APP_ENV'), [
                'exception' => $t::class,
                'message' => $t->getMessage(),
                'time' => now()
            ]);

            self::refund(subscriptionPayment: $subscriptionPayment, nmPg: Pg::subscription_kcp->name);
            return [
                'result' => false,
                'res_cd' => '9999',
                'no_order' => $noOrder,
                'nm_order' => $verify['product']->title,
                'pg_msg' => $t->getMessage(),
                'msg' => Code::message('P2010')
            ];
        }

        return $paymentInfo;
    }

    public static function paymentChange(MemberCard $card, Subscription $subscription): bool
    {
        return $subscription->subscriptionPayment->update([
            'card' => $card
        ]);
    }

    public static function getSubscriptionIssue(array $parameter): Collection
    {
        return SubscriptionIssue::where($parameter)->get();
    }

    public static function registOilPointCard(User $user, SubscriptionProduct $product, PromotionDeal $promotion): void
    {
        $code = Code::conf(sprintf('subscription.OIL.%d', $product->benefit->unit));
        $user->memberPointCard->map(function (MemberPointcard $card) {
            $card->update([
                'yn_delete' => 'Y'
            ]);
        });

        $idPointCard = PromotionService::maxGsSaleCard($user, $promotion->ds_bandwidth_st, $promotion->ds_bandwidth_end, $promotion->last_pointcard);
        if (empty($idPointCard) === false) {
            $response = (new ArkServer(
                type: 'ARK',
                method: 'card',
                body: ArkServer::makeMemberPacketSale('member', $user, $code, $idPointCard, ['01', '02', '09', '71', '81'])
            ))->init();

            if ($response['result_code'] != '00000') {
                throw new OwinException(Code::message('SC1091'));
            }

            CardService::upsertGsSalesCard([
                'id_pointcard' => $response['no_card'],
                'no_user' => $user->no_user
            ], [
                'ds_validity' => $response['validity'],
                'ds_card_name' => $response['nm_card']
            ]);

            $pointCard = CardService::gsSaleCard([
                'no_user' => $user->no_user,
                'id_pointcard' => $response['no_card']
            ])->first();

            $pointResponse = (new ArkServer(
                type: 'ARK',
                method: 'oil',
                body: ArkServer::makeCardInfoPacketSale('card_info', $user, $response['no_card'])
            ))->init();

            $cardInfo = match ($pointResponse['result_code']) {
                '00000' => [
                        'yn_used' => 'Y'
                    ] + $pointResponse,
                default => [
                    'ds_validity' => null,
                    'ds_card_name' => null
                ]
            };
            CardService::updateGsSalesCard($pointCard, $cardInfo);

            $memberPointCardParameter = [
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
                'id_pointcard' => $response['no_card'],
                'no_deal' => PromotionService::getNoDeal($response['no_card'])
            ];
            CardService::upsertMemberPointcard([
                'no_user' => $user->no_user
            ], $memberPointCardParameter);
        }
    }

    public static function batch(array $nos): void
    {
        Subscription::with(['user', 'subscriptionPayment'])->whereIn('no',  $nos)->get()->map(function (Subscription $subscription) {
            try {
                if ($subscription->user->ds_status == 'Y' && $subscription->yn_cancel == 'N' && empty($subscription->next_no_subscription_product) === false && empty($subscription->dt_cancel) === true) {
                    $noOrder = CodeUtil::generateOrderCode(ServiceCodeEnum::OWIN);
                    $verify = self::varifySubscriptionOrder($subscription->user, (new Request())->merge([
                        'no_subscription' => $subscription->next_no_subscription_product,
                        'no_card' => $subscription->subscriptionPayment->card->no_card,
                    ]));
                    if ($verify['card']?->exists) {
                        self::regist(
                            user: $subscription->user,
                            noOrder: $noOrder,
                            amount: $verify['product']->amount,
                            verify: $verify,
                            endDt: match ($subscription->affiliate_code) {
                                SubscriptionAffiliateCode::HYUNDAI->name => now()->addRealDays($subscription->subscriptionAffiliate->subscription_date)->subDay()->endOfDay(),
                                default => now()->addMonthWithNoOverflow()->startOfDay()->subSecond()
                            },
                            idPointcard: $subscription->used_id_pointcard
                        );
                    }
                }
            } catch (Throwable $t) {
                Log::channel('slack')->critical(Code::message('SUB010'), [$t->getMessage()]);
            }
        });
    }

    public static function updateSubscriptionIssue(SubscriptionIssue $issue, array $parameter): void
    {
        $issue->update($parameter);
    }

    public static function getSubscrtipionAffiliates()
    {
        return SubscriptionAffiliate::get();
    }
}

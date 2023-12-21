<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Pg;
use App\Enums\SubscriptionAffiliateCode;
use App\Exceptions\OwinException;
use App\Models\SubscriptionAffiliate;
use App\Services\MemberService;
use App\Services\SubscriptionService;
use App\Utils\Code;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use stdClass;
use Throwable;

class Subscription extends Controller
{
    public function list(): JsonResponse
    {
        return response()->json([
            'result' => true,
            'list' => SubscriptionService::list([
                'yn_visible' => 'Y'
            ])
        ]);
    }

    public function detail(int $no): JsonResponse
    {
        return response()->json([
            'result' => true,
            ...SubscriptionService::detail($no)->makeHidden(SubscriptionService::$productHidden)->toArray()
        ]);
    }

    public function payment(Request $request): JsonResponse
    {
        $request->validate([
            'no_subscription' => 'required|integer',
            'no_card' => 'required|integer',
//            'agree1' => 'required|array',
//            'agree2' => 'required|array',
//            'agree1.*' => 'required|in:Y',
//            'agree2.*' => 'required|in:Y'
        ]);

        return response()->json(
            SubscriptionService::payment(Auth::user(), $request)
        );
    }

    public function refund(): JsonResponse
    {
        $subscription = Auth::user()->useSubscription;
        return match ($subscription?->exists) {
            true => (function () use ($subscription) {
                $benefit = SubscriptionService::subscriptionInfoBenefit(Auth::user(), $subscription);

                $message = match (array_sum(data_get($benefit, '*.SALE.count')) > 0 || array_sum(data_get($benefit, '*.COUPON.*.count')) > 0) {
                    true => (function () use ($subscription) {
                        SubscriptionService::updateSubscription($subscription, [
                            'next_no_subscription_product' => null,
                            'dt_cancel' => now()
                        ]);

                        return sprintf(Code::message('SUB005'), $subscription->end_date->addDays(1)->format('Y년 m월 d일'));
                    })(),
                    default => (function () use ($subscription) {
                        try {
                            DB::beginTransaction();
                            SubscriptionService::refund(
                                subscriptionPayment: $subscription->subscriptionPayment,
                                nmPg: Pg::subscription_kcp->name,
                                subscription: $subscription
                            );
                            SubscriptionService::updateSubscription($subscription, [
                                'next_no_subscription_product' => null,
                                'yn_cancel' => 'Y',
                                'dt_cancel' => now()
                            ]);
                            DB::commit();

                            return Code::message('SUB004');
                        } catch (Throwable $t) {
                            DB::rollBack();
                            Log::channel('error')->critical($t->getMessage(), [$t->getFile(), $t->getLine(), $t->getTraceAsString()]);

                            throw new OwinException(Code::message('SUB006'));
                        }
                    })()
                };

                return response()->json([
                    'result' => true,
                    'message' => $message
                ]);
            })(),
            default => throw new OwinException(Code::message('SUB003'))
        };
    }

    public function orderListBrief(): JsonResponse
    {
        return response()->json([
            'result' => true,
            'list' => SubscriptionService::orderListBrief(Auth::user()?->subscription)
        ]);
    }

    public function orderDetail(int $no): JsonResponse
    {
        return response()->json([
            'result' => true,
            'status' => SubscriptionService::subscriptionStatus(Auth::user())->name,
            'subscription' => SubscriptionService::subscriptionInfo(Auth::user(), $no)
        ]);
    }

    public function me(): JsonResponse
    {
        return match (Auth::user()->useSubscription?->exists) {
            true => $this->orderDetail(Auth::user()->useSubscription->no),
            default => response()->json([
                'result' => true,
                'status' => SubscriptionService::subscriptionStatus(Auth::user())->name,
                'subscription' => new stdClass()
            ])
        };
    }

    public function change(Request $request): JsonResponse
    {
        $request->validate([
            'no_subscription' => 'required|integer'
        ]);
        SubscriptionService::detail((int)$request->no_subscription);

        return match (Auth::user()->useSubscription?->exists) {
            true => (function () use ($request) {
                if (Auth::user()->useSubscription->next_no_subscription_product == $request->no_subscription) {
                    throw new OwinException(Code::message('SUB007'));
                }
                if (empty(Auth::user()->useSubscription->dt_change) === false) {
                    throw new OwinException(Code::message('SUB008'));
                }

                SubscriptionService::updateSubscription(Auth::user()->useSubscription, [
                    'next_no_subscription_product' => $request->no_subscription,
                    'dt_change' => now()
                ]);

                return response()->json([
                    'result' => true
                ]);
            })(),
            default => throw new OwinException(Code::message('SUB003'))
        };
    }

    public function affiliate(): JsonResponse
    {
        return response()->json([
            'result' => true,
            'list' => SubscriptionService::getSubscrtipionAffiliates()->map(function (SubscriptionAffiliate $affiliate
            ) {
                return [
                    'code' => $affiliate->affiliate_code,
                    'name' => $affiliate->nm_company
                ];
            })
        ]);
    }

    public function registCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'affiliate_code' => ['required', Rule::in(SubscriptionAffiliateCode::keys())],
            'expression_no' => 'required|size:12'
        ]);

        SubscriptionService::registCoupon(
            user: Auth::user(),
            request: $request
        );

        return response()->json([
            'result' => true
        ]);
    }

    public function registCouponAdmin(Request $request): JsonResponse
    {
        $request->validate([
            'no_user' => 'required|integer'
        ]);

        Auth::login(MemberService::getMember([
            'no_user' => $request->no_user
        ])->first());

        return $this->registCoupon($request);
    }

    public function paymentChange(Request $request): JsonResponse
    {
        $request->validate([
            'no_card' => 'required|integer'
        ]);

        $result = match (Auth::user()->useSubscription?->exists) {
            true => (function () use ($request) {
                return Auth::user()->memberCard->where('no_card', $request->no_card)->whenEmpty(function () {
                    throw new OwinException(Code::message('P1020'));
                }, function (Collection $card) {
                    return SubscriptionService::paymentChange($card->firstWhere('cd_pg', Pg::kcp->value), Auth::user()->useSubscription);
                });
            })(),
            default => false
        };

        return response()->json([
            'result' => $result
        ]);
    }

    public function batch(Request $request): JsonResponse
    {
        $request->validate([
            'nos' => 'required'
        ]);
        SubscriptionService::batch(@explode(',', $request->nos));

        return response()->json([
            'result' => true
        ]);
    }
}

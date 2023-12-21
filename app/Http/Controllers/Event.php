<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OwinException;
use App\Models\BbsEvent;
use App\Models\Member;
use App\Models\MemberCoupon;
use App\Services\CouponService;
use App\Services\MemberService;
use App\Services\SubscriptionService;
use App\Utils\Code;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Event extends Controller
{
    public function fnbEvent($noEvent,Request $request)
    {
        $data['no_user'] = $request->no_user;
        return view('event_page_'.$noEvent, $data);
    }

    /*
     * FNB 이벤트 쿠폰을 발급 받는다
     * */
    public function getFnbMemberCoupon(Request $request): JsonResponse
    {
        // 쿠폰발급 URL ENV에 설정 되어 있음
        $headers[] = 'Content-Type: application/json;charset=UTF-8';
        $headers[] = 'Accept: application/json';

        $param['no_user'] = $request->no_user;
        $param['event_version'] = $request->event_version;

        $params = [
            'form_params' => $param,
            'headers' => $headers
        ];
        $response = (new Client())->post(Code::conf('owin_coupon_url').'/event/get_fnb_member_coupon', $params);
        $body = $response->getBody()->getContents();
        $result_data = json_decode($body, true);

        return response()->json($result_data);
    }

    /*
     * 사용 하지 않은 구독 쿠폰을 검색한다
     * */
    public function getSubscriptionMemberCoupon(Request $request): JsonResponse
    {
        $whereData['yn_use'] = 'N';
        $whereData['no_subscription_product'] = (Int)$request->no_subscription_product;
        $whereData['no_subscription_affiliate'] = (Int)$request->no_subscription_affiliate;

        $getSubscriptionMemberCoupon = SubscriptionService::listIssue($whereData);
        if(!$getSubscriptionMemberCoupon?->exists) {
            throw new OwinException(Code::message('SUB013'));
        }

        $request->merge([
            'expression_no' => $getSubscriptionMemberCoupon->expression_no
        ]);

        Auth::login(MemberService::getMember([
            'no_user' => $request->no_user
        ])->first());

        return (new Subscription($request))->registCoupon($request);
    }

    /**
     * 이벤트 쿠폰 발급
     * - owin-api-sales 의 get_fnb_coupon_event 의 마이그레이션 버전
     * - 웹페이지 내에서 호출한다
     * - todo: 인증 방식의 수정이 필요하다
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     */
    public function issueFnbMemberCoupon(Request $request): JsonResponse
    {
        $request->validate([
            'no_user' => 'required',
            'no_bbs_event' => 'required', // bbs_event 의 no
        ]);

        $member = Member::find($request->no_user);
        if (!$member) {
            throw new OwinException(Code::message('M1305'));
        }

        $bbsEvent = BbsEvent::find($request->no_bbs_event);
        if (!$bbsEvent) {
            throw new OwinException(Code::message('A1003'));
        }

        // bbs_event 를 통해 coupon_event 를 얻어온다
        $couponEvent = $bbsEvent->couponEvent;
        if (!$couponEvent) {
            throw new OwinException(Code::message('P4100'));
        }

        // NOTE: 이벤트의 시작, 종료일을 쿠폰의 시작, 종료일과 맞춰서 사용했다고 한다
        // 시작일 검사
        $now = now();
        if ($couponEvent->dt_start && $couponEvent->dt_start > $now) {
            throw new OwinException(sprintf(Code::message('P4101'), $couponEvent->dt_start->format('Y-m-d H:i:s')));
        }

        // 종료일 검사
        if ($couponEvent->dt_expire && $couponEvent->dt_expire < $now || $couponEvent->cd_cpe_status !== '121100') {
            throw new OwinException(Code::message('P4102'));
        }

        // 발급 여부 검사
        if (MemberCoupon::where('no_event', $couponEvent->no_event)
            ->where('no_user', $member->no_user)
            ->exists()) {
            throw new OwinException(Code::message('P4103'));
        }

        // 발급 가능 수량 검사
        // - coupon_event 에 at_pub_count 필드가 있지만 사용하지 않는다
        // - 직접 member_coupon 을 count 해서 검사한다
        if ($couponEvent->at_limit_count && MemberCoupon::where('no_event', $couponEvent->no_event)->count() >= $couponEvent->at_limit_count) {
            throw new OwinException(Code::message('P4104'));
        }

        // 멤버 쿠폰의 시작, 종료 날짜 지정
        $couponStartDt = null;
        $couponEndDt = null;

        // 1. 쿠폰 이벤트의 날짜가 지정된 경우 해당 날짜와 동일하게 지정
        if ($couponEvent->dt_start) {
            $couponStartDt = $couponEvent->dt_start;
        }

        if ($couponEvent->dt_expire) {
            $couponEndDt = $couponEvent->dt_expire;
        }

        // 2. 발급일 기준 유효기간이 있을경우
        if ($couponEvent->at_expire_day > 0) {
            $couponStartDt = $now;
            $couponEndDt = $now->copy()->addDays($couponEvent->at_expire_day - 1);
        }

        // 쿠폰 발급
        Couponservice::getMakeMemberCoupon($member->no_user, $couponEvent->no_event, $couponStartDt, $couponEndDt, 'EVENT');

        return response()->json([
            'result' => true,
            'message' => Code::message('P4105')
        ]);
    }
}

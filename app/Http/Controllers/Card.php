<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OwinException;
use App\Models\MemberCard;
use App\Services\CardService;
use App\Services\CodeService;
use App\Services\MemberService;
use App\Services\OrderService;
use App\Utils\Code;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Card extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * 카드 등록 (/card/regist)
     */
    public function regist(Request $request): JsonResponse
    {
        $request->validate([
            'no_cardnum' => 'required|numeric|digits_between:15,16',
            'no_expyea' => 'required|digits:2',
            'no_expmon' => 'required|digits:2',
            'no_pin' => 'required|digits:2'
        ]);

        // KB 알파카드 등록 차단 [2018.11.09 김목영 추가]
        if (in_array(substr($request->no_cardnum, 0, 6), ['949098', '516453'])) {
            throw new OwinException(Code::message('P1024_1'));
        }

        $noCard = (new CardService())->regist(Auth::id(), $request->all(), Auth::user()->memberCard?->where('yn_main_card', 'Y')->count() <= 0);

        return response()->json([
            'result' => true,
            'no_card' => $noCard
        ]);
    }

    /**
     * @return JsonResponse
     *
     * 카드리스트 (/card/lists)
     */
    public function lists(): JsonResponse
    {
        return response()->json([
            'result' => true,
            'card_list' => Auth::user()->memberCard->unique('no_card')->map(function ($card) {
                return [
                    'no_card' => $card->no_card,
                    'no_card_user' => $card->no_card_user,
                    'cd_card_corp' => $card->cd_card_corp,
                    'card_corp' => CodeService::getCode($card->cd_card_corp)->nm_code ?? '',
                    'yn_main_card' => $card->yn_main_card
                ];
            })->sortByDesc('yn_main_card')->values()
        ]);
    }

    /**
     * @return JsonResponse
     *
     * 카드등록갯수 (/card/get_card_cnt)
     */
    public function cardCnt(): JsonResponse
    {
        return response()->json([
            'result' => true,
            'ct_card' => Auth::user()->memberCard->unique('no_card')->count()
        ]);
    }

    /**
     * @param int $noCard
     * @return JsonResponse
     * @throws OwinException
     * @throws GuzzleException
     *
     * 카드삭제 (/card/remove)
     */
    public function remove(int $noCard): Jsonresponse
    {

        //주문내역 체크
        (new OrderService())->checkIncompleteOrder(Auth::id());

        //자동결제 차량에 연결된 카드 삭제
        Auth::user()->memberCarInfoAll->where('no_card', $noCard)
            ->where('yn_use_auto_parking', 'Y')
            ->whenNotEmpty(function () {
                throw new OwinException(Code::message('AP0009'));
//                $dsCarNumbers = $collect->pluck('ds_car_number')->all();
//                AutoParkingUtil::registerCar($dsCarNumbers, false);
//                MemberService::updateAutoParkingInfo([
//                    'no_user' => Auth::id()
//                ], [
//                    'yn_use_auto_parking' => 'N',
//                    'no_card' => null,
//                    'dt_auto_parking' => Carbon::now(),
//                ], [
//                    'ds_car_number' => $dsCarNumbers
//                ]);
            });

        if (Auth::user()->useSubscription?->exists && Auth::user()->useSubscription?->subscriptionPayment->card->no_card == $noCard) {
            throw new OwinException(Code::message('SUB012'));
        }

        //카드 삭제
        Auth::user()->memberCard->where('no_card', $noCard)->whenEmpty(function () {
            throw new OwinException(Code::message('P1020'));
        }, function ($card) use ($noCard) {
            (new CardService())->remove($noCard, Auth::id());

            if ($card->first()->yn_main_card == 'Y') {
                MemberService::updateMemberCardInfo(Auth::user()->refresh()->memberCard?->first(), [
                    'yn_main_card' => 'Y'
                ]);
            }
        });

        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @param int $noCard
     * @return JsonResponse
     * @throws OwinException
     *
     * 메인 카드 등록
     */
    public function mainCard(int $noCard): JsonResponse
    {
        Auth::user()->memberCard->whenNotEmpty(function ($card) use ($noCard) {
            $card->where('no_card', $noCard)->whenEmpty(function () {
                throw new OwinException(Code::message('P1020'));
            });
        })->unique('no_card')->map(function (MemberCard $card) use ($noCard) {
            $card->update([
                'yn_main_card' => $card->no_card == $noCard ? 'Y' : 'N'
            ]);
        });

        return response()->json([
            'result' => true
        ]);
    }
}

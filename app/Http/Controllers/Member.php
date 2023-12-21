<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EnumYN;
use App\Enums\Pickup;
use App\Enums\SearchBizKind;
use App\Enums\ServiceCode;
use App\Enums\Withdrawal;
use App\Exceptions\OwinException;
use App\Models\MemberAutoParkingHistory;
use App\Rules\MemberPasswordRule;
use App\Services\CertService;
use App\Services\CodeService;
use App\Services\MemberService;
use App\Services\OAuthService;
use App\Services\OrderService;
use App\Utils\AutoParking as AutoParkingUtil;
use App\Utils\Code;
use App\Utils\Common;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class Member extends Controller
{
    /**
     * @return JsonResponse
     *
     * 회원정보
     */
    public function getUser(): JsonResponse
    {
        return response()->json([
            'result' => true,
            'no_user' => Auth::id(),
            'nm_user' => Auth::user()->nm_user,
            'id_user' => Auth::user()->id_user,
            'is_master' => Auth::user()->is_master,
            'car_info' => Auth::user()->memberCarInfoAll->where('yn_main_car', 'Y')->map(function ($car) {
                return [
                    'ds_car_number' => $car->ds_car_number,
                    'yn_use_auto_parking' => $car->yn_use_auto_parking,
                    'yn_delete' => $car->yn_delete,
                    'cd_gas_kind' => $car->cd_gas_kind,
                    'cd_car_kind' => $car->carList->cd_car_kind
                ];
            })->first(),
            'card_info' => Auth::user()->memberCard->where('yn_main_card', 'Y')->map(function ($card) {
                return [
                    'cd_card_corp' => CodeService::getCode($card->cd_card_corp)->nm_code ?? '',
                    'no_card' => $card->no_card,
                    'no_card_user' => $card->no_card_user
                ];
            })->first()
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 회원 가입
     */
    public function regist(Request $request): JsonResponse
    {
        $osType = CodeService::getGroupCode('103');

        $request->validate([
            'id_user' => 'required|string|email:rfc,dns|unique:member,id_user',
            'password' => ['required', new MemberPasswordRule(), 'confirmed'],
            'password_confirmation' => 'required|string',
            'cd_phone_os' => ['string', Rule::in($osType->pluck('no_code')->values())],
            'ds_phone_model' => 'string',
            'ds_phone_version' => 'string',
            'ds_phone_nation' => 'string',
            'ds_phone_token' => 'string',
            'no_auth_seq' => 'required|string'
        ]);

        $no_user = Common::generateNoUser();

        $certData = CertService::getMemberOwnAuthlog([
            'no_auth_seq' => $request->no_auth_seq
        ])->whenEmpty(function () {
            throw new OwinException(Code::message('M1132'));
        })->first();

        MemberService::getMember([
            'ds_ci' => $certData['ds_ci']
        ])->whenNotEmpty(function ($member) {
            if (empty($member->first()->id_user) === false && $member->first()->ds_status == 'N') {
                throw new OwinException(Code::message('M1413'));
            }
        });

        MemberService::createMember([
            'member' => [
                'id_user' => $request->id_user,
                'ds_passwd_api' => bcrypt(md5($request->password)),
                'ds_status' => EnumYN::Y->name,
                'ds_birthday' => $certData->ds_birthday,
                'ds_sex' => $certData->ds_sex,
                'ds_phone' => $certData->ds_phone,
                'ds_ci' => $certData->ds_ci,
                'ds_di' => $certData->ds_di,
                'nm_nick' => $certData->ds_name,
                'nm_user' => $certData->ds_name,
                'cd_mem_level' => '104100'
            ],
            'detail' => [
                'ds_phone_agency' => $certData->ds_phone_agency,
                'cd_phone_os' => $request->cd_phone_os,
                'ds_phone_model' => $request->ds_phone_model,
                'ds_phone_version' => $request->ds_phone_version,
                'ds_phone_nation' => $request->ds_phone_nation,
                'ds_phone_token' => $request->ds_phone_token,
                'cd_third_party' => getAppType()->value,
            ]
        ], $no_user);

        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 회원 정보 수정
     */
    public function modify(Request $request): JsonResponse
    {
        $noUser = Auth::id() ?? CertService::getMemberOwnAuthlog([
            'no_auth_seq' => $request->no_auth_seq
        ])->map(function ($cert) {
            return MemberService::getMember([
                'ds_ci' => $cert->ds_ci
            ])->first();
        })->first()?->no_user;

        $request->validate([
            'no_user' => 'required|integer|in:' . $noUser,
            'no_auth_seq' => 'string',
            'password' => ['required', new MemberPasswordRule(), 'confirmed'],
            'password_confirmation' => 'required|string'
        ]);

        MemberService::updateMember(
            [
                'ds_passwd_api' => bcrypt(md5($request->password))
            ],
            [
                'no_user' => $noUser
            ]
        );

        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     * @throws GuzzleException
     *
     * 회원 탈퇴
     */
    public function withdrawal(Request $request): JsonResponse
    {
        $request->validate([
            'no_withdrawal' => ['required', Rule::in(array_column(Withdrawal::cases(), 'value'))],
            'ds_withdrawal' => ['required_if:no_withdrawal,==,4|string']
        ]);

        if ($request['no_withdrawal'] != 4) {
            $request['ds_withdrawal'] = '';
        }

        (new OrderService())->checkIncompleteOrder(Auth::id());

        Auth::user()->memberCarInfoAll->where('yn_use_auto_parking', 'Y')->whenNotEmpty(function ($collect) {
            $collect->map(function ($car) {
                AutoParkingUtil::registerCar([$car->ds_car_number], false);
                MemberService::updateAutoParkingInfo([
                    'no_user' => Auth::id(),
                    'ds_car_number' => $car->ds_car_number
                ], [
                    'yn_use_auto_parking' => 'N',
                    'no_card' => null,
                    'dt_auto_parking' => Carbon::now(),
                ]);
                (new MemberAutoParkingHistory([
                    'no_user' => Auth::id(),
                    'ds_car_number' => $car->ds_car_number,
                    'yn_use_auto_parking' => 'N',
                    'no_card' => null,
                ]))->saveOrFail();
            });
        });

        (new MemberService())->withdrawalMember([
            'member' => [
                'ds_status' => EnumYN::N->name
            ],
            'detail' => [
                'no_withdrawal' => $request['no_withdrawal'],
                'ds_withdrawal' => $request['ds_withdrawal']
            ]
        ], ['no_user' => Auth::id()]);


        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 회원 주문 목록
     */
    public function getOrderList(Request $request): JsonResponse
    {
        $request->validate([
            'size' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:0',
        ]);
        $size = (int)$request->get('size') ?: Code::conf('default_size');
        $offset = (int)$request->get('offset') ?: 0;
        $memberService = new MemberService();
        $items = $memberService->getOrderList(Auth::id(), $size, $offset);

        return response()->json([
            'result' => true,
            'total_cnt' => $items->total(),
            'per_page' => $items->perPage(),
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'order_list' => collect($items->items())->map(function ($list) {
                $cdBizKind = $list->cd_biz_kind;
                $nmShop = $list->shop?->nm_shop ?? $list->nm_site;
                $noShop = $list->shop?->no_shop ?? $list->no_site;
                $orderStatus = getOrderStatus(
                    cdBizKind: $cdBizKind,
                    cdOrderStatus: $list->cd_order_status,
                    cdPickupStatus: $list->cd_pickup_status,
                    cdPaymentStatus: $list->cd_payment_status,
                    parkingStatus: $list->cd_parking_status,
                    cdBizKindDetail: $list->cd_biz_kind_detail,
                );
                return [
                    'no_order' => $list->no_order,
                    'nm_order' => $list->nm_order,
                    'cd_order_status' => Arr::first($orderStatus),
                    'order_status' => Arr::last($orderStatus),
                    'dt_reg' => $list->dt_reg->format('Y-m-d H:i:s'),
                    'nm_partner' => $list->shop?->partner?->nm_partner,
                    'no_shop' => $noShop,
                    'nm_shop' => $nmShop,
                    'cd_biz_kind' => (string)$cdBizKind,
                    'biz_kind' => SearchBizKind::getBizKind((string)$cdBizKind)->name,
                    'pickup_type' => match (!empty($list->cd_pickup_type)) {
                        true => match (!empty(Pickup::tryFrom((int)$list->cd_pickup_type))) {
                            true => Pickup::tryFrom((int)$list->cd_pickup_type)->name,
                            default => Pickup::CAR->name
                        },
                        default => Pickup::CAR->name
                    },
                ];
            })
        ]);
    }

    /**
     * @return JsonResponse
     *
     * 회원 차량 리스트
     */
    public function getCar(): JsonResponse
    {
        $gasKind = CodeService::getGroupCode('204');
        $rkmMember = OAuthService::rkmMember(Auth::user());
        return response()->json([
            'result' => true,
            'car_info' => Auth::user()->memberCarInfoAll->map(function ($car) use ($gasKind, $rkmMember) {
                return [
                    'no' => $car->no,
                    'ds_car_number' => $car->ds_car_number,
                    'ds_car_color' => $car->ds_car_color,
                    'cd_gas_kind' => $car->cd_gas_kind,
                    'gas_kind' => $gasKind->firstWhere('no_code', $car->cd_gas_kind)->nm_code,
                    'no_maker' => $car->carList->no_maker,
                    'ds_maker' => $car->carList->ds_maker,
                    'seq' => $car->seq,
                    'ds_kind' => $car->carList->ds_kind,
                    'yn_main_car' => $car->yn_main_car,
                    'yn_use_auto_parking' => $car->yn_use_auto_parking,
                    'is_modify' => ($rkmMember['yn_access_status'] == 'Y' && $car->carList->no_maker == '1003') === false
                ];
            })
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * 회원 차량 등록
     */
    public function registCar(Request $request): JsonResponse
    {
        $gasKind = CodeService::getGroupCode('204');
        $request->merge([
            'ds_car_number' => str_replace(' ', '', $request->ds_car_number)
        ])->validate([
            'seq' => 'required|integer|gt:0',
            'ds_car_number' => 'required|regex:/\d{2,3}[가-힣]{1} ?\d{4}/ui',
            'ds_car_color' => 'required',
            'cd_gas_kind' => ['required', Rule::in($gasKind->pluck('no_code')->values())],
        ]);

        Auth::user()->memberCarInfoAll->where('ds_car_number', $request->ds_car_number)->whenNotEmpty(function () {
            throw new OwinException(Code::message('PA143'));
        }, function () use ($request) {
            MemberService::createMemberCarinfo([
                'no_user' => Auth::id(),
                'seq' => $request->seq,
                'ds_car_number' => $request->ds_car_number,
                'ds_car_color' => $request->ds_car_color,
                'ds_car_search' => substr($request->ds_car_number, -4),
                'cd_gas_kind' => $request->cd_gas_kind,
                'yn_main_car' => Auth::user()->memberCarInfoAll?->count() > 0 ? 'N' : 'Y'
            ]);
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
     * 회원 차량 수정
     */
    public function modifyCar(Request $request): JsonResponse
    {
        $gasKind = CodeService::getGroupCode('204');
        $request->merge([
            'ds_car_number' => str_replace(' ', '', $request->ds_car_number)
        ])->validate([
            'no' => 'required|integer',
            'seq' => 'required|integer|gt:0',
            'ds_car_number' => 'required|regex:/\d{2,3}[가-힣]{1} ?\d{4}/ui',
            'ds_car_color' => 'required',
            'cd_gas_kind' => ['required', Rule::in($gasKind->pluck('no_code')->values())],
        ]);

        Auth::user()->memberCarInfoAll->where('no', $request->no)->whenEmpty(function () {
            throw new OwinException(Code::message('M1510'));
        }, function () use ($request) {
            MemberService::upsertMemberCarInfo([
                'seq' => $request->seq,
                'ds_car_number' => $request->ds_car_number,
                'ds_car_color' => $request->ds_car_color,
                'ds_car_search' => substr($request->ds_car_number, -4),
                'cd_gas_kind' => $request->cd_gas_kind
            ], [
                'no' => $request->no,
                'no_user' => Auth::id()
            ]);
        });

        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @param int $no
     * @return JsonResponse
     * @throws OwinException
     *
     * 회원 차량정보
     */
    public function getCarInfo(int $no): JsonResponse
    {
        $car = Auth::user()->memberCarInfoAll->where('no', $no)->whenEmpty(function () {
            throw new OwinException(Code::message('M1510'));
        })->first();

        $gasKind = CodeService::getGroupCode('204');
        return response()->json([
            'result' => true,
            'no' => $car->no,
            'ds_car_number' => $car->ds_car_number,
            'ds_car_color' => $car->ds_car_color,
            'cd_gas_kind' => $car->cd_gas_kind,
            'gas_kind' => $gasKind->firstWhere('no_code', $car->cd_gas_kind)->nm_code,
            'no_maker' => $car->carList->no_maker,
            'ds_maker' => $car->carList->ds_maker,
            'seq' => $car->seq,
            'ds_kind' => $car->carList->ds_kind,
            'yn_main_car' => $car->yn_main_car
        ]);
    }

    /**
     * @param int $no
     * @return JsonResponse
     * @throws OwinException
     *
     * 회원 차량 삭제
     */
    public function deleteCar(int $no): JsonResponse
    {
        $memberCar = Auth::user()->memberCarInfoAll->where('no', $no)->whenEmpty(function () {
            throw new OwinException(Code::message('M1510'));
        }, function ($car) {
            if ($car->first()->yn_main_car == 'Y') {
                throw new OwinException(Code::message('PA142'));
            }
            if (empty($car->first()->no_card) === false && $car->first()->yn_use_auto_parking == 'Y') {
                throw new OwinException(Code::message('AP0004'));
            }
        })->first();
        MemberService::deleteMemberCarInfo($memberCar);

        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @param int $no
     * @return JsonResponse
     * @throws OwinException
     *
     * 회원 차량 메인 등록
     */
    public function mainCar(int $no): JsonResponse
    {
        $rkmMember = OAuthService::rkmMember(Auth::user());
        if ($rkmMember['yn_access_status'] == 'Y') {
            throw new OwinException(Code::message('M1520'));
        }

        Auth::user()->memberCarInfoAll->whenNotEmpty(function ($car) use ($no) {
            $car->where('no', $no)->whenEmpty(function () {
                throw new OwinException(Code::message('M1510'));
            });
        })->map(function ($car) use ($no) {
            MemberService::updateMemberCarinfo($car, [
                'yn_main_car' => $car->no == $no ? 'Y' : 'N'
            ]);
        });

        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 회원 문의 리스트
     */
    public function getQnaList(Request $request): JsonResponse
    {
        $request->validate([
            'size' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:0'
        ]);

        $size = (int)$request->get('size') ?: Code::conf('default_size');
        $offset = (int)$request->get('offset') ?: 0;
        $items = MemberService::getQnaList([
            'no',
            'ds_title',
            'ds_content',
            'dt_reg',
            'id_answer',
            'ds_answer_content',
            'dt_answer'
        ], [
            'no_user' => Auth::id(),
            'cd_service' => strval(ServiceCode::PICKUP->value),
            'cd_third_party' => getAppType()->value,
        ], $size, $offset);

        return response()->json([
            'result' => true,
            'total_cnt' => $items->total(),
            'per_page' => $items->perPage(),
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'list' => collect($items->items())->map(function ($qna) {
                return [
                    'no' => $qna->no,
                    'ds_title' => $qna->ds_title,
                    'ds_content' => $qna->ds_content,
                    'dt_reg' => $qna->dt_reg->format('Y-m-d H:i:s'),
                    'id_answer' => $qna->id_answer,
                    'ds_answer_content' => $qna->ds_answer_content,
                    'dt_answer' => $qna->dt_answer,
                    'yn_answer' => ($qna['id_answer'] and $qna['ds_answer_content']) ? 'Y' : 'N'
                ];
            })

        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 회원 문의 등록
     */
    public function registerQna(Request $request): JsonResponse
    {
        $request->validate([
            'ds_title' => 'required|string',
            'ds_content' => 'required|string'
        ]);

        MemberService::createMemberQnaInfo([
            'cd_question' => '107100',
            'no_user' => Auth::id(),
            'ds_title' => $request->ds_title,
            'ds_content' => $request->ds_content,
            'ds_userip' => $request->server('REMOTE_ADDR'),
            'cd_third_party' => getAppType()->value,
        ]);

        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * 회원비밀번호 인증
     */
    public function passwdCheck(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required'
        ]);

        return response()->json([
            'result' => Hash::check(md5($request->password), Auth::user()->getAuthPassword())
        ]);
    }

    public function updateUdid(Request $request): JsonResponse
    {
        $request->validate([
            'ds_udid' => 'required'
        ]);

        MemberService::updateMemberDetail([
            'ds_udid' => $request->ds_udid
        ], [
            'no_user' => Auth::id()
        ]);

        return response()->json([
            'result' => true
        ]);
    }


    /**
     * 핸드폰 번호 변경
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePhone(Request $request): JsonResponse
    {
        $request->validate([
            'no_auth_seq' => 'required',
            'ds_phone' => 'required',
        ]);

        // 본인인증 내역 확인
        $certData = CertService::getMemberOwnAuthlog([
            'no_auth_seq' => $request->no_auth_seq,
            'ds_phone' => $request->ds_phone,
            'ds_auth_result2' => EnumYN::Y->name,
            ['ds_ci', 'IS NOT', null],
            ['ds_di', 'IS NOT', null],
            ['dt_reg', '>=', now()->subDay()] // 혹시 모를 조작 방지를 위해 하루 이내 건만 조회
        ])->whenEmpty(function () {
            throw new OwinException(Code::message('M1132'));
        })->first();

        MemberService::updateMember([
            'ds_phone' => $certData->ds_phone,
            'ds_ci' => $certData->ds_ci,
            'ds_di' => $certData->ds_di,
        ], [
            'no_user' => Auth::id()
        ]);

        return response()->json([
            'result' => true
        ]);
    }
}

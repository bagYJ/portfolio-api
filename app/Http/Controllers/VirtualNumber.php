<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OwinException;
use App\Services\OrderService;
use App\Services\VirtualNumberService;
use App\Utils\BizCall;
use App\Utils\Code;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class VirtualNumber extends Controller
{
    /**
     * @return JsonResponse
     * @throws OwinException
     * @throws GuzzleException
     *
     * 가상번호 리스트 (유지보수 계약 내용에 해당되는 회선수) 요청
     */
    public function getList(): JsonResponse
    {
        $result = BizCall::getVns();

        if ($result && count($result['rec'])) {
            $body = [];
            foreach ($result['rec'] as $row) {
                $body[] = [
                    'virtual_number' => $row['vn'],
                    'yn_possible' => $row['rn'] ? 'Y' : 'N',
                ];
            }

            VirtualNumberService::insertDuplicateVns($body);

            return response()->json([
                'result' => true,
            ]);
        } else {
            throw new OwinException('비즈콜 통신에 에러가 발생했습니다.', $result['rt']);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     * @throws OwinException
     *
     * 가상번호 자동 설정 요청 (비어있는 가상번호 순차적으로 부여됨)
     */
    public function setAutoSetting(Request $request): JsonResponse
    {
        $auth = Auth::user();

        $dsPhone = str_replace('-', '', trim($auth['ds_phone'])); //회원 휴대폰번호

        try {
            $result = BizCall::autoMapping($dsPhone);
            if ($result) {
                if ($result['rt'] === 0) {
                    VirtualNumberService::insertVnsLog([
                        'virtual_number' => $result['vn'],
                        'yn_success' => 'Y',
                    ]);
                    return response()->json([
                        'result' => true,
                        'ds_safe_number' => $result['vn'],
                    ]);
                } else {
                    VirtualNumberService::insertVnsLog([
                        'yn_success' => 'N',
                        'fail_reason' => $result['rs'],
                    ]);
                    throw new OwinException('비즈콜 통신에 에러가 발생했습니다.', $result['rt']);
                }
            } else {
                VirtualNumberService::insertVnsLog([
                    'yn_success' => 'N',
                    'fail_reason' => '통신에 문제가 발생했습니다.',
                ]);

                throw new OwinException(Code::message('P2043'));
            }
        } catch (Throwable $t) {
            throw new OwinException($t->getMessage());
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     *
     * 가상번호 자동 설정 요청 (비어있는 가상번호가 있어도 마지막 부여된 전화번호 다음번호로 세팅)
     */
    public function setVnClose(Request $request): JsonResponse
    {
        $request->validate([
            'no_user' => 'required|integer',
            'no_order' => 'required|string',
            'virtual_number' => 'required|string',
        ]);

        $result = BizCall::setVn([
            'virtualNumber' => $request->virtual_number,
            'realNumber' => " ",
        ]);
        if ($result) {
            if ($result['rt'] === 0) {
                OrderService::updateOrderVns([
                    'no_order' => $request->no_order
                ], [
                    'ds_safe_number' => Code::conf('virtual_number.default_number'),
                ], [
                    'dt_use_end' => DB::raw('NOW()'),
                    'yn_success' => 'Y',
                ]);

                return response()->json([
                    'result' => true,
                ]);
            } else {
                throw new OwinException('비즈콜 통신에 에러가 발생했습니다.', $result['rt']);
            }
        } else {
            throw new OwinException(Code::message('P2043'));
        }
    }
}

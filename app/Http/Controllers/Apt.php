<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OwinException;
use App\Services\AptService;
use App\Utils\Code;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class Apt extends Controller
{
    /**
     * @return JsonResponse
     *
     * 회원이 등록한 아파트 목록
     */
    public function getMemberAptList(): JsonResponse
    {
        return response()->json([
            'result' => true,
            'apt_list' => AptService::list(Auth::id())->first()
        ]);
    }

    /**
     * @param string $idApt
     * @return JsonResponse
     * @throws OwinException
     *
     * 아파트 등록
     */
    public function register(string $idApt): JsonResponse
    {
        AptService::register($idApt, Auth::id());

        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @param string $idApt
     * @return JsonResponse
     *
     * 아파트 삭제
     */
    public function remove(string $idApt): JsonResponse
    {
        AptService::deleteApt([
            'no_user' => Auth::id(),
            'id_apt' => AptService::list(Auth::id())->where('id_apt', $idApt)
                ->whenEmpty(function () {
                    throw new OwinException(Code::message('B3080'));
                })->first()
        ]);

        return response()->json([
            'result' => true
        ]);
    }

    /**
     * @return JsonResponse
     *
     * 아파트 목록
     */
    public function list(): JsonResponse
    {
        return response()->json([
            'result' => true,
            'apt_list' => AptService::aptList()
        ]);
    }
}

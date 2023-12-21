<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\EvChargerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EvCharger extends Controller
{
    /**
     * @return JsonResponse
     *
     * 검색 필터
     */
    public function filter(): JsonResponse
    {
        return response()->json([
            'result' => true,
            'filter' => EvChargerService::getFilter()
        ]);
    }

    /**
     * @param string $idStat
     * @return JsonResponse
     * @throws ValidationException
     *
     * 충전소 정보
     */
    public function info(string $idStat): JsonResponse
    {
        Validator::make(['idStat' => $idStat], ['idStat' => 'required'])->validate();

        return response()->json([
            'result' => true,
            'item' => EvChargerService::getInfo($idStat)
        ]);
    }
}

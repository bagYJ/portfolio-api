<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Car extends Controller
{
    /**
     * @return JsonResponse
     *
     * 차량 제조사 목록
     */
    public function makerList(): JsonResponse
    {
        return response()->json([
            'result' => true,
            'car_maker_list' => CarService::makerList()
        ]);
    }

    /**
     * @param int $noMaker
     * @return JsonResponse
     * @throws ValidationException
     *
     * 제조사 별 차종 목록
     */
    public function kindByCarList(int $noMaker): JsonResponse
    {
        Validator::make(['noMaker' => $noMaker], ['noMaker' => 'required|integer'])->validate();

        return response()->json([
            'result' => true,
            'kind_by_car_list' => CarService::kindByCarList($noMaker)
        ]);
    }
}

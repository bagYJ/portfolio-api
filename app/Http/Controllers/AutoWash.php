<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\OwinException;
use App\Exceptions\TMapException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutoWash extends Controller
{
    /**
     * @param Request $request
     * @param int $noShop
     * @return JsonResponse
     * @throws OwinException
     * @throws TMapException
     */
    public function info(Request $request, int $noShop): JsonResponse
    {
        return (new Shop($request))->info($request, $noShop);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function intro(Request $request): JsonResponse
    {
        return (new Wash($request))->intro($request);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     * @throws TMapException
     */
    public function payment(Request $request): JsonResponse
    {
        return (new Order($request))->payment($request);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws OwinException
     */
    public function orderComplete(Request $request): JsonResponse
    {
        return (new Wash($request))->orderComplete($request);
    }
}

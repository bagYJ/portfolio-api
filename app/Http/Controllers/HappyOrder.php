<?php

namespace App\Http\Controllers;

use App\Exceptions\SlackNotiException;
use App\Utils\Spc;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HappyOrder extends Controller
{

    /**
     * todo 배치에서 사용되는 항목, 프록시로 이전 후 삭제 필요
     * @param Request $request
     * @return void
     * @throws SlackNotiException
     */
    public function arrTime(Request $request): void
    {
        $request->validate([
            'orderChannel' => 'required|string',
            'brandCode' => 'required|string',
            'storeCode' => 'required|string',
            'orderId' => 'required|string',
            'arvYn' => 'required|string',
            'arvHm' => 'required|string',
        ]);

        Spc::uptime(
            $request->brandCode,
            $request->storeCode,
            $request->orderId,
            $request->arvYn,
            Carbon::createFromFormat('Y-m-d H:i:s', $request->arvHm)->format('YmdHi'),
        );
    }
}

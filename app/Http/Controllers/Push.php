<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PushBizKind;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class Push extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'no_users' => 'required|array',
            'title' => 'required|string',
            'body' => 'required|string',
            'biz_kind' => ['required', Rule::in(PushBizKind::keys())],
            'biz_kind_detail' => 'string',
            'status' => 'string',
            'no_shop' => 'integer',
            'no_order' => 'string',
            'is_ordering' => 'string|in:Y,N',
            'data' => 'array'
        ]);
        PushService::send($request);

        return response()->json([
            'result' => true
        ]);
    }
}

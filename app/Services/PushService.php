<?php
declare(strict_types=1);

namespace App\Services;

use App\Queues\Fcm\Fcm;
use Illuminate\Http\Request;

class PushService extends Service
{
    public static function send(Request $request): void
    {
        $data = [
            'title' => $request->title,
            'message' => $request->body,
            'biz_kind' => strtoupper($request->biz_kind),
            'biz_kind_detail' => $request->biz_kind_detail,
            'status' => $request->status,
            'no_shop' => $request->no_shop,
            'no_order' => $request->no_order,
            'is_ordering' => $request->is_ordering
        ];
        if (!empty($request->data)) {
            $data += $request->data;
        }
        (new Fcm(
            type: $request->biz_kind,
            data: $data,
            notification: true,
            receiver: 'ext',
            noUser: $request->no_users
        ))->init();
    }
}

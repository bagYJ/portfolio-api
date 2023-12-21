<?php

use App\Models\MemberDetail;
use App\Models\User;
use App\Utils\Code;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        ini_set('memory_limit','512M');
        User::with([
            'memberDetail' => function ($query) {
                $query->whereNotNull('ds_udid');
            }
        ])->where([
            'ds_status' => 'Y',
            'yn_push_msg_event'=> 'Y'
        ])->whereNot('cd_mem_level', '104800')->get()->filter(function (User $user) {
            return empty($user->memberDetail->ds_udid) === false;
        })->values()->chunk(1000)->map(function (Collection $members) {
            $response = Http::withHeaders([
                'Authorization' => sprintf('key=%s', Code::fcm('user.appkey')),
                'Content-Type' => 'application/json'
            ])->post(Code::fcm('uri'), [
                'registration_ids' => $members->pluck('memberDetail.ds_udid')
            ]);

            $result = json_decode($response->body());
            array_filter($result->results, function ($res, $key) use ($members) {
                if ($members->get($key)?->memberDetail->exists) {
                    $members->get($key)->memberDetail->ds_udid = (isset($res->error) ? '' : $members->get($key)->memberDetail->ds_udid);
                }
            }, ARRAY_FILTER_USE_BOTH);

            MemberDetail::massUpdate(
                values: $members->pluck('memberDetail')
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};

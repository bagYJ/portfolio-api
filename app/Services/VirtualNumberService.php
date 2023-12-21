<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\VirtualNumberIssueLog;
use App\Models\VirtualNumberList;
use Illuminate\Database\Eloquent\Model;

class VirtualNumberService extends Service
{
    /**
     * @param $body
     * @return int
     */
    public static function insertDuplicateVns($body)
    {
        //todo dt_reg default 값에 CURRENT_TIMESTAMP 추가하기! (로컬 테스트 결과 이상 없음)
        return VirtualNumberList::upsert($body, [
            'no',
            'virtual_number',
        ]);
    }

    /**
     * @param $data
     * @param $where
     * @return bool
     */
    public static function updateVns($data, $where)
    {
        return VirtualNumberList::where($where)->update($data);
    }

    /**
     * @param $data
     * @return mixed
     */
    public static function insertVnsLog($data)
    {
        return VirtualNumberIssueLog::create($data);
    }

    /**
     * @param $data
     * @param $where
     * @return bool
     */
    public static function updateVnsLog($data, $where)
    {
        return VirtualNumberIssueLog::where($where)->update($data);
    }

    /**
     * @return VirtualNumberList|Model|object|null
     */
    public static function getLastVnsNumber()
    {
        return VirtualNumberList::where('yn_success', 'y')->orderByDesc('no')->limit(1)->first();
    }
}

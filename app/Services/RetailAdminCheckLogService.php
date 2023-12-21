<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RetailAdminChkLog;

class RetailAdminCheckLogService extends Service
{
    /**
     * @param $data
     * @return void
     */
    public static function insertRetailAdminChkLog($data)
    {
        RetailAdminChkLog::create($data);
    }
}

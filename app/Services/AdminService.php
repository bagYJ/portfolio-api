<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Administrator;
use App\Models\PartnerManager;
use Illuminate\Support\Collection;

class AdminService extends Service
{
    /**
     * @return Collection
     *
     * 관리자 리스트 (이메일)
     */
    public function adminList(): Collection
    {
        return Administrator::whereNotNull('ds_email')->get();
    }

    /**
     * @param array $parameter
     * @param array|null $whereNotNull
     * @return Collection
     *
     * 점주 리스트
     */
    public static function getPartnerManager(array $parameter, ?array $whereNotNull = []): Collection
    {
        return PartnerManager::where($parameter)
            ->whereNotNull($whereNotNull)->get();
    }
}

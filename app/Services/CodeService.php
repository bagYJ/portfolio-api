<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CodeManage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CodeService extends Service
{
    /**
     * @param string|null $code
     * @return CodeManage|null
     */
    public static function getCode(?string $code): ?CodeManage
    {
        $cacheKey = sprintf('code_manage_%s', $code);

        return match (Cache::has($cacheKey)) {
            true => Cache::get($cacheKey),
            default => (function () use ($code, $cacheKey) {
                $codeResult = CodeManage::where('no_code', $code)->first();
                Cache::store('file')->put($cacheKey, $codeResult, 60 * 60);

                return $codeResult;
            })()
        };
    }

    /**
     * @param string $group
     * @return Collection
     */
    public static function getGroupCode(string $group): Collection
    {
        return CodeManage::where('no_group', $group)->get();
    }
}

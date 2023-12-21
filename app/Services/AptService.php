<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EnumYN;
use App\Exceptions\OwinException;
use App\Models\AptList;
use App\Models\MemberApt;
use App\Utils\Code;
use Illuminate\Support\Collection;
use Throwable;

class AptService extends Service
{
    /**
     * @param int $noUser
     * @return Collection
     *
     * 회원 아파트 리스트
     */
    public static function list(int $noUser): Collection
    {
        return MemberApt::with('aptList')->where('no_user', $noUser)->get()
            ->map(function ($list) {
                return [
                    'id_apt' => $list->id_apt,
                    'nm_apt' => $list->aptList->nm_apt,
                    'yn_regist' => match ($list->aptList->count() > 0) {
                        true => EnumYN::Y->name,
                        default => EnumYN::N->name
                    }
                ];
            });
    }

    /**
     * @param string $idApt
     * @param int $noUser
     * @return void
     * @throws OwinException
     * @throws Throwable
     *
     * 회원 아파트 등록
     */
    public static function register(string $idApt, int $noUser): void
    {
        $apt = AptList::where('id_apt', $idApt)->get()->whenEmpty(function () {
            throw new OwinException(Code::message('B3090'));
        })->first();

        if ((MemberApt::where([
                'no_user' => $noUser
            ])->count()) > 0) {
            throw new OwinException(Code::message('B3010'));
        }

        (new MemberApt([
            'id_apt' => $apt->id_apt,
            'no_user' => $noUser,
            'dt_reg' => now()
        ]))->saveOrFail();
    }

    /**
     * @param array $parameter
     * @return void
     *
     * 회원 아파트 삭제
     */
    public static function deleteApt(array $parameter): void
    {
        MemberApt::where($parameter)->delete();
    }

    /**
     * @return Collection
     *
     * 살다 관련 아파트 리스트
     */
    public static function aptList(): Collection
    {
        return AptList::select(['id_apt', 'nm_apt'])->get();
    }
}

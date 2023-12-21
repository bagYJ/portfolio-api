<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EnumYN;
use App\Models\BbsQna;
use App\Models\Beacon;
use App\Models\CarList;
use App\Models\CarListHk;
use App\Models\GsSaleCard;
use App\Models\Member;
use App\Models\MemberApt;
use App\Models\MemberCard;
use App\Models\MemberCarinfo;
use App\Models\MemberCarinfoLog;
use App\Models\MemberDeal;
use App\Models\MemberDetail;
use App\Models\MemberEvent;
use App\Models\MemberGroup;
use App\Models\MemberOwinCouponRequest;
use App\Models\OauthAccessTokens;
use App\Models\PersonalAccessTokens;
use App\Models\TollMember;
use App\Models\User;
use App\Utils\Code;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\NewAccessToken;
use Throwable;

class MemberService extends Service
{
    /**
     * @param $noUser
     * @return Builder|Model|object
     */
    public static function get($noUser)
    {
        return Member::where('no_user', $noUser)->with([
            'memberDetail'
        ])->first();
    }


    /**
     * @param array $parameter
     * @return Collection
     */
    public static function getMember(array $parameter): Collection
    {
        return User::with('memberDetail')->where($parameter)->get();
    }


    /**
     * @param array $parameter
     * @return Collection
     */
    public static function getMemberOrWhere(array $parameter): Collection
    {
        return User::orWhere(function ($query) use ($parameter) {
            foreach ($parameter as $key => $value) {
                $query->orWhere($key, $value);
            }
        })->get();
    }


    /**
     * @param array $parameter
     * @return Collection
     */
    public static function getMemberDetail(array $parameter): Collection
    {
        return MemberDetail::with('memberCarInfo')->where($parameter)->get();
    }


    /**
     * @param MemberDetail $memberDetail
     * @param array $parameter
     * @return void
     * @throws Throwable
     */
    public function memberDetailUpdate(MemberDetail $memberDetail, array $parameter): void
    {
        $memberDetail->updateOrFail($parameter);
    }

    /**
     * @param int $noUser
     * @return NewAccessToken
     */
    public static function createAccessToken(int $noUser): NewAccessToken
    {
        return User::find($noUser)->createToken('nav_access_token', $noUser, ['*']);
    }

    /**
     * @param Request $request
     * @return void
     */
    public function profileEdit(Request $request): void
    {
        if (empty($request->post('nm_change_nick')) === false) {
            Auth::user()->updateOrFail([
                'nm_nick' => $request->post('nm_change_nick')
            ]);
        }

        if ($request->hasFile('file_profile')) {
            $path = Storage::putFileAs(
                Code::conf('member.profile_path') . substr(Auth::id(), -2),
                $request->file('file_profile'),
                Auth::id() . '.' . $request->file('file_profile')->extension()
            );

            Auth::user()->memberDetail->updateOrFail([
                'ds_profile_path' => $path
            ]);
        }
    }

    /**
     * @param array $param
     * @return void
     * @throws Throwable
     */
    public function owinCouponRequest(array $param): void
    {
        (new MemberOwinCouponRequest($param))->saveOrFail();
    }

    /**
     * @param array $parameter
     * @return Collection
     */
    public function beacon(array $parameter): Collection
    {
        return Beacon::where($parameter)->get();
    }

    /**
     * @param array $noUsers
     * @param array $noSeqs
     * @param string $noSeq
     * @return Collection
     */
    public static function memberEvent(array $noUsers, array $noSeqs, string $noSeq): Collection
    {
        return MemberEvent::whereIn('no_user', $noUsers)
            ->whereNotIn('no_seq', $noSeqs)
            ->where('no_seq', '!=', $noSeq)->get();
    }

    /**
     * @param array $parameter
     * @param array $where
     * @return void
     */
    public function memberGroupFirstOrCreate(array $parameter, array $where): void
    {
        MemberGroup::firstOrCreate($where, $parameter);
    }

    /**
     * @param array $parameter
     * @return Collection
     */
    public static function memberDeal(array $parameter): Collection
    {
        return MemberDeal::where($parameter)->get();
    }

    /**
     * @param array $parameter
     * @param array|null $where
     * @return MemberDeal
     */
    public function memberDealFirstOrCreate(array $parameter, ?array $where): MemberDeal
    {
        return MemberDeal::firstOrCreate($parameter, $where);
    }

    /**
     * @param string $memLevel
     * @param string $accountStatus
     * @param int $seq
     * @return CarList|CarListHk
     */
    public function carDetail(string $memLevel, string $accountStatus, int $seq): CarList|CarListHk
    {
        $carTable = match ($memLevel == '104400' && $accountStatus == EnumYN::Y->name) {
            true => new CarListHk(),
            default => new CarList()
        };

        return $carTable->where('seq', $seq)->first();
    }

    /**
     * @param array $parameter
     * @return Collection
     */
    public function memberCarinfo(array $parameter): Collection
    {
        return MemberCarinfo::where($parameter)->get();
    }

    /**
     * @param array $parameter
     * @return void
     * @throws Throwable
     */
    public static function createMemberCarinfo(array $parameter): void
    {
        (new MemberCarinfo($parameter))->saveOrFail();
    }

    /**
     * @param array $parameter
     * @return void
     * @throws Throwable
     */
    public function createMemberCarinfoLog(array $parameter): void
    {
        (new MemberCarinfoLog($parameter))->saveOrFail();
    }

    /**
     * @param array $parameter
     * @param int $no_user
     * @return void
     * @throws Throwable
     */
    public static function createMember(array $parameter, int $no_user): void
    {
        (new User(array_merge($parameter['member'], ['no_user' => $no_user])))->saveOrFail();
        (new MemberDetail(array_merge($parameter['detail'], ['no_user' => $no_user])))->saveOrFail();
    }

    /**
     * @param array $parameter
     * @param array|null $where
     * @return void
     */
    public static function upsertMemberCarInfo(array $parameter, ?array $where): void
    {
        MemberCarinfo::updateOrCreate($where, $parameter);
    }

    /**
     * @param array $parameter
     * @param array $where
     * @return void
     */
    public static function updateMember(array $parameter, array $where): void
    {
        User::where($where)->update($parameter);
    }

    /**
     * @param array $parameter
     * @param array $where
     * @return void
     */
    public static function updateMemberDetail(array $parameter, array $where): void
    {
        MemberDetail::where($where)->update($parameter);
    }

    /**
     * @param array $parameter
     * @param array $noUser
     * @return void
     */
    public function withdrawalMember(array $parameter, array $noUser): void
    {
        self::updateMember($parameter['member'], $noUser);
        self::updateMemberDetail($parameter['detail'], $noUser);

        PersonalAccessTokens::where('tokenable_id', $noUser)->delete();
        OauthAccessTokens::where('user_id', $noUser)->delete();
    }

    /**
     * @param array $parameter
     * @return Collection
     */
    public function tollMember(array $parameter): Collection
    {
        return TollMember::where($parameter)->get();
    }

    /**
     * @param MemberCarinfo $carinfo
     * @param array $parameter
     * @return void
     */
    public static function updateMemberCarinfo(MemberCarinfo $carinfo, array $parameter): void
    {
        $carinfo->update($parameter);
    }

    /**
     * @param array $where
     * @param array $parameter
     * @return void
     */
    public static function updateAutoParkingInfo(array $where, array $parameter, ?array $whereIn = null): void
    {
        MemberCarinfo::where($where)
        ->when(empty($whereIn) === false, function($query) use ($whereIn) {
            foreach ($whereIn as $key => $value) {
                $query->whereIn($key, $value);
            }
        })->update($parameter);
    }

    /**
     * @param MemberCarinfo $carinfo
     * @return void
     */
    public static function deleteMemberCarInfo(MemberCarinfo $carinfo): void
    {
        $carinfo->update(['yn_delete' => 'Y']);
    }

    /**
     * @param array $parameter
     * @return Collection
     */
    public function getMyApt(array $parameter): Collection
    {
        return MemberApt::with('aptList')->where($parameter)->get();
    }

    /**
     * @param int $seq
     * @return Collection
     */
    public static function getCarInfo(int $seq): Collection
    {
        return CarList::where('seq', $seq)->get();
    }

    /**
     * @param $noUser
     * @return Builder|Model|object|null
     */
    public function getMemberDealInfo($noUser)
    {
        return MemberDeal::with(['promotionDeal'])->where('no_user', $noUser)->first();
    }

    /**
     * @param array $parameter
     * @param array $where
     * @return void
     */
    public function updateGsSaleCard(array $parameter, array $where): void
    {
        GsSaleCard::where($where)->update($parameter);
    }

    /**
     * @param int $noUser
     * @param int $size
     * @param int $offset
     * @return LengthAwarePaginator
     */
    public function getOrderList(int $noUser, int $size, int $offset): LengthAwarePaginator
    {
        return (new OrderService())->getOrderListByMember($noUser, $size, $offset);
    }

    /**
     * @param MemberCard|null $card
     * @param array $parameter
     * @return void
     */
    public static function updateMemberCardInfo(?MemberCard $card, array $parameter): void
    {
        $card?->update($parameter);
    }

    /**
     * @param array $select
     * @param array $where
     * @param int $size
     * @param int $offset
     * @return LengthAwarePaginator
     */
    public static function getQnaList(array $select, array $where, int $size, int $offset): LengthAwarePaginator
    {
        return BbsQna::select($select)->where($where)->orderByDesc('no')->paginate(perPage: $size, page: $offset);
    }

    /**
     * @param array $parameter
     * @return void
     * @throws Throwable
     */
    public static function createMemberQnaInfo(array $parameter): void
    {
        (new BbsQna($parameter))->saveOrFail();
    }

    /**
     * @param string $parameter
     * @return void
     */
    public static function updateEventPushYn(string $parameter): void
    {
        User::where(['no_user' => Auth::id()])->update(['yn_push_msg_event' => $parameter]);
    }

    /**
     * @param array $noUsers
     * @param array|null $whereNotNull
     * @return Collection
     */
    public static function getMemberWithParam(array $noUsers, ?array $whereNotNull = []): Collection
    {
        return MemberDetail::whereIn('no_user', $noUsers)->whereNotNull($whereNotNull)->get();
    }
}

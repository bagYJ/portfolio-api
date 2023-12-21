<?php

namespace App\Models;

use App\Enums\MemberType;
use App\Traits\HasApiTokens;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

//use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $ds_passwd
 * @property string $ds_passwd_api
 * @property string $id_user
 * @property string $nm_user
 * @property string $nm_nick
 * @property string $cd_reg_kind
 * @property string $ds_status
 * @property string $ds_phone
 * @property string $ds_ci
 * @property string $ds_birthday
 * @property string $ds_sex
 * @property Carbon $dt_reg
 * @property int $no_user
 * @property string $cd_mem_level
 * @property string $cd_mem_type
 * @property MemberDetail $memberDetail
 * @property Collection $memberCard
 * @property Collection $memberCoupon
 * @property Collection $subscription
 * @property Subscription $useSubscription
 * @property Collection $memberParkingCoupon
 * @property Collection $memberPointCard
 *
 * @property string $cd_third_party
 * @property bool $is_master
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    public const CREATED_AT = 'dt_reg';
    public const UPDATED_AT = 'dt_upt';

    protected $table = 'member';

    protected $primaryKey = 'no_user';

    public $incrementing = false;
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nm_nick',
        'yn_owin_member',
        'ds_social',
        'no_user',
        'id_user',
        'ds_passwd_api',
        'ds_status',
        'ds_birthday',
        'ds_sex',
        'ds_phone',
        'ds_di',
        'ds_ci',
        'nm_user',
        'cd_mem_level',
        'cd_mem_type',
        'yn_push_msg_mobile'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends = ['is_master'];

    public function getAuthPassword(): ?string
    {
        return $this->ds_passwd_api;
    }

    public function findForPassport($username): self
    {
        return $this->where('id_user', $username)->first();
    }

    public function oauthAccessTokens(): HasMany
    {
        return $this->hasMany(OauthAccessTokens::class, 'user_id');
    }

    public function memberDetail(): HasOne
    {
        return $this->hasOne(MemberDetail::class, 'no_user');
    }

    public function beaconCount(): HasMany
    {
        return $this->hasMany(Beacon::class, 'no_user')
            ->where('cd_device_status', '=', '303100');
    }

    public function beacon(): HasOne
    {
        return $this->hasOne(Beacon::class, 'no_user')
            ->where('cd_device_status', '=', '303100')
            ->select(
                'no_user',
                DB::raw(
                    '
            '
                )
            );
    }

    public function memberCarInfo(): HasOne
    {
        return $this->hasOne(MemberCarinfo::class, 'no_user', 'no_user')
            ->where('yn_main_car', '=', 'Y');
    }

    public function memberCarInfoAll(): HasMany
    {
        return $this->hasMany(MemberCarinfo::class, 'no_user', 'no_user')->with(['carList'])->orderByDesc('yn_main_car');
    }

    public function memberCard(): HasMany
    {
        return $this->hasMany(MemberCard::class, 'no_user')->orderByDesc('yn_main_card');
    }

    public function memberFavorMap(): HasMany
    {
        return $this->hasMany(MemberFavorMap::class, 'no_user');
    }

    public function memberApt(): HasOne
    {
        return $this->hasOne(MemberApt::class, 'no_user')
            ->orderByDesc('no');
    }

    public function memberCoupon(): HasMany
    {
        return $this->hasMany(MemberCoupon::class, 'no_user');
    }

    public function memberPointCard(): HasMany
    {
        return $this->hasMany(MemberPointcard::class, 'no_user', 'no_user')
            ->where('yn_delete', '=', 'N')->with(['promotionDeal', 'gsSaleCard']);
    }

    public function orderList(): HasMany
    {
        return $this->hasMany(OrderList::class, 'no_user', 'no_user');
    }

    public function useSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'no_user')
            ->where('yn_cancel', 'N')->whereBetween(DB::raw('NOW()'), [DB::raw('start_date'), DB::raw('end_date')]);
    }

    public function subscription(): HasMany
    {
        return $this->hasMany(Subscription::class, 'no_user')->where('yn_cancel', 'N')->orderBy('no', 'desc');
    }

    public function memberParkingCoupon(): HasMany
    {
        return $this->hasMany(MemberParkingCoupon::class, 'no_user');
    }

    protected function getIsMasterAttribute(): bool
    {
        return $this->cd_mem_type == MemberType::MASTER_STAFF->value;
    }
}

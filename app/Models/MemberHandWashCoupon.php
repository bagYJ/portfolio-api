<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Class MemberHandWashCoupon
 * 
 * @property int $no
 * @property int $no_user
 * @property string $no_coupon
 * @property int $no_event
 * @property string $cd_mcp_status
 * @property Carbon|null $dt_reg
 * @property Carbon|null $dt_upt
 * @property string|null $no_order
 * @property string|null $ds_etc
 * @property string|null $id_admin
 * @property Carbon|null $dt_use_start
 * @property Carbon|null $dt_use_end
 *
 * @package App\Models
 */
class MemberHandWashCoupon extends Model
{
    protected $table = 'member_hand_wash_coupon';
    protected $primaryKey = 'no';
    public $timestamps = false;

    protected $casts = [
        'no_user' => 'int',
        'no_event' => 'int'
    ];

    protected $dates = [
        'dt_reg',
        'dt_upt',
        'dt_use_start',
        'dt_use_end'
    ];

    protected $fillable = [
        'no_user',
        'no_coupon',
        'no_event',
        'cd_mcp_status',
        'dt_reg',
        'dt_upt',
        'no_order',
        'ds_etc',
        'id_admin',
        'dt_use_start',
        'dt_use_end'
    ];

    public function couponEvent(): HasOne
    {
        return $this->hasOne(
            HandWashCouponEvent::class,
            'no_event',
            'no_event'
        );
    }

    public function couponEventProduct(): HasOneThrough
    {
        return $this->hasOneThrough(
            WashHandProduct::class,
            HandWashCouponEvent::class,
            'no_event',
            'no_product',
            'no_event',
            'at_discount'
        )->where('cd_disc_type', '=', '126300');
    }

    public function couponEventCondition(): HasMany
    {
        return $this->hasMany(HandWashCouponEventCondition::class, 'no_event', 'no_event');
    }

}

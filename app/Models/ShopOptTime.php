<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class ShopOptTime
 *
 * @property int $no_shop
 * @property int $nt_weekday
 * @property string|null $ds_open_time
 * @property string|null $ds_close_time
 * @property string|null $ds_open_order_time
 * @property string|null $ds_close_order_time
 * @property Carbon|null $dt_reg
 * @property Carbon|null $dt_upt
 * @property string|null $cd_break_time
 * @property string|null $ds_break_start_time
 * @property string|null $ds_break_end_time
 * @property string|null $cd_break_time2
 * @property string|null $ds_break_start_time2
 * @property string|null $ds_break_end_time2
 *
 * @package App\Models
 */
class ShopOptTime extends Model
{
    public $incrementing = false;
    public $timestamps = true;

    public const CREATED_AT = 'dt_reg';
    public const UPDATED_AT = 'dt_upt';
    public const DELETED_AT = null;

    protected $casts = [
        'no_shop' => 'int',
        'nt_weekday' => 'int'
    ];

    protected $dates = [
        'dt_reg',
        'dt_upt'
    ];

    protected $fillable = [
        'ds_open_time',
        'ds_close_time',
        'ds_open_order_time',
        'ds_close_order_time',
        'dt_reg',
        'dt_upt',
        'cd_break_time',
        'ds_break_start_time',
        'ds_break_end_time',
        'cd_break_time2',
        'ds_break_start_time2',
        'ds_break_end_time2'
    ];

    protected $appends = [
        'is_order',
        'is_order_available'
    ];

    public function getBreakTime()
    {
    }

    protected function getIsOrderAttribute(): bool
    {
        $openTime = parent::getAttributeValue('ds_open_order_time') ?? parent::getAttributeValue('ds_open_time');
        $closeTime = parent::getAttributeValue('ds_close_order_time') ?? parent::getAttributeValue('ds_close_time');
        if (empty($openTime) || empty($closeTime)) {
            return false;
        }

        return now()->between(Carbon::createFromFormat('Hi', $openTime)->format('Y-m-d H:i:s'), Carbon::createFromFormat('Hi', $closeTime)->format('Y-m-d H:i:s'));
    }

    protected function getIsOrderAvailableAttribute(): bool
    {
        $result1 = match (empty(parent::getAttribute('cd_break_time')) === false) {
            true => now()->between(Carbon::createFromFormat('Hi', parent::getAttribute('ds_break_start_time'))->format('Y-m-d H:i:s'), Carbon::createFromFormat('Hi', parent::getAttribute('ds_break_end_time'))->format('Y-m-d H:i:s')) === false,
            default => true
        };

        $result2 = match (empty(parent::getAttribute('cd_break_time2')) === false) {
            true => now()->between(Carbon::createFromFormat('Hi', parent::getAttribute('ds_break_start_time2'))->format('Y-m-d H:i:s'), Carbon::createFromFormat('Hi', parent::getAttribute('ds_break_end_time2'))->format('Y-m-d H:i:s')) === false,
            default => true
        };

        return $result1 && $result2;
    }
}

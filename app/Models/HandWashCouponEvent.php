<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class HandWashCouponEvent
 * 
 * @property int $no_event
 * @property string|null $nm_event
 * @property string|null $yn_condi_status_partner
 * @property string|null $yn_condi_status_shop
 * @property string|null $yn_condi_status_card
 * @property string|null $yn_condi_status_weekday
 * @property string|null $yn_condi_status_category
 * @property string|null $yn_condi_status_menu
 * @property string|null $yn_condi_status_money
 * @property string|null $cd_disc_type
 * @property float|null $at_discount
 * @property float|null $at_max_disc
 * @property Carbon|null $dt_start
 * @property Carbon|null $dt_expire
 * @property Carbon|null $dt_reg
 * @property string|null $cd_cpe_status
 * @property string|null $yn_cpn_list
 * @property int|null $at_limit_count
 * @property int|null $at_pub_count
 * @property string|null $yn_dupl_use
 * @property string|null $ds_etc
 * @property string|null $yn_is_calculated
 * @property string|null $cd_third_party
 * @property int|null $at_expire_day
 *
 * @package App\Models
 */
class HandWashCouponEvent extends Model
{
    protected $table = 'hand_wash_coupon_event';
    protected $primaryKey = 'no_event';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
		'no_event' => 'int',
		'at_discount' => 'float',
		'at_max_disc' => 'float',
		'at_limit_count' => 'int',
		'at_pub_count' => 'int',
		'at_expire_day' => 'int'
    ];

    protected $dates = [
		'dt_start',
		'dt_expire',
		'dt_reg'
    ];

    protected $fillable = [
		'nm_event',
		'yn_condi_status_partner',
		'yn_condi_status_shop',
		'yn_condi_status_card',
		'yn_condi_status_weekday',
		'yn_condi_status_category',
		'yn_condi_status_menu',
		'yn_condi_status_money',
		'cd_disc_type',
		'at_discount',
		'at_max_disc',
		'dt_start',
		'dt_expire',
		'dt_reg',
		'cd_cpe_status',
		'yn_cpn_list',
		'at_limit_count',
		'at_pub_count',
		'yn_dupl_use',
		'ds_etc',
		'yn_is_calculated',
		'cd_third_party',
		'at_expire_day'
    ];
}

<?php

declare(strict_types=1);

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class MemberAutoParkingHistory
 * 
 * @property int $no
 * @property int $no_user
 * @property string $ds_car_number
 * @property int|null $no_card
 * @property string|null $yn_use_auto_parking
 * @property Carbon|null $dt_reg
 *
 * @package App\Models
 */
class MemberAutoParkingHistory extends Model
{

    protected $table = 'member_auto_parking_history';
    protected $primaryKey = 'no';
    public $timestamps = false;

    public const CREATED_AT = 'dt_reg';

    protected $casts = [
        'no_user' => 'int',
        'no_card' => 'int'
    ];

	protected $dates = [
        'dt_reg'
    ];

    protected $fillable = [
        'no_user',
        'ds_car_number',
        'no_card',
        'yn_use_auto_parking',
        'dt_reg'
    ];
}

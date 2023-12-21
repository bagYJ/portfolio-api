<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class AlimtalkLog
 * 
 * @property int $no
 * @property string $ds_phone
 * @property string $cd_templates
 * @property string|null $ds_messageid
 * @property string $ds_request
 * @property string $ds_response
 * @property string|null $ds_result
 * @property Carbon|null $dt_reg
 * @property Carbon|null $dt_result
 *
 * @package App\Models
 */
class AlimtalkLog extends Model
{
    protected $primaryKey = 'no';
    public $timestamps = true;

    public const CREATED_AT = 'dt_reg';
    public const UPDATED_AT = 'dt_result';
    public const DELETED_AT = null;

    protected $dates = [
		'dt_reg',
		'dt_result'
    ];

    protected $fillable = [
		'ds_phone',
		'cd_templates',
		'ds_messageid',
		'ds_request',
		'ds_response',
		'ds_result',
		'dt_reg',
		'dt_result'
    ];
}

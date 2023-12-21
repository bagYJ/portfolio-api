<?php
declare(strict_types=1);

namespace App\Models;

class OrderAlarmEventLog extends Model
{
    protected $connection = 'mysql_log';
    protected $primaryKey = 'no';
    public $timestamps = true;

    public const CREATED_AT = 'dt_event_create';
    public const UPDATED_AT = null;
    public const DELETED_AT = null;

    protected $dates = [
        'dt_event_create'
    ];

    protected $fillable = [
        'cd_alarm_event_type',
        'no_order',
        'no_shop',
        'no_user',
        'no_device',
        'at_distance',
        'yn_gps_status',
        'at_rssi',
        'dt_pickup_time_chg',
        'no_reject_product_list',
        'ct_update'
    ];
}

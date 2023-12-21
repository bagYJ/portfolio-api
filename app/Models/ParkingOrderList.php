<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Awobaz\Compoships\Compoships;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class ParkingOrderList
 *
 * @property int $no
 * @property string $no_order
 * @property string $nm_order
 * @property int $no_user
 * @property string $ds_car_number
 * @property int $seq
 * @property int $no_site
 * @property string $id_site
 * @property int|null $no_parking_site
 * @property int|null $no_product
 * @property int|null $no_booking_uid
 * @property string|null $id_auto_parking
 * @property string|null $ds_parking_start_time
 * @property string|null $ds_parking_end_time
 * @property int|null $cd_ticket_type
 * @property int|null $cd_ticket_day_type
 * @property string|null $ds_user_parking_reserve_time
 * @property Carbon|null $dt_user_parking_used
 * @property Carbon|null $dt_user_parking_canceled
 * @property Carbon|null $dt_user_parking_expired
 * @property string|null $cd_parking_status
 * @property float|null $at_basic_price
 * @property int|null $at_basic_time
 * @property Carbon|null $dt_entry_time
 * @property Carbon|null $dt_exit_time
 * @property string $cd_service
 * @property string $cd_service_pay
 * @property string $cd_order_status
 * @property string $cd_pg
 * @property string $cd_payment
 * @property string $cd_payment_kind
 * @property string $cd_payment_method
 * @property string $cd_payment_status
 * @property int $no_card
 * @property string $cd_card_corp
 * @property int $no_card_user
 * @property float $at_price
 * @property float $at_price_pg
 * @property int $at_disct
 * @property int $at_cpn_disct
 * @property Carbon|null $dt_req
 * @property Carbon|null $dt_res
 * @property string $cd_pg_result
 * @property string|null $cd_pg_bill_result
 * @property string|null $ds_res_code
 * @property string|null $ds_res_msg
 * @property string|null $ds_res_order_no
 * @property string $ds_req_param
 * @property string|null $ds_res_param
 * @property Carbon|null $dt_req_refund
 * @property Carbon|null $dt_res_refund
 * @property string|null $ds_req_refund
 * @property string|null $ds_res_refund
 * @property string|null $ds_res_code_refund
 * @property string|null $cd_reject_reason
 * @property string|null $ds_server_reg
 * @property string|null $ds_pg_id
 * @property string|null $tid
 * @property int|null $product_num
 * @property string|null $cancel_id
 * @property string|null $cancel_pw
 * @property float $at_pg_commission_rate
 * @property string|null $cd_commission_type
 * @property float $at_commission_amount
 * @property float $at_commission_rate
 * @property float $at_sales_commission_rate
 * @property Carbon|null $dt_order_status
 * @property Carbon|null $dt_payment_status
 * @property Carbon|null $dt_booking
 * @property Carbon|null $dt_check_cancel
 * @property Carbon $dt_reg
 *
 * @package App\Models
 */
class ParkingOrderList extends Model
{
    use Compoships;

    protected $table = 'parking_order_list';
    protected $primaryKey = 'no_order';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'no' => 'int',
        'no_user' => 'int',
        'seq' => 'int',
        'no_site' => 'int',
        'no_parking_site' => 'int',
        'no_product' => 'int',
        'no_booking_uid' => 'int',
        'cd_ticket_type' => 'int',
        'cd_ticket_day_type' => 'int',
        'at_basic_price' => 'float',
        'at_basic_time' => 'int',
        'no_card' => 'int',
        'no_card_user' => 'int',
        'at_price' => 'float',
        'at_price_pg' => 'float',
        'at_disct' => 'int',
        'at_cpn_disct' => 'int',
        'product_num' => 'int',
        'at_pg_commission_rate' => 'float',
        'at_commission_amount' => 'float',
        'at_commission_rate' => 'float',
        'at_sales_commission_rate' => 'float'
    ];

    protected $dates = [
        'dt_user_parking_used',
        'dt_user_parking_canceled',
        'dt_user_parking_expired',
        'dt_entry_time',
        'dt_exit_time',
        'dt_req',
        'dt_res',
        'dt_reg',
        'dt_req_refund',
        'dt_res_refund',
        'dt_order_status',
        'dt_payment_status',
        'dt_booking',
        'dt_check_cancel'
    ];

    protected $fillable = [
        'no_order',
        'nm_order',
        'no_user',
        'ds_car_number',
        'seq',
        'no_site',
        'id_site',
        'no_parking_site',
        'no_product',
        'no_booking_uid',
        'id_auto_parking',
        'ds_parking_start_time',
        'ds_parking_end_time',
        'cd_ticket_type',
        'cd_ticket_day_type',
        'ds_user_parking_reserve_time',
        'dt_user_parking_used',
        'dt_user_parking_canceled',
        'dt_user_parking_expired',
        'cd_parking_status',
        'at_basic_price',
        'at_basic_time',
        'dt_entry_time',
        'dt_exit_time',
        'cd_service',
        'cd_service_pay',
        'cd_order_status',
        'cd_pg',
        'cd_payment',
        'cd_payment_kind',
        'cd_payment_method',
        'cd_payment_status',
        'no_card',
        'cd_card_corp',
        'no_card_user',
        'at_price',
        'at_price_pg',
        'at_disct',
        'at_cpn_disct',
        'dt_req',
        'dt_res',
        'cd_pg_result',
        'cd_pg_bill_result',
        'ds_res_code',
        'ds_res_msg',
        'ds_res_order_no',
        'ds_req_param',
        'ds_res_param',
        'dt_req_refund',
        'dt_res_refund',
        'ds_req_refund',
        'ds_res_refund',
        'ds_res_code_refund',
        'cd_reject_reason',
        'ds_server_reg',
        'ds_pg_id',
        'tid',
        'product_num',
        'cancel_id',
        'cancel_pw',
        'at_pg_commission_rate',
        'cd_commission_type',
        'at_commission_amount',
        'at_commission_rate',
        'at_sales_commission_rate',
        'dt_order_status',
        'dt_payment_status',
        'dt_booking',
        'dt_check_cancel',
        'dt_reg',
        'cd_third_party',
    ];

    public function parkingSite(): BelongsTo
    {
        return $this->belongsTo(ParkingSite::class, 'no_site', 'no_site');
    }

    public function autoParking(): BelongsTo
    {
        return $this->belongsTo(ParkingSite::class, 'id_auto_parking', 'id_auto_parking');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ParkingSiteTicket::class, ['id_site', 'no_product'], ['id_site', 'no_product']);
    }

    public function carInfo(): BelongsTo
    {
        return $this->belongsTo(MemberCarinfo::class, ['no_user','ds_car_number'], ['no_user','ds_car_number']);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'no_user', 'no_user');
    }

    public function card(): HasOne
    {
        return $this->hasOne(MemberCard::class, 'no_card', 'no_card');
    }
}

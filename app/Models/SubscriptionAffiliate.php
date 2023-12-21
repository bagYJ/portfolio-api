<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class SubscriptionAffiliate
 * 
 * @property int $no
 * @property string $affiliate_code
 * @property string $nm_company
 * @property int $subscription_date
 * @property Carbon $dt_reg
 * @property Carbon $dt_upt
 * @property Carbon|null $dt_del
 *
 * @package App\Models
 */
class SubscriptionAffiliate extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'no';
    public $timestamps = true;
    public const CREATED_AT = 'dt_reg';
    public const UPDATED_AT = 'dt_upt';
    public const DELETED_AT = 'dt_del';

    protected $dates = [
        'dt_reg',
        'dt_upt',
        'dt_del'
    ];

    protected $fillable = [
        'affiliate_code',
        'nm_company',
        'dt_reg',
        'dt_upt',
        'dt_del'
    ];
}

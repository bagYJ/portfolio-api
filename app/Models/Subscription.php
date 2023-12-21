<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Casts\Json;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class Subscription
 * 
 * @property int $no
 * @property string $no_order
 * @property int $no_subscription_product
 * @property int $no_subscription_payment
 * @property int|null $no_subscription_issue
 * @property string $expression_no
 * @property int $no_user
 * @property string $affiliate_code
 * @property array $benefit
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $used_id_pointcard
 * @property int|null $next_no_subscription_product
 * @property string $yn_cancel
 * @property Carbon|null $dt_change
 * @property Carbon|null $dt_cancel
 * @property Carbon $dt_reg
 * @property Carbon $dt_upt
 * @property SubscriptionPayment $subscriptionPayment
 * @property SubscriptionAffiliate $subscriptionAffiliate
 * @property User $user
 *
 * @package App\Models
 */
class Subscription extends Model
{
    protected $primaryKey = 'no';
    public $timestamps = false;

    protected $casts = [
        'no_subscription_product' => 'int',
        'no_subscription_payment' => 'int',
        'no_subscription_issue' => 'int',
        'no_user' => 'int',
        'next_no_subscription_product' => 'int',
        'benefit' => Json::class
    ];

    protected $dates = [
		'start_date',
		'end_date',
		'dt_change',
		'dt_cancel',
		'dt_reg',
		'dt_upt'
    ];

    protected $fillable = [
        'no_order',
		'no_subscription_product',
		'no_subscription_payment',
		'no_subscription_issue',
		'expression_no',
		'no_user',
		'affiliate_code',
        'benefit',
		'start_date',
		'end_date',
        'used_id_pointcard',
		'next_no_subscription_product',
        'yn_cancel',
		'dt_change',
		'dt_cancel',
		'dt_reg',
		'dt_upt'
    ];

//    protected $with = ['subscriptionPayment'];

    public function subscriptionPayment(): HasOne
    {
        return $this->hasOne(SubscriptionPayment::class, 'no_order', 'no_order');
    }

    public function subscriptionAffiliate(): BelongsTo
    {
        return $this->belongsTo(SubscriptionAffiliate::class, 'affiliate_code', 'affiliate_code');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'no_user', 'no_user');
    }

}

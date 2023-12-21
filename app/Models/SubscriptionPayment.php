<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Casts\Json;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class SubscriptionPayment
 * 
 * @property int $no
 * @property string $no_order
 * @property int $no_user
 * @property string $tid
 * @property int $amount
 * @property array $product
 * @property array $card
 * @property array|null $ds_req_param
 * @property array|null $ds_res_param
 * @property Carbon $dt_reg
 * @property Subscription $subscription
 *
 * @package App\Models
 */
class SubscriptionPayment extends Model
{
    protected $primaryKey = 'no';
    public $timestamps = false;

    protected $casts = [
		'no_user' => 'int',
		'amount' => 'int',
		'product' => Json::class,
        'card' => Json::class,
		'ds_req_param' => Json::class,
		'ds_res_param' => Json::class
    ];

    protected $dates = [
		'dt_reg'
    ];

    protected $fillable = [
		'no_order',
		'no_user',
		'tid',
		'amount',
		'product',
        'card',
		'ds_req_param',
		'ds_res_param',
		'dt_reg'
    ];

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'no_subscription_payment');
    }
}

<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Casts\Json;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class SubscriptionIssue
 *
 * @property int $no
 * @property int $no_subscription_product
 * @property int $no_subscription_affiliate
 * @property string $expression_no
 * @property string $yn_use
 * @property Carbon $dt_reg
 * @property Carbon|null $dt_upt
 * @property SubscriptionAffiliate $subscriptionAffiliate
 *
 * @package App\Models
 */
class SubscriptionIssue extends Model
{
    protected $primaryKey = 'no';
    public $timestamps = true;
    public const CREATED_AT = 'dt_reg';
    public const UPDATED_AT = 'dt_upt';
    public const DELETED_AT = null;

    protected $casts = [
		'no_subscription_product' => 'int',
		'no_subscription_affiliate' => 'int'
    ];

    protected $dates = [
		'dt_reg',
		'dt_upt'
    ];

    protected $fillable = [
		'no_subscription_product',
		'no_subscription_affiliate',
		'expression_no',
		'yn_use',
		'dt_reg',
		'dt_upt'
    ];

    public function subscriptionAffiliate(): BelongsTo
    {
        return $this->belongsTo(SubscriptionAffiliate::class, 'no_subscription_affiliate', 'no');
    }
}

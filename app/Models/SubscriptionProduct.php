<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Casts\Json;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class SubscriptionProduct
 * 
 * @property int $no
 * @property string $product_code
 * @property string $title
 * @property string $content
 * @property string $tag
 * @property string|null $list_image_url
 * @property string|null $detail_image_url
 * @property array $benefit
 * @property array $benefit_text
 * @property int $amount
 * @property string|null $ds_sale_code
 * @property string $yn_visible
 * @property Carbon|null $dt_reg
 * @property Carbon|null $dt_upt
 * @property Carbon|null $dt_del
 *
 * @package App\Models
 */
class SubscriptionProduct extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'no';
    public $timestamps = true;

    public const CREATED_AT = 'dt_reg';
    public const UPDATED_AT = 'dt_upt';
    public const DELETED_AT = 'dt_del';

    protected $casts = [
		'benefit' => Json::class,
        'benefit_text' => Json::class,
        'tag' => Json::class
    ];

    protected $dates = [
		'dt_reg',
		'dt_upt',
        'dt_del'
    ];

    protected $fillable = [
		'product_code',
		'title',
		'content',
		'list_image_url',
		'detail_image_url',
		'benefit',
        'benefit_text',
        'amount',
		'yn_use',
		'dt_reg',
		'dt_upt'
    ];
}

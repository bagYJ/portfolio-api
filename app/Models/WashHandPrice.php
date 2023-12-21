<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class WashHandPrice
 * 
 * @property int $no
 * @property int $no_product
 * @property string|null $cd_wash_carnpeople
 * @property float|null $at_price
 * @property string|null $yn_status
 * @property string|null $id_upt
 * @property Carbon|null $dt_upt
 * @property string|null $id_reg
 * @property Carbon|null $dt_reg
 *
 * @package App\Models
 */
class WashHandPrice extends Model
{
    protected $table = 'wash_hand_price';
    protected $primaryKey = 'no';
    public $timestamps = false;

    protected $casts = [
		'no_product' => 'int',
		'at_price' => 'float'
    ];

    protected $dates = [
		'dt_upt',
		'dt_reg'
    ];

    protected $fillable = [
		'no_product',
		'cd_wash_carnpeople',
		'at_price',
		'yn_status',
		'id_upt',
		'dt_upt',
		'id_reg',
		'dt_reg'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(WashHandProduct::class, 'no_product', 'no_product');
    }
}

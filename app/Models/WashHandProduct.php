<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class WashHandProduct
 * 
 * @property int $no
 * @property int $no_product
 * @property string|null $cd_biz_kind_detail
 * @property int $no_partner
 * @property string|null $nm_product
 * @property string|null $yn_status
 * @property string|null $id_upt
 * @property Carbon|null $dt_upt
 * @property string|null $id_reg
 * @property Carbon|null $dt_reg
 *
 * @package App\Models
 */
class WashHandProduct extends Model
{
    protected $table = 'wash_hand_product';
    protected $primaryKey = 'no';
    public $timestamps = false;

    protected $casts = [
		'no_product' => 'int',
		'no_shop' => 'int',
		'no_partner' => 'int'
    ];

    protected $dates = [
		'dt_upt',
		'dt_reg'
    ];

    protected $fillable = [
		'no_product',
		'cd_biz_kind_detail',
		'no_partner',
		'no_shop',
		'nm_product',
		'yn_status',
		'id_upt',
		'dt_upt',
		'id_reg',
		'dt_reg'
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'no_partner', 'no_partner');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(WashHandPrice::class, 'no_product', 'no_product')->where('yn_status', 'Y');
    }
}

<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Awobaz\Compoships\Compoships;
use Carbon\Carbon;

/**
 * Class ShopOilUnit
 *
 * @property int $no_shop
 * @property string $ds_display_ark_id
 * @property string $ds_unit_id
 * @property string|null $cd_oil_marker
 * @property Carbon|null $dt_reg
 * @property Carbon|null $dt_upt
 * @property int|null $nt_list_order
 *
 * @package App\Models
 */
class ShopOilUnit extends Model
{
    use Compoships;

    public $incrementing = false;
    public $timestamps = true;

    public const CREATED_AT = 'dt_reg';
    public const UPDATED_AT = 'dt_upt';
    public const DELETED_AT = null;

    protected $casts = [
        'no_shop' => 'int',
        'nt_list_order' => 'int'
    ];

    protected $dates = [
        'dt_reg',
        'dt_upt'
    ];

    protected $fillable = [
        'cd_oil_marker',
        'dt_reg',
        'dt_upt',
        'nt_list_order'
    ];
}

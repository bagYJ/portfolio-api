<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;

/**
 * Class ShopProductPrice
 * 
 * @property int $no
 * @property int $no_shop
 * @property int $no_product
 * @property float|null $at_price
 * @property Carbon $dt_reg
 * @property Carbon $dt_upt
 *
 * @package App\Models
 */
class ShopProductPrice extends Model
{
	protected $primaryKey = 'no';
	public $timestamps = false;

	protected $casts = [
		'no_shop' => 'int',
		'no_product' => 'int',
		'at_price' => 'float'
	];

	protected $dates = [
		'dt_reg',
		'dt_upt'
	];

	protected $fillable = [
		'no_shop',
		'no_product',
		'at_price',
		'dt_reg',
		'dt_upt'
	];
}

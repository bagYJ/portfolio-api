<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class BbsTerm
 *
 * @property int $no
 * @property string|null $terms_category
 * @property string|null $nm_terms_category
 * @property int|null $no_version
 * @property string|null $ds_content
 * @property string|null $yn_show
 * @property string|null $id_upt
 * @property Carbon|null $dt_upt
 * @property string|null $id_del
 * @property Carbon|null $dt_del
 * @property string|null $id_reg
 * @property Carbon|null $dt_reg
 *
 * @package App\Models
 */
class BbsTerms extends Model
{
	protected $table = 'bbs_terms';
	protected $primaryKey = 'no';
	public $timestamps = false;

	protected $casts = [
		'no_version' => 'int',
		'dt_upt' => 'date',
		'dt_del' => 'date',
		'dt_reg' => 'date'
	];

	protected $fillable = [
		'terms_category',
		'nm_terms_category',
		'no_version',
		'ds_content',
		'yn_show',
		'id_upt',
		'dt_upt',
		'id_del',
		'dt_del',
		'id_reg',
		'dt_reg'
	];
}

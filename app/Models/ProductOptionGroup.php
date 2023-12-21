<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Class ProductOptionGroup
 *
 * @property int $no
 * @property int $no_group
 * @property string|null $nm_group
 * @property int|null $no_partner
 * @property int|null $ct_order
 * @property string|null $id_upt
 * @property Carbon|null $dt_upt
 * @property string|null $id_del
 * @property Carbon|null $dt_del
 * @property string|null $id_reg
 * @property Carbon|null $dt_reg
 * @property int|null $min_option_select
 * @property int|null $max_option_select
 * @property string $yn_cup_deposit
 *
 * @property Collection $productOptions
 *
 * @package App\Models
 */
class ProductOptionGroup extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'no_group';
    public $incrementing = false;
    public $timestamps = true;

    public const CREATED_AT = 'dt_reg';
    public const UPDATED_AT = 'dt_upt';
    public const DELETED_AT = 'dt_del';

    protected $appends = [
        'yn_cup_deposit'
    ];

    protected $casts = [
        'no' => 'int',
        'no_group' => 'int',
        'no_partner' => 'int',
        'ct_order' => 'int'
    ];

    protected $dates = [
        'dt_upt',
        'dt_del',
        'dt_reg'
    ];

    protected $fillable = [
        'no',
        'nm_group',
        'no_partner',
        'ct_order',
        'id_upt',
        'dt_upt',
        'id_del',
        'dt_del',
        'id_reg',
        'dt_reg'
    ];

    public function productOptions(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'no_group', 'no_group')
            ->where('cd_option_status', '=', '613100')
            ->orderBy('ct_order');
    }

    protected function getYnCupDepositAttribute(): string
    {
        return ProductOption::where('no_group', $this->no_group)
            ->where('cd_option_status', '=', '613100')
            ->get()
            ->filter(function ($option) {
                return $option->yn_cup_deposit == 'Y';
            })
            ->count() ? 'Y' : 'N';
    }
}

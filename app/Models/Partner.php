<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Utils\Common;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Partner
 *
 * @property int $no
 * @property int $no_partner
 * @property string|null $nm_partner
 * @property string|null $cd_biz_kind
 * @property string|null $cd_biz_kind_detail
 * @property string|null $cd_sale_kind
 * @property string|null $ds_bi
 * @property string|null $ds_pin
 * @property string|null $ds_info_bg
 * @property string|null $yn_status
 * @property Carbon|null $dt_reg
 * @property string|null $cd_service
 * @property string|null $ds_address
 * @property string|null $ds_partner_admin
 * @property string|null $ds_tel
 * @property string|null $ds_email
 * @property string|null $ds_image_bg
 * @property string|null $ds_image_ci
 * @property Carbon|null $dt_contract_start
 * @property Carbon|null $dt_contract_end
 * @property string|null $yn_auto_extend
 * @property string|null $ds_contract_url
 * @property string|null $cd_commission_type
 * @property float|null $at_commission_amount
 * @property float|null $at_commission_rate
 * @property float|null $at_comm_rate_general
 * @property int|null $ct_commission_sales
 * @property Carbon|null $dt_upt
 * @property int|null $no_company
 * @property int|null $at_make_ready_time
 * @property string|null $ds_menu_origin
 * @property int|null $no_sales_agency
 * @property float|null $at_sales_commission_rate
 * @property string|null $cd_pg
 * @property string|null $ds_pg_id
 * @property float|null $at_pg_commission_rate
 * @property string|null $cd_bank
 * @property string|null $ds_bank_acct
 * @property string|null $nm_acct_name
 * @property string|null $cd_contract_status
 * @property string|null $ds_biz_num
 * @property string|null $cd_calculate_main
 * @property string|null $ds_calc_email
 * @property Carbon|null $dt_calc_email_upt
 * @property string|null $id_admin
 * @property string|null $ds_external_company
 * @property string|null $tags
 * @property string|null $tag
 * @property string|null $cd_spc_brand
 * @property string|null $id_del
 * @property Carbon|null $dt_del
 *
 * @package App\Models
 */
class Partner extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'no_partner';
    public $incrementing = false;
    public $timestamps = true;

    public const CREATED_AT = 'dt_reg';
    public const UPDATED_AT = 'dt_upt';
    public const DELETED_AT = 'dt_del';

    protected $casts = [
        'no' => 'int',
        'no_partner' => 'int',
        'at_commission_amount' => 'float',
        'at_commission_rate' => 'float',
        'at_comm_rate_general' => 'float',
        'ct_commission_sales' => 'int',
        'no_company' => 'int',
        'at_make_ready_time' => 'int',
        'no_sales_agency' => 'int',
        'at_sales_commission_rate' => 'float',
        'at_pg_commission_rate' => 'float'
    ];

    protected $dates = [
        'dt_reg',
        'dt_contract_start',
        'dt_contract_end',
        'dt_upt',
        'dt_del',
        'dt_calc_email_upt'
    ];

    protected $fillable = [
        'no',
        'nm_partner',
        'cd_biz_kind',
        'cd_biz_kind_detail',
        'cd_sale_kind',
        'ds_bi',
        'ds_pin',
        'ds_info_bg',
        'yn_status',
        'dt_reg',
        'cd_service',
        'ds_address',
        'ds_partner_admin',
        'ds_tel',
        'ds_email',
        'ds_image_bg',
        'ds_image_ci',
        'dt_contract_start',
        'dt_contract_end',
        'yn_auto_extend',
        'ds_contract_url',
        'cd_commission_type',
        'at_commission_amount',
        'at_commission_rate',
        'at_comm_rate_general',
        'ct_commission_sales',
        'dt_upt',
        'no_company',
        'at_make_ready_time',
        'ds_menu_origin',
        'no_sales_agency',
        'at_sales_commission_rate',
        'cd_pg',
        'ds_pg_id',
        'at_pg_commission_rate',
        'cd_bank',
        'ds_bank_acct',
        'nm_acct_name',
        'cd_contract_status',
        'ds_biz_num',
        'cd_calculate_main',
        'ds_calc_email',
        'dt_calc_email_upt',
        'id_admin',
        'ds_external_company',
        'cd_spc_brand'
    ];

    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class, 'no_partner', 'no_partner');
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class, 'no_partner', 'no_partner');
    }

    public function productOptionGroups(): HasMany
    {
        return $this->hasMany(ProductOptionGroup::class, 'no_partner');
    }

    protected function dsBi(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Common::getImagePath($value)
        );
    }

    protected function dsPin(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Common::getImagePath($value)
        );
    }

    protected function dsInfoBg(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Common::getImagePath($value)
        );
    }

    protected function dsImageBg(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Common::getImagePath($value)
        );
    }

}

<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use App\Utils\Common;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ParkingSiteImage
 *
 * @property int $no_parking_site
 * @property string $id_site
 * @property string $ds_image_url
 *
 * @property ParkingSite $parking_site
 *
 * @package App\Models
 */
class ParkingSiteImage extends Model
{
    protected $table = 'parking_site_image';

    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'no_parking_site' => 'int'
    ];

    protected $fillable = [
        'id_site',
        'image_no',
        'ds_image_url'
    ];

    protected function dsImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Common::getImagePath($value)
        );
    }

    public function parkingSite(): BelongsTo
    {
        return $this->belongsTo(ParkingSite::class, 'no_parking_site', 'no_parking_site');
    }
}

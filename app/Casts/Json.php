<?php
declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Json implements CastsAttributes
{

    public function get($model, string $key, $value, array $attributes)
    {
        return json_decode($value ?? '{}');
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return match (gettype($value)) {
            'string' => $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE)
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airport extends Model
{
    protected $guarded = [];

    protected $casts = ['is_origin' => 'bool', 'is_destination' => 'bool'];

    public static function byCode(string $iata): ?self
    {
        static $cache = [];
        $iata = strtoupper($iata);

        return $cache[$iata] ??= static::where('iata', $iata)->first();
    }
}

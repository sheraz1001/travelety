<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Subscriber extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'bool', 'last_notified_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (self $s) => $s->token ??= Str::random(48));
    }
}

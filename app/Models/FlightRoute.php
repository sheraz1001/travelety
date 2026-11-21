<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FlightRoute extends Model
{
    protected $guarded = [];

    protected $casts = [
        'faqs' => 'array', 'fare_data' => 'array', 'is_direct' => 'bool',
        'is_published' => 'bool', 'is_indexable' => 'bool',
        'fares_updated_at' => 'datetime', 'content_generated_at' => 'datetime',
    ];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true);
    }

    public function origin(): ?Airport
    {
        return Airport::byCode($this->origin_iata);
    }

    public function destination(): ?Airport
    {
        return Airport::byCode($this->destination_iata);
    }

    public function destinationGuide(): ?Destination
    {
        return Destination::published()->where('iata', $this->destination_iata)->first();
    }

    public static function makeSlug(Airport $from, Airport $to): string
    {
        return Str::slug($from->city).'-to-'.Str::slug($to->city).($from->city === $to->city ? '-'.strtolower($to->iata) : '');
    }

    public function title(): string
    {
        return 'Flights from '.($this->origin()?->city ?? $this->origin_iata).' to '.($this->destination()?->city ?? $this->destination_iata);
    }

    public function url(): string
    {
        return route('flights.show', $this->slug);
    }
}

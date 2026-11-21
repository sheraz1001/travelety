<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Destination extends Model
{
    protected $guarded = [];

    protected $casts = [
        'areas' => 'array', 'things_to_do' => 'array', 'weather' => 'array', 'faqs' => 'array',
        'is_published' => 'bool', 'is_indexable' => 'bool', 'is_featured' => 'bool',
        'content_generated_at' => 'datetime',
    ];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function airport(): ?Airport
    {
        return $this->iata ? Airport::byCode($this->iata) : null;
    }

    public function routesTo()
    {
        return FlightRoute::published()->where('destination_iata', $this->iata)->orderBy('priority')->get();
    }

    public function hotelsUrl(): string
    {
        return route('hotels.show', $this->slug);
    }

    public function guideUrl(): string
    {
        return route('travel.show', $this->slug);
    }
}

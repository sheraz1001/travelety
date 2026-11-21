<?php

namespace App\Models;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EsimPlan extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'bool', 'top_ups' => 'bool', 'price_usd' => 'float', 'data_gb' => 'float'];

    public const USD_TO_AED = 3.6725; // AED is pegged to USD

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function isUnlimited(): bool
    {
        return $this->data_gb === null;
    }

    public function priceAed(): string
    {
        return 'AED '.number_format($this->price_usd * self::USD_TO_AED, 0);
    }

    public function priceUsd(): string
    {
        return 'US$'.number_format($this->price_usd, 2);
    }

    public function pricePerGb(): ?float
    {
        return $this->data_gb ? round($this->price_usd / $this->data_gb, 2) : null;
    }

    /** Partner link built server-side; visitors only ever see /go/esim/{id}. */
    public function affiliateUrl(): string
    {
        return 'https://tp.media/r?'.http_build_query([
            'marker' => Setting::get('affiliate_marker') ?: '37131',
            'trs' => Setting::get('tp_project_id') ?: '604827',
            'p' => '8310',
            'u' => $this->link,
            'campaign_id' => '541',
        ]);
    }

    public function url(): string
    {
        return route('esim.show', $this->slug);
    }
}

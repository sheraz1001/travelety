<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $guarded = [];

    public const DEFAULTS = [
        'site_name' => 'Travelety',
        'tagline' => 'Compare flights, hotels and everything for your trip',
        'search_domain' => 'https://search.travelety.com',
        'currency' => 'aed',
        'locale' => 'en',
        'default_origin' => 'DXB',
        'contact_email' => 'hello@travelety.com',
        'link_hotels' => '',
        'link_transfers' => 'https://kiwitaxi.tpk.lv/ee7yCzGJ',
        'link_tours' => 'https://klook.tpk.lv/3VhSdTEY',
        'link_esim' => 'https://airalo.tpk.lv/oHiZGKFY',
        'link_cars' => 'https://localrent.tpk.lv/zXe2IPmN',
        'link_insurance' => '',
        'link_compensation' => '',
        'blog_auto_publish' => '0',
        'blog_posts_per_day' => '1',
        'blog_min_words' => '1200',
        'routes_content_per_run' => '25',
        'fares_api_token' => '',
        'affiliate_marker' => '',
        'ai_provider' => 'anthropic',
        'ai_model' => 'claude-sonnet-5-5',
        'ai_api_key' => '',
        'google_analytics_id' => '',
        'google_site_verification' => '',
        'social_facebook' => '',
        'social_instagram' => '',
        'social_x' => '',
    ];

    public static function get(string $key, $default = null)
    {
        $all = Cache::rememberForever('settings.all', fn () => static::pluck('value', 'key')->all());

        $value = $all[$key] ?? null;

        return ($value === null || $value === '') ? ($default ?? static::DEFAULTS[$key] ?? null) : $value;
    }

    public static function put(array $values): void
    {
        foreach ($values as $k => $v) {
            static::updateOrCreate(['key' => $k], ['value' => is_bool($v) ? (int) $v : $v]);
        }
        Cache::forget('settings.all');
    }
}

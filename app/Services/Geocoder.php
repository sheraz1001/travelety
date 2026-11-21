<?php

namespace App\Services;

use App\Models\Airport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** OpenStreetMap (Nominatim) geocoding, cached for 30 days. */
class Geocoder
{
    public function search(string $q, ?string $country = null): ?array
    {
        $q = trim($q);
        if ($q === '') {
            return null;
        }
        $key = 'geo.'.md5(strtolower($q).'|'.$country);

        return Cache::remember($key, now()->addDays(30), function () use ($q, $country) {
            try {
                $res = Http::timeout(12)->withHeaders(['User-Agent' => 'Travelety/1.0 (hello@travelety.com)'])
                    ->get('https://nominatim.openstreetmap.org/search', array_filter([
                        'q' => $q, 'format' => 'jsonv2', 'limit' => 1, 'addressdetails' => 0,
                        'countrycodes' => $country ? strtolower($country) : null,
                    ]));
                $hit = $res->json()[0] ?? null;

                return $hit ? ['lat' => (float) $hit['lat'], 'lng' => (float) $hit['lon'], 'name' => $hit['display_name']] : null;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    public function airport(Airport $a): ?array
    {
        if ($a->lat && $a->lng) {
            return ['lat' => (float) $a->lat, 'lng' => (float) $a->lng, 'name' => $a->name];
        }
        $hit = $this->search($a->name.' airport '.$a->city, $a->country_code) ?? $this->search($a->iata.' airport', $a->country_code);
        if ($hit) {
            $a->update(['lat' => $hit['lat'], 'lng' => $hit['lng']]);
        }

        return $hit;
    }
}

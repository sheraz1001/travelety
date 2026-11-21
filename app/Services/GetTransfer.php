<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GetTransfer partner API: live transfer prices (route_info).
 * Needs a partner token from Travelpayouts support (Admin → Site settings).
 * Bookings go through the GetTransfer affiliate link, so payment and support stay with GetTransfer.
 */
class GetTransfer
{
    public function enabled(): bool
    {
        return (bool) Setting::get('gettransfer_token');
    }

    /** @return array{prices: array<int, array{type:string,label:string,price:float,currency:string,seats:?int}>, distance:?float, duration:?int}|null */
    public function prices(array $from, array $to, string $dateTime, int $pax = 1, string $currency = 'USD'): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        $key = 'gt.'.md5(json_encode([$from['lat'], $from['lng'], $to['lat'], $to['lng'], substr($dateTime, 0, 10), $pax, $currency]));

        return Cache::remember($key, now()->addHours(6), function () use ($from, $to, $dateTime, $pax, $currency) {
            try {
                $base = rtrim(Setting::get('gettransfer_api_base') ?: 'https://gtrbox.org', '/');
                $query = http_build_query([
                    'with_prices' => 'true', 'date_to' => $dateTime, 'pax' => $pax,
                    'currency' => $currency, 'distance_unit' => 'km',
                ]).'&points[]='.rawurlencode($from['lat'].','.$from['lng']).'&points[]='.rawurlencode($to['lat'].','.$to['lng']);
                $res = Http::timeout(20)->withHeaders(['X-ACCESS-TOKEN' => Setting::get('gettransfer_token'), 'Accept' => 'application/json'])
                    ->get($base.'/api/route_info?'.$query);
                if (! $res->successful()) {
                    Log::warning('GetTransfer route_info failed', ['status' => $res->status(), 'body' => substr($res->body(), 0, 300)]);

                    return null;
                }

                return $this->parse($res->json() ?? [], $currency);
            } catch (\Throwable $e) {
                Log::warning('GetTransfer error: '.$e->getMessage());

                return null;
            }
        });
    }

    /** Tolerant parser: finds the "prices" map and reads each vehicle's book_now price. */
    protected function parse(array $json, string $currency): array
    {
        $data = $json['data'] ?? $json;
        $prices = $data['prices'] ?? ($data['route_info']['prices'] ?? []);
        $labels = [
            'economy' => 'Economy sedan', 'comfort' => 'Comfort sedan', 'business' => 'Business class', 'premium' => 'Premium',
            'limousine' => 'Limousine', 'suv' => 'SUV', 'van' => 'Minivan (up to 7)', 'minibus' => 'Minibus (up to 16)',
            'bus' => 'Coach', 'helicopter' => 'Helicopter',
        ];
        $seats = ['economy' => 3, 'comfort' => 3, 'business' => 3, 'premium' => 3, 'suv' => 4, 'van' => 7, 'minibus' => 16, 'bus' => 40];
        $out = [];
        foreach ((array) $prices as $type => $p) {
            $type = is_string($type) ? $type : ($p['transport_type_id'] ?? $p['type'] ?? 'car');
            $book = $p['book_now'] ?? null;
            if (is_array($book)) {
                $book = $book['amount'] ?? $book['price'] ?? $book['value'] ?? null;
            }
            if ($book === null || ! is_numeric(preg_replace('/[^\d.]/', '', (string) $book))) {
                continue;
            }
            $out[] = [
                'type' => $type,
                'label' => $labels[$type] ?? ucfirst(str_replace('_', ' ', $type)),
                'price' => (float) preg_replace('/[^\d.]/', '', (string) $book),
                'currency' => $currency,
                'seats' => $seats[$type] ?? null,
            ];
        }
        usort($out, fn ($a, $b) => $a['price'] <=> $b['price']);

        return [
            'prices' => $out,
            'distance' => isset($data['distance']) ? (float) $data['distance'] : null,
            'duration' => isset($data['duration']) ? (int) $data['duration'] : null,
        ];
    }
}

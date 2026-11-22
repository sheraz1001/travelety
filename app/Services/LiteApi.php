<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Nuitee LiteAPI (hotels). Search and content on api.liteapi.travel, prebook/book on book.liteapi.travel.
 * Payment: Nuitee payment SDK (TRANSACTION_ID) — guest pays in-page, Nuitee settles, markup set in the Nuitee dashboard.
 */
class LiteApi
{
    public const API = 'https://api.liteapi.travel/v3.0';

    public const BOOK = 'https://book.liteapi.travel/v3.0';

    public function mode(): string
    {
        $mode = Setting::get('liteapi_mode') === 'live' ? 'live' : 'sandbox';
        if ($mode === 'live' && ! Setting::get('liteapi_live_key')) {
            $mode = 'sandbox';
        }

        return $mode;
    }

    public function key(): ?string
    {
        return $this->mode() === 'live' ? Setting::get('liteapi_live_key') : Setting::get('liteapi_sandbox_key');
    }

    public function enabled(): bool
    {
        return (bool) $this->key();
    }

    protected function http(int $timeout = 25)
    {
        return Http::timeout($timeout)->acceptJson()->withHeaders(['X-API-Key' => (string) $this->key()]);
    }

    protected function call(string $method, string $url, array $data = [], int $timeout = 25): ?array
    {
        if (! $this->enabled()) {
            return null;
        }
        try {
            $res = $method === 'GET' ? $this->http($timeout)->get($url, $data) : $this->http($timeout)->post($url, $data);
            $json = $res->json();
            if (! $res->successful()) {
                Log::warning('LiteAPI '.$method.' '.parse_url($url, PHP_URL_PATH).' failed', ['status' => $res->status(), 'body' => substr($res->body(), 0, 500)]);

                return ['_error' => $json['error']['message'] ?? ($json['error']['description'] ?? ($json['message'] ?? 'The hotel service returned an error.')), '_status' => $res->status(), '_json' => $json];
            }

            return is_array($json) ? $json : null;
        } catch (\Throwable $e) {
            Log::warning('LiteAPI error: '.$e->getMessage());

            return ['_error' => 'The hotel service did not respond. Please try again.', '_status' => 0];
        }
    }

    /** Places autocomplete (billable ~$0.01/request) — cached for a week per query. */
    public function places(string $q): array
    {
        $q = trim(mb_strtolower($q));
        if (mb_strlen($q) < 2 || ! $this->enabled()) {
            return [];
        }

        return Cache::remember('lite.places.v2.'.md5($q), now()->addDays(7), function () use ($q) {
            $r = $this->call('GET', self::API.'/data/places', ['textQuery' => $q, 'language' => 'en'], 10);
            $good = ['locality', 'political', 'country', 'administrative_area_level_1', 'administrative_area_level_2', 'sublocality', 'neighborhood', 'colloquial_area',
                'airport', 'international_airport', 'lodging', 'hotel', 'resort_hotel', 'tourist_attraction', 'natural_feature', 'park', 'beach', 'island', 'point_of_interest'];
            $bad = ['store', 'doctor', 'health', 'finance', 'lawyer', 'restaurant', 'food', 'cafe', 'bakery', 'car_repair', 'real_estate_agency', 'school', 'church', 'storage', 'insurance_agency', 'service', 'shopping_mall', 'clothing_store', 'local_government_office'];

            return collect($r['data'] ?? [])->filter(function ($p) use ($good, $bad) {
                $t = (array) ($p['types'] ?? []);

                return ! $t || (array_intersect($t, $good) && ! array_intersect($t, $bad)) || in_array('lodging', $t) || in_array('airport', $t);
            })->take(8)->map(fn ($p) => [
                'placeId' => $p['placeId'] ?? null,
                'name' => $p['displayName'] ?? ($p['name'] ?? ''),
                'address' => $p['formattedAddress'] ?? ($p['address'] ?? ''),
                'type' => is_array($p['types'] ?? null) ? ($p['types'][0] ?? '') : ($p['type'] ?? ''),
            ])->filter(fn ($p) => $p['placeId'])->values()->all();
        });
    }

    /**
     * Search hotels with rates. $where = ['placeId'=>..] or ['cityName'=>..,'countryCode'=>..] or ['hotelIds'=>[...]].
     * Returns ['hotels'=>[...normalised...], 'error'=>?string].
     */
    public function search(array $where, string $checkin, string $checkout, array $occupancies, string $currency, string $nationality, int $limit = 60): array
    {
        $body = array_filter($where) + [
            'checkin' => $checkin, 'checkout' => $checkout, 'currency' => $currency,
            'guestNationality' => $nationality, 'occupancies' => $occupancies,
            'limit' => $limit, 'timeout' => 12, 'maxRatesPerHotel' => 1, 'includeHotelData' => true,
        ];
        $key = 'lite.search.v1.'.$this->mode().'.'.md5(json_encode($body));
        $r = Cache::get($key);
        if (! $r) {
            $r = $this->call('POST', self::API.'/hotels/rates', $body, 30);
            if ($r && ! isset($r['_error'])) {
                Cache::put($key, $r, now()->addMinutes(10));
            }
        }
        if (! $r || isset($r['_error'])) {
            return ['hotels' => [], 'error' => $r['_error'] ?? null];
        }

        $content = collect($r['hotels'] ?? [])->keyBy(fn ($h) => $h['id'] ?? ($h['hotelId'] ?? ''));
        $missing = collect($r['data'] ?? [])->pluck('hotelId')->filter(fn ($id) => ! $content->has($id))->values();
        if ($missing->isNotEmpty()) {
            $content = $content->merge($this->hotelsContent($missing->all()));
        }

        $hotels = collect($r['data'] ?? [])->map(function ($h) use ($content) {
            $id = $h['hotelId'] ?? null;
            $c = (array) ($content[$id] ?? []);
            $best = null;
            foreach ($h['roomTypes'] ?? [] as $rt) {
                $amount = $rt['offerRetailRate']['amount'] ?? ($rt['rates'][0]['retailRate']['total'][0]['amount'] ?? null);
                if ($amount !== null && ($best === null || $amount < $best['amount'])) {
                    $rate = $rt['rates'][0] ?? [];
                    $best = [
                        'amount' => (float) $amount,
                        'currency' => $rt['offerRetailRate']['currency'] ?? ($rate['retailRate']['total'][0]['currency'] ?? ''),
                        'public' => isset($rt['suggestedSellingPrice']['amount']) ? (float) $rt['suggestedSellingPrice']['amount'] : null,
                        'board' => $rate['boardName'] ?? null,
                        'refundable' => ($rate['cancellationPolicies']['refundableTag'] ?? '') === 'RFN',
                        'room' => $rate['name'] ?? null,
                    ];
                }
            }

            return $id && $best ? [
                'id' => $id,
                'name' => $c['name'] ?? 'Hotel',
                'photo' => $c['main_photo'] ?? ($c['thumbnail'] ?? null),
                'address' => $c['address'] ?? null,
                'city' => $c['city_name'] ?? ($c['city'] ?? null),
                'stars' => ! empty($c['stars']) ? (float) $c['stars'] : null,
                'rating' => isset($c['rating']) ? (float) $c['rating'] : null,
                'reviews' => $c['review_count'] ?? ($c['reviewCount'] ?? null),
                'lat' => $c['latitude'] ?? null, 'lng' => $c['longitude'] ?? null,
                'price' => $best,
            ] : null;
        })->filter()->values()->all();

        return ['hotels' => $hotels, 'error' => null];
    }

    /** Basic content for a list of hotels, keyed by id. */
    public function hotelsContent(array $ids): array
    {
        $out = [];
        foreach (array_chunk($ids, 50) as $chunk) {
            $r = $this->call('GET', self::API.'/data/hotels', ['hotelIds' => implode(',', $chunk), 'limit' => count($chunk)], 20);
            foreach ($r['data'] ?? [] as $h) {
                $out[$h['id'] ?? ''] = $h;
            }
        }

        return $out;
    }

    /** Full hotel details (photos, description, facilities). Cached for a day. */
    public function hotel(string $id): ?array
    {
        return Cache::remember('lite.hotel.v1.'.$id, now()->addDay(), function () use ($id) {
            $r = $this->call('GET', self::API.'/data/hotel', ['hotelId' => $id, 'language' => 'en'], 20);

            return isset($r['data']) ? $r['data'] : null;
        });
    }

    /** All room offers for one hotel. */
    public function rooms(string $hotelId, string $checkin, string $checkout, array $occupancies, string $currency, string $nationality): array
    {
        $r = $this->call('POST', self::API.'/hotels/rates', [
            'hotelIds' => [$hotelId], 'checkin' => $checkin, 'checkout' => $checkout, 'currency' => $currency,
            'guestNationality' => $nationality, 'occupancies' => $occupancies, 'timeout' => 15,
        ], 30);
        if (! $r || isset($r['_error'])) {
            return ['rooms' => [], 'error' => $r['_error'] ?? null];
        }
        $rooms = [];
        foreach ($r['data'][0]['roomTypes'] ?? [] as $rt) {
            $rate = $rt['rates'][0] ?? [];
            $amount = $rt['offerRetailRate']['amount'] ?? ($rate['retailRate']['total'][0]['amount'] ?? null);
            if (! isset($rt['offerId']) || $amount === null) {
                continue;
            }
            $cp = $rate['cancellationPolicies'] ?? [];
            $rooms[] = [
                'offerId' => $rt['offerId'],
                'mappedRoomId' => $rate['mappedRoomId'] ?? null,
                'name' => $rate['name'] ?? 'Room',
                'board' => $rate['boardName'] ?? 'Room only',
                'maxOccupancy' => $rate['maxOccupancy'] ?? null,
                'amount' => (float) $amount,
                'currency' => $rt['offerRetailRate']['currency'] ?? ($rate['retailRate']['total'][0]['currency'] ?? $currency),
                'public' => isset($rt['suggestedSellingPrice']['amount']) ? (float) $rt['suggestedSellingPrice']['amount'] : null,
                'refundable' => ($cp['refundableTag'] ?? '') === 'RFN',
                'cancelUntil' => collect($cp['cancelPolicyInfos'] ?? [])->sortBy('cancelTime')->first()['cancelTime'] ?? null,
                'taxesIncluded' => collect($rate['retailRate']['taxesAndFees'] ?? [])->every(fn ($t) => ($t['included'] ?? true)),
                'payAtHotel' => collect($rate['retailRate']['taxesAndFees'] ?? [])->filter(fn ($t) => isset($t['included']) && ! $t['included'])
                    ->map(fn ($t) => ['desc' => $t['description'] ?? 'Local tax', 'amount' => $t['amount'] ?? null, 'currency' => $t['currency'] ?? null])->values()->all(),
            ];
        }
        usort($rooms, fn ($a, $b) => $a['amount'] <=> $b['amount']);

        return ['rooms' => $rooms, 'error' => null];
    }

    public function prebook(string $offerId): array
    {
        $r = $this->call('POST', self::BOOK.'/rates/prebook', ['offerId' => $offerId, 'usePaymentSdk' => true], 40);

        return isset($r['data']) ? ['data' => $r['data']] : ['error' => $r['_error'] ?? 'This room is no longer available.'];
    }

    public function book(array $body): array
    {
        $r = $this->call('POST', self::BOOK.'/rates/book', $body, 90);

        return isset($r['data']) ? ['data' => $r['data']] : ['error' => $r['_error'] ?? 'Booking could not be confirmed.', 'raw' => $r['_json'] ?? null];
    }

    public function booking(string $bookingId): ?array
    {
        $r = $this->call('GET', self::BOOK.'/bookings/'.$bookingId, [], 20);

        return $r['data'] ?? null;
    }
}

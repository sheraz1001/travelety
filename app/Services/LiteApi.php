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
    public function search(array $where, string $checkin, string $checkout, array $occupancies, string $currency, string $nationality, int $limit = 150, array $facilities = []): array
    {
        $body = array_filter($where) + [
            'checkin' => $checkin, 'checkout' => $checkout, 'currency' => $currency,
            'guestNationality' => $nationality, 'occupancies' => $occupancies,
            'limit' => $limit, 'timeout' => 12, 'maxRatesPerHotel' => 1, 'includeHotelData' => true,
        ];
        if ($facilities) {
            $body['facilities'] = array_values(array_map('intval', $facilities));
            $body['strictFacilityFiltering'] = true;
        }
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
                        'boardType' => $rate['boardType'] ?? null,
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

    /* ======================= FLIGHTS ======================= */

    /**
     * Search flights. Returns ['hash'=>..,'journeys'=>[normalised], 'error'=>?].
     * Raw offers are cached under the hash so offerIds never travel through the browser.
     */
    public function flightSearch(array $legs, int $adults, int $children, int $infants, string $cabin, string $currency, string $country): array
    {
        $body = [
            'legs' => $legs, 'adults' => $adults, 'cabinClass' => $cabin, 'currency' => $currency, 'country' => $country,
        ];
        if ($children) {
            $body['children'] = $children;
            $body['childrenAges'] = array_fill(0, $children, 8);
        }
        if ($infants) {
            $body['infants'] = $infants;
            $body['infantAges'] = array_fill(0, $infants, 1);
        }
        $hash = substr(md5($this->mode().json_encode($body)), 0, 20);
        $cached = Cache::get('lite.fl.'.$hash);
        if ($cached) {
            return ['hash' => $hash, 'journeys' => $cached['journeys'], 'error' => null];
        }
        $r = $this->call('POST', self::API.'/flights/rates', $body, 90);
        if (! $r || isset($r['_error'])) {
            return ['hash' => $hash, 'journeys' => [], 'error' => $r['_error'] ?? 'Flight search failed. Please try again.'];
        }
        $data = $r['data'][0] ?? [];
        $offers = [];
        $journeys = [];
        foreach ($data['journeys'] ?? [] as $j) {
            $n = $this->normJourney($j, $offers, $legs);
            if ($n) {
                $journeys[] = $n;
            }
        }
        $best = $data['sortMetadata']['best']['journeyKey'] ?? null;
        foreach ($journeys as &$jj) {
            $jj['best'] = $jj['key'] === $best;
        }
        unset($jj);
        // offers expire after ~10 minutes at the provider
        Cache::put('lite.fl.'.$hash, ['journeys' => $journeys, 'offers' => $offers, 'body' => $body], now()->addMinutes(12));

        return ['hash' => $hash, 'journeys' => $journeys, 'error' => null];
    }

    public function flightCached(string $hash): ?array
    {
        return Cache::get('lite.fl.'.$hash);
    }

    /** Split a journey's flat segment list into the requested legs. */
    protected function splitLegs(array $segs, array $req): array
    {
        $out = [];
        $k = 0;
        $cur = [];
        $n = count($segs);
        foreach ($segs as $i => $s) {
            $cur[] = $s;
            $next = $segs[$i + 1] ?? null;
            $want = $req[$k]['destination'] ?? null;
            $nextReq = $req[$k + 1] ?? null;
            $ends = ! $next
                || ($want && strtoupper($s['destinationCode']) === strtoupper($want))
                || ($nextReq && substr($next['departureTime'], 0, 10) >= $nextReq['date']
                    && (strtotime($next['departureTime']) - strtotime($s['arrivalTime'])) >= 6 * 3600);
            if ($ends && $k < count($req) - 1 && $next) {
                $out[] = $cur;
                $cur = [];
                $k++;
            }
        }
        if ($cur) {
            $out[] = $cur;
        }

        return $out;
    }

    protected function normJourney(array $j, array &$offers, array $req = []): ?array
    {
        $all = $j['segments'] ?? [];
        if (! $all || empty($j['offers'])) {
            return null;
        }
        $groups = $req ? $this->splitLegs($all, $req) : collect($all)->groupBy(fn ($s) => $s['direction'] ?? 'OUTBOUND')->map->values()->values()->all();
        $multi = count($req) > 2 || (count($req) === 2 && ($req[1]['direction'] ?? '') !== 'INBOUND');
        $legs = [];
        foreach ($groups as $li => $ss) {
            $ss = collect($ss)->values();
            $first = $ss->first();
            $last = $ss->last();
            $ld = $j['legDurations'][$li] ?? [];
            $layovers = [];
            for ($x = 0; $x < $ss->count() - 1; $x++) {
                $a = $ss[$x];
                $b = $ss[$x + 1];
                $layovers[] = [
                    'code' => $a['destinationCode'], 'name' => $a['destinationName'] ?? '',
                    'min' => (int) round((strtotime($b['departureTime']) - strtotime($a['arrivalTime'])) / 60),
                    'changeAirport' => $a['destinationCode'] !== $b['originCode'],
                    'overnight' => substr($a['arrivalTime'], 0, 10) !== substr($b['departureTime'], 0, 10),
                ];
            }
            $dep = $first['departureTime'];
            $arr = $last['arrivalTime'];
            $legs[] = [
                'dir' => $multi ? 'LEG'.($li + 1) : ($li ? 'INBOUND' : 'OUTBOUND'),
                'from' => $first['originCode'], 'fromName' => $first['originName'] ?? '',
                'to' => $last['destinationCode'], 'toName' => $last['destinationName'] ?? '',
                'dep' => $dep, 'arr' => $arr,
                'min' => $ld['duration']['minutes'] ?? $ss->sum(fn ($s) => $s['duration']['minutes'] ?? 0) + collect($layovers)->sum('min'),
                'dayChange' => $ld['dayChange'] ?? max(0, (int) ((strtotime(substr($arr, 0, 10)) - strtotime(substr($dep, 0, 10))) / 86400)),
                'stops' => max(0, $ss->count() - 1) + $ss->sum(fn ($s) => $s['stopCount'] ?? 0),
                'layovers' => $layovers,
                'carriers' => $ss->map(fn ($s) => ['code' => $s['carrier']['marketingCode'] ?? '', 'name' => $s['carrier']['marketingName'] ?? '', 'logo' => $s['carrier']['marketingLogo'] ?? null])->unique('code')->values()->all(),
                'segments' => $ss->map(fn ($s) => [
                    'key' => $s['segmentKey'] ?? null,
                    'from' => $s['originCode'], 'fromName' => $s['originName'] ?? '', 'to' => $s['destinationCode'], 'toName' => $s['destinationName'] ?? '',
                    'dep' => $s['departureTime'], 'arr' => $s['arrivalTime'], 'min' => $s['duration']['minutes'] ?? null,
                    'flight' => ($s['carrier']['marketingCode'] ?? '').' '.($s['flight']['marketingNumber'] ?? ''),
                    'airline' => $s['carrier']['marketingName'] ?? '', 'logo' => $s['carrier']['marketingLogo'] ?? null,
                    'operatedBy' => ($s['carrier']['operatingCode'] ?? '') !== ($s['carrier']['marketingCode'] ?? '') ? ($s['carrier']['operatingName'] ?? null) : null,
                    'aircraft' => $s['aircraft']['name'] ?? ($s['aircraftType'] ?? null),
                ])->all(),
            ];
        }
        $opts = [];
        foreach ($j['offers'] as $o) {
            $idx = count($offers);
            $offers[$idx] = $o['offerId'];
            $bag = $o['baggage'] ?? [];
            $checked = collect($bag['included'] ?? [])->firstWhere('bagType', 'checked');
            $cabinBag = collect($bag['included'] ?? [])->firstWhere('bagType', 'cabin');
            $t = $o['terms'] ?? [];
            $opts[] = [
                'i' => $idx,
                'total' => (float) ($o['pricing']['display']['total'] ?? 0),
                'currency' => $o['pricing']['display']['currency'] ?? '',
                'adult' => (float) ($o['pricing']['display']['perPassenger']['adult']['total'] ?? 0),
                'family' => $o['fare']['family'] ?? 'Standard',
                'seats' => $o['fare']['seatsRemaining'] ?? null,
                'checked' => $checked ? ['pieces' => $checked['pieces'] ?? 1, 'kg' => $checked['weightKg'] ?? null] : null,
                'cabinBag' => $cabinBag ? ['pieces' => $cabinBag['pieces'] ?? 1, 'kg' => $cabinBag['weightKg'] ?? null] : null,
                'refundable' => (bool) ($t['refundable'] ?? false),
                'changeable' => (bool) ($t['changeable'] ?? false),
                'terms' => collect($t['summary'] ?? [])->pluck('message')->take(4)->values()->all(),
                'seatSelect' => ($o['seats']['seatReservation']['status'] ?? '') !== 'not-offered',
            ];
        }
        usort($opts, fn ($a, $b) => $a['total'] <=> $b['total']);

        return [
            'key' => $j['journeyKey'] ?? md5(json_encode($legs)),
            'price' => $opts[0]['total'], 'currency' => $opts[0]['currency'],
            'min' => $j['totalDuration']['minutes'] ?? collect($legs)->sum('min'),
            'legs' => $legs, 'offers' => $opts,
            'stops' => collect($legs)->max('stops'),
            'airlines' => collect($legs)->pluck('carriers')->flatten(1)->unique('code')->values()->all(),
        ];
    }

    public function flightPrebook(string $offerId, array $contact, array $passengers, array $meta = []): array
    {
        $r = $this->call('POST', self::API.'/flights/prebooks', array_filter([
            'offerId' => $offerId, 'usePaymentSdk' => true, 'contact' => $contact, 'passengers' => $passengers,
            'travelPurpose' => 'LEISURE', 'metadata' => $meta ?: null,
        ]), 120);
        if (isset($r['data'][0])) {
            return ['data' => $r['data'][0]];
        }

        return ['error' => $r['_error'] ?? 'This fare is no longer available.', 'raw' => $r['_json'] ?? null];
    }

    public function flightBook(string $prebookId, string $transactionId, string $ref): array
    {
        $r = $this->call('POST', self::API.'/flights/bookings', [
            'prebookId' => $prebookId, 'payment' => ['method' => 'TRANSACTION_ID', 'transactionId' => $transactionId],
            'customTags' => ['REF' => $ref],
        ], 150);
        if (isset($r['data'][0])) {
            return ['data' => $r['data'][0]];
        }
        if (isset($r['data']) && is_array($r['data']) && isset($r['data']['bookingId'])) {
            return ['data' => $r['data']];
        }

        return ['error' => $r['_error'] ?? 'Ticketing could not be confirmed.', 'raw' => $r['_json'] ?? null];
    }

    public function flightBooking(string $bookingId): ?array
    {
        $r = $this->call('GET', self::API.'/flights/bookings/'.$bookingId, [], 30);
        $x = $r['data'] ?? null;
        if (is_array($x) && array_is_list($x)) {
            $x = $x[0] ?? null;
        }

        return is_array($x) ? ($x['booking'] ?? $x) : null;
    }

    /** Replace the prebook's ancillary selection (seats/bags/meals). Returns new transaction + price. */
    public function flightServices(string $prebookId, array $selected): array
    {
        $r = $this->call('POST', self::API.'/flights/prebooks/'.$prebookId.'/services', ['selectedServices' => array_values($selected)], 90);
        if (isset($r['data'][0])) {
            return ['data' => $r['data'][0]];
        }

        return ['error' => $r['_json']['error']['description'] ?? ($r['_error'] ?? 'Could not add the extras.')];
    }

    /** Compact the prebook's servicesAttachable into seats per segment, bags and meals. */
    public static function compactServices(array $sa): array
    {
        $out = ['seats' => [], 'bags' => [], 'meals' => [], 'expires' => $sa['expiresAt'] ?? null];
        foreach ($sa['groups'] ?? [] as $g) {
            foreach ($g['services'] ?? [] as $sv) {
                $cat = $sv['category'] ?? $g['category'] ?? '';
                $price = (float) ($sv['pricing']['display']['amount'] ?? 0);
                $base = ['id' => $sv['serviceId'], 'seg' => $sv['segmentKey'] ?? null, 'price' => $price, 'pt' => $sv['passengerType'] ?? 'ALL'];
                if ($cat === 'seat') {
                    $m = $sv['metadata']['seat'] ?? [];
                    if (! isset($m['seatRow']) && preg_match('/\b(\d+)([A-Za-z]+)\b/', $sv['name'] ?? '', $mm)) {
                        $m += ['seatRow' => (int) $mm[1], 'seatColumn' => strtoupper($mm[2]), 'seatNumber' => $mm[1].strtoupper($mm[2]), 'available' => true];
                    }
                    if (! isset($m['seatRow'])) {
                        continue;
                    }
                    $out['seats'][$base['seg']][] = $base + ['r' => (int) $m['seatRow'], 'c' => (string) ($m['seatColumn'] ?? ''), 'n' => $m['seatNumber'] ?? ($m['seatRow'].($m['seatColumn'] ?? '')),
                        't' => $m['seatType'] ?? 'standard', 'pos' => $m['position'] ?? null, 'a' => (bool) ($m['available'] ?? true)];
                } elseif ($cat === 'baggage') {
                    $b = $sv['metadata']['baggage'] ?? [];
                    $out['bags'][] = $base + ['name' => ucwords(strtolower($sv['name'] ?? 'Extra bag')), 'kg' => $b['weightKg'] ?? null, 'pieces' => $b['pieces'] ?? 1, 'type' => $b['bagType'] ?? 'checked'];
                } elseif ($cat === 'meal') {
                    $out['meals'][] = $base + ['name' => ucwords(strtolower($sv['name'] ?? 'Meal'))];
                }
            }
        }

        return $out;
    }
}

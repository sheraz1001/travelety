<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Order;
use App\Models\Setting;
use App\Services\LiteApi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HotelBookingController extends Controller
{
    public function __construct(protected LiteApi $api) {}

    /** Normalised search params from the query string (with safe defaults). */
    protected function params(Request $r): array
    {
        $date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : null;
        $in = $date($r->query('checkin')) ?? now()->addDays(14)->toDateString();
        $out = $date($r->query('checkout')) ?? Carbon::parse($in)->addDays(2)->toDateString();
        if (Carbon::parse($in)->lt(today())) {
            $in = today()->addDay()->toDateString();
        }
        if (Carbon::parse($out)->lte(Carbon::parse($in))) {
            $out = Carbon::parse($in)->addDay()->toDateString();
        }
        if (Carbon::parse($in)->diffInDays(Carbon::parse($out)) > 30) {
            $out = Carbon::parse($in)->addDays(30)->toDateString();
        }
        $adults = max(1, min(8, (int) $r->query('adults', 2)));
        $children = collect(explode(',', (string) $r->query('children', '')))->filter(fn ($a) => $a !== '' && is_numeric($a))
            ->map(fn ($a) => max(0, min(17, (int) $a)))->take(4)->values()->all();
        $rooms = max(1, min(4, (int) $r->query('rooms', 1)));

        $occ = [];
        for ($i = 0; $i < $rooms; $i++) {
            $a = intdiv($adults, $rooms) + ($i < $adults % $rooms ? 1 : 0);
            $occ[] = ['adults' => max(1, $a), 'children' => $i === 0 ? $children : []];
        }

        $nat = strtoupper((string) ($r->header('CF-IPCountry') ?: ''));
        if (! preg_match('/^[A-Z]{2}$/', $nat) || in_array($nat, ['XX', 'T1'])) {
            $nat = 'US';
        }
        $cur = strtoupper((string) ($r->query('currency') ?: Setting::get('hotel_currency') ?: 'USD'));
        if (! preg_match('/^[A-Z]{3}$/', $cur)) {
            $cur = 'USD';
        }

        return [
            'q' => trim((string) $r->query('q', $r->query('city', ''))),
            'placeId' => (string) $r->query('placeId', ''),
            'checkin' => $in, 'checkout' => $out, 'nights' => Carbon::parse($in)->diffInDays(Carbon::parse($out)),
            'adults' => $adults, 'children' => $children, 'rooms' => $rooms,
            'occupancies' => $occ, 'currency' => $cur, 'nationality' => $nat,
        ];
    }

    protected function qs(array $p, array $extra = []): array
    {
        return array_filter([
            'q' => $p['q'], 'placeId' => $p['placeId'], 'checkin' => $p['checkin'], 'checkout' => $p['checkout'],
            'adults' => $p['adults'], 'children' => implode(',', $p['children']) ?: null, 'rooms' => $p['rooms'] > 1 ? $p['rooms'] : null,
        ] + $extra, fn ($v) => $v !== null && $v !== '');
    }

    public function places(Request $r)
    {
        $q = trim((string) $r->query('q', ''));
        $local = Airport::query()->where(fn ($w) => $w->where('city', 'like', $q.'%')->orWhere('iata', strtoupper($q)))
            ->orderBy('priority')->limit(5)->get(['city', 'country', 'country_code'])->unique('city')
            ->map(fn ($a) => ['placeId' => null, 'name' => $a->city, 'address' => $a->country, 'city' => $a->city, 'cc' => $a->country_code, 'type' => 'city'])->values();

        $remote = mb_strlen($q) >= 3 ? collect($this->api->places($q)) : collect();

        return response()->json($remote->isNotEmpty() ? $remote->take(8)->values() : $local->values());
    }

    public const FACILITIES = [
        107 => 'Free WiFi', 2 => 'Parking', 301 => 'Swimming pool', 557 => 'Spa', 492 => 'Fitness centre',
        17 => 'Airport shuttle', 3 => 'Restaurant', 28 => 'Family rooms', 302 => 'Beachfront', 109 => 'Air conditioning',
        8 => '24-hour front desk', 5 => 'Room service', 529 => 'Free breakfast', 63 => 'Hot tub/Jacuzzi',
    ];

    /** Resolve where to search: placeId, a known city, or the first matching place. */
    protected function where(array &$p): ?array
    {
        if ($p['placeId']) {
            return ['placeId' => $p['placeId']];
        }
        if ($p['q'] === '') {
            return null;
        }
        $a = Airport::where('city', $p['q'])->orWhere('city_slug', \Illuminate\Support\Str::slug($p['q']))->orderBy('priority')->first();
        if ($a) {
            return ['cityName' => $a->city, 'countryCode' => $a->country_code];
        }
        $place = $this->api->places($p['q'])[0] ?? null;
        if ($place) {
            $p['placeId'] = $place['placeId'];

            return ['placeId' => $place['placeId']];
        }

        return null;
    }

    /** Results page shell: renders instantly, hotels load via searchData(). */
    public function search(Request $r)
    {
        if (! $this->api->enabled()) {
            return redirect()->route('hotels.index');
        }
        $p = $this->params($r);

        return view('hotels.search', [
            'p' => $p, 'qs' => $this->qs($p), 'sandbox' => $this->api->mode() === 'sandbox',
            'facilities' => self::FACILITIES, 'selectedFac' => array_map('intval', (array) $r->query('fac', [])),
        ]);
    }

    public function searchData(Request $r)
    {
        abort_unless($this->api->enabled(), 404);
        $p = $this->params($r);
        $where = $this->where($p);
        if (! $where) {
            return response()->json(['hotels' => [], 'error' => $p['q'] === '' ? null : 'We couldn\'t find that destination. Try a city name.', 'searched' => $p['q'] !== '']);
        }
        $fac = array_values(array_intersect(array_map('intval', (array) $r->query('fac', [])), array_keys(self::FACILITIES)));
        $res = $this->api->search($where, $p['checkin'], $p['checkout'], $p['occupancies'], $p['currency'], $p['nationality'], 150, $fac);
        $qs = $this->qs($p);

        $hotels = collect($res['hotels'])->map(function ($h) use ($qs, $p) {
            $b = strtoupper((string) ($h['price']['boardType'] ?? ''));
            $bn = strtolower((string) ($h['price']['board'] ?? ''));
            $meal = match (true) {
                in_array($b, ['AI', 'UAI']) || str_contains($bn, 'all inclusive') => 'ai',
                $b === 'FB' || str_contains($bn, 'full board') => 'fb',
                $b === 'HB' || str_contains($bn, 'half board') => 'hb',
                in_array($b, ['BB', 'CB']) || str_contains($bn, 'breakfast') => 'bb',
                default => 'ro',
            };

            return $h + [
                'url' => route('hotels.stay', ['hotelId' => $h['id']] + $qs),
                'night' => round($h['price']['amount'] / max(1, $p['nights']), 2),
                'meal' => $meal,
            ];
        })->values();

        return response()->json(['hotels' => $hotels, 'error' => $res['error'], 'searched' => true, 'nights' => $p['nights'], 'placeId' => $p['placeId']]);
    }

    public function show(Request $r, string $hotelId)
    {
        abort_unless($this->api->enabled() && preg_match('/^[A-Za-z0-9_-]{2,40}$/', $hotelId), 404);
        $p = $this->params($r);
        $hotel = $this->api->hotel($hotelId);
        abort_unless($hotel, 404);
        $rooms = $this->api->rooms($hotelId, $p['checkin'], $p['checkout'], $p['occupancies'], $p['currency'], $p['nationality']);

        return view('hotels.hotel', [
            'p' => $p, 'h' => $hotel, 'rooms' => $rooms['rooms'], 'error' => $rooms['error'], 'hotelId' => $hotelId,
            'qs' => $this->qs($p), 'sandbox' => $this->api->mode() === 'sandbox',
        ]);
    }

    /** Guest picks a room → we prebook (price + availability check) and create a pending order. */
    public function prebook(Request $r, string $hotelId)
    {
        $data = $r->validate(['offerId' => 'required|string|max:5000']);
        $p = $this->params($r);
        $pre = $this->api->prebook($data['offerId']);
        if (isset($pre['error'])) {
            return back()->with('room_error', 'Sorry, that room just sold out or changed price. Please choose another room. ('.$pre['error'].')');
        }
        $d = $pre['data'];
        $hotel = $this->api->hotel($hotelId) ?? [];
        $rate = $d['roomTypes'][0]['rates'][0] ?? [];
        $cp = $rate['cancellationPolicies'] ?? [];

        $order = Order::create([
            'type' => 'hotel',
            'status' => 'pending_payment',
            'customer_name' => '',
            'email' => '',
            'currency' => $d['currency'] ?? $p['currency'],
            'amount' => (float) ($d['price'] ?? ($rate['retailRate']['total'][0]['amount'] ?? 0)),
            'cost_amount' => isset($d['commission']) ? (float) ($d['price'] ?? 0) - (float) $d['commission'] : null,
            'cost_currency' => $d['currency'] ?? null,
            'gateway' => 'liteapi',
            'gateway_ref' => $d['transactionId'] ?? null,
            'ip' => $r->ip(),
            'details' => [
                'mode' => $this->api->mode(),
                'hotel_id' => $hotelId,
                'hotel_name' => $hotel['name'] ?? 'Hotel',
                'hotel_address' => trim(($hotel['address'] ?? '').', '.($hotel['city'] ?? ''), ', '),
                'hotel_photo' => $hotel['main_photo'] ?? ($hotel['hotelImages'][0]['url'] ?? null),
                'stars' => $hotel['starRating'] ?? ($hotel['stars'] ?? null),
                'checkin' => $p['checkin'], 'checkout' => $p['checkout'], 'nights' => $p['nights'],
                'adults' => $p['adults'], 'children' => $p['children'], 'rooms' => $p['rooms'],
                'occupancies' => $p['occupancies'],
                'room_name' => $rate['name'] ?? 'Room',
                'board' => $rate['boardName'] ?? null,
                'refundable' => ($cp['refundableTag'] ?? '') === 'RFN',
                'cancel_policies' => $cp['cancelPolicyInfos'] ?? [],
                'hotel_remarks' => $cp['hotelRemarks'] ?? [],
                'prebook_id' => $d['prebookId'] ?? null,
                'transaction_id' => $d['transactionId'] ?? null,
                'secret_key' => $d['secretKey'] ?? null,
                'price_changed' => (bool) ($d['priceDifferencePercent'] ?? 0),
                'terms' => $d['termsAndConditions'] ?? null,
                'search' => $this->qs($p),
            ],
        ]);

        return redirect()->route('hotels.checkout', $order);
    }

    public function checkout(Order $order)
    {
        abort_unless($order->type === 'hotel', 404);
        if ($order->status === 'fulfilled') {
            return redirect()->route('hotels.booking', $order);
        }

        return view('hotels.checkout', ['o' => $order, 'd' => $order->details, 'sandbox' => ($order->details['mode'] ?? '') === 'sandbox']);
    }

    /** Step 1 of checkout: lead guest details. Then the page shows the payment form. */
    public function guest(Request $r, Order $order)
    {
        abort_unless($order->type === 'hotel' && $order->status === 'pending_payment', 404);
        $v = $r->validate([
            'first_name' => 'required|string|max:60', 'last_name' => 'required|string|max:60',
            'email' => 'required|email:rfc|max:120', 'phone' => 'nullable|string|max:30',
            'requests' => 'nullable|string|max:500', 'agree' => 'accepted',
        ]);
        $d = $order->details;
        $d['guest'] = ['first_name' => $v['first_name'], 'last_name' => $v['last_name'], 'email' => $v['email'], 'phone' => $v['phone'] ?? null, 'requests' => $v['requests'] ?? null];
        $order->update(['customer_name' => $v['first_name'].' '.$v['last_name'], 'email' => $v['email'], 'phone' => $v['phone'] ?? null, 'details' => $d]);

        return redirect()->route('hotels.checkout', [$order, 'step' => 'pay']);
    }

    /** Payment SDK returns here → confirm the booking with TRANSACTION_ID. */
    public function complete(Order $order)
    {
        abort_unless($order->type === 'hotel', 404);
        if ($order->status === 'fulfilled') {
            return redirect()->route('hotels.booking', $order);
        }
        if (! in_array($order->status, ['pending_payment', 'processing'])) {
            return redirect()->route('hotels.booking', $order);
        }
        $d = $order->details;
        $g = $d['guest'] ?? null;
        if (! $g || empty($d['prebook_id']) || empty($d['transaction_id'])) {
            return redirect()->route('hotels.checkout', $order);
        }
        $order->update(['status' => 'processing']);

        $guests = [];
        foreach (array_values($d['occupancies'] ?? [['adults' => 1]]) as $i => $o) {
            $guests[] = ['occupancyNumber' => $i + 1, 'firstName' => $g['first_name'], 'lastName' => $g['last_name'], 'email' => $g['email'], 'phone' => $g['phone'] ?? '', 'remarks' => $i === 0 ? ($g['requests'] ?? '') : ''];
        }
        $res = $this->api->book([
            'prebookId' => $d['prebook_id'],
            'clientReference' => $order->number,
            'holder' => ['firstName' => $g['first_name'], 'lastName' => $g['last_name'], 'email' => $g['email'], 'phone' => $g['phone'] ?? ''],
            'payment' => ['method' => 'TRANSACTION_ID', 'transactionId' => $d['transaction_id']],
            'guests' => $guests,
        ]);

        if (isset($res['error'])) {
            $order->update(['status' => 'needs_attention', 'error' => $res['error'], 'paid_at' => now()]);
            Log::error('Hotel booking failed after payment', ['order' => $order->number, 'error' => $res['error']]);
            $this->notifyAdmin($order, 'Hotel booking needs attention: '.$res['error']);

            return redirect()->route('hotels.booking', $order);
        }

        $b = $res['data'];
        $order->update([
            'status' => in_array(strtoupper($b['status'] ?? 'CONFIRMED'), ['CONFIRMED', 'COMPLETED']) ? 'fulfilled' : 'processing',
            'supplier_ref' => $b['bookingId'] ?? null,
            'paid_at' => now(),
            'fulfilled_at' => now(),
            'amount' => (float) ($b['price'] ?? $order->amount),
            'fulfilment' => [
                'booking_id' => $b['bookingId'] ?? null,
                'hotel_confirmation' => $b['hotelConfirmationCode'] ?? null,
                'supplier_ref' => $b['supplierBookingId'] ?? null,
                'status' => $b['status'] ?? null,
                'cancellation_policies' => $b['cancellationPolicies'] ?? null,
            ],
        ]);
        $this->sendConfirmation($order->fresh());

        return redirect()->route('hotels.booking', $order);
    }

    public function booking(Order $order)
    {
        abort_unless($order->type === 'hotel', 404);

        return view('hotels.booking', ['o' => $order, 'd' => $order->details, 'f' => $order->fulfilment ?? []]);
    }

    protected function sendConfirmation(Order $o): void
    {
        if ($o->emailed_at || ! $o->email) {
            return;
        }
        try {
            Mail::send('emails.hotel-booking', ['o' => $o, 'd' => $o->details, 'f' => $o->fulfilment ?? []], function ($m) use ($o) {
                $m->to($o->email, $o->customer_name)->subject('Booking confirmed: '.($o->details['hotel_name'] ?? 'your hotel').' · '.$o->number);
                if ($bcc = Setting::get('contact_email')) {
                    $m->bcc($bcc);
                }
            });
            $o->update(['emailed_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Hotel confirmation email failed: '.$e->getMessage());
        }
    }

    protected function notifyAdmin(Order $o, string $msg): void
    {
        try {
            if ($to = Setting::get('contact_email')) {
                Mail::raw($msg."\n\nOrder: ".$o->number."\nGuest: ".$o->customer_name.' <'.$o->email.">\nHotel: ".($o->details['hotel_name'] ?? '')."\nAmount: ".$o->money()."\nTransaction: ".($o->details['transaction_id'] ?? ''), fn ($m) => $m->to($to)->subject('[Travelety] '.$msg));
            }
        } catch (\Throwable $e) {
            Log::warning('Admin notify failed: '.$e->getMessage());
        }
    }
}

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

class FlightBookingController extends Controller
{
    public function __construct(protected LiteApi $api) {}

    public static function countries(): array
    {
        return json_decode(file_get_contents(resource_path('data/countries.json')), true);
    }

    public static function dial(): array
    {
        return json_decode(file_get_contents(resource_path('data/dial.json')), true);
    }

    protected function params(Request $r): array
    {
        $code = fn ($v) => preg_match('/^[A-Z]{3}$/', strtoupper((string) $v)) ? strtoupper($v) : null;
        $date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : null;
        $depart = $date($r->query('depart')) ?? now()->addDays(14)->toDateString();
        if (Carbon::parse($depart)->lt(today())) {
            $depart = today()->addDay()->toDateString();
        }
        $ret = $date($r->query('return'));
        if ($ret && Carbon::parse($ret)->lt(Carbon::parse($depart))) {
            $ret = null;
        }
        $adults = max(1, min(9, (int) $r->query('adults', 1)));
        $children = max(0, min(8, (int) $r->query('children', 0)));
        $infants = max(0, min($adults, (int) $r->query('infants', 0)));
        $cabinIn = strtoupper((string) $r->query('cabin', '0'));
        $cabin = match ($cabinIn) { '1', 'BUSINESS', 'C' => 'BUSINESS', '2', 'FIRST', 'F' => 'FIRST', 'PREMIUM_ECONOMY', 'W' => 'PREMIUM_ECONOMY', default => 'ECONOMY' };
        $from = $code($r->query('from')) ?? strtoupper((string) Setting::get('default_origin', 'DXB'));
        $to = $code($r->query('to'));
        $country = strtoupper((string) ($r->header('CF-IPCountry') ?: 'AE'));
        if (! preg_match('/^[A-Z]{2}$/', $country) || in_array($country, ['XX', 'T1'])) {
            $country = 'AE';
        }
        $cur = strtoupper((string) ($r->query('currency') ?: Setting::get('hotel_currency') ?: 'USD'));

        return compact('from', 'to', 'depart', 'ret', 'adults', 'children', 'infants', 'cabin', 'country') + ['currency' => preg_match('/^[A-Z]{3}$/', $cur) ? $cur : 'USD'];
    }

    protected function qs(array $p): array
    {
        return array_filter([
            'from' => $p['from'], 'to' => $p['to'], 'depart' => $p['depart'], 'return' => $p['ret'],
            'adults' => $p['adults'], 'children' => $p['children'] ?: null, 'infants' => $p['infants'] ?: null,
            'cabin' => $p['cabin'] !== 'ECONOMY' ? $p['cabin'] : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function search(Request $r)
    {
        if (! $this->api->enabled()) {
            return redirect()->route('go.flights', $r->query());
        }
        $p = $this->params($r);
        $fromA = Airport::byCode($p['from']);
        $toA = $p['to'] ? Airport::byCode($p['to']) : null;

        return view('flights.search', [
            'p' => $p, 'qs' => $this->qs($p), 'fromA' => $fromA, 'toA' => $toA,
            'sandbox' => $this->api->mode() === 'sandbox',
        ]);
    }

    public function searchData(Request $r)
    {
        abort_unless($this->api->enabled(), 404);
        $p = $this->params($r);
        if (! $p['to'] || $p['to'] === $p['from']) {
            return response()->json(['journeys' => [], 'error' => 'Choose where you are flying to.']);
        }
        $legs = [['origin' => $p['from'], 'destination' => $p['to'], 'date' => $p['depart'], 'direction' => 'OUTBOUND']];
        if ($p['ret']) {
            $legs[] = ['origin' => $p['to'], 'destination' => $p['from'], 'date' => $p['ret'], 'direction' => 'INBOUND'];
        }
        $res = $this->api->flightSearch($legs, $p['adults'], $p['children'], $p['infants'], $p['cabin'], $p['currency'], $p['country']);

        return response()->json(['hash' => $res['hash'], 'journeys' => $res['journeys'], 'error' => $res['error'],
            'pax' => $p['adults'] + $p['children'] + $p['infants']]);
    }

    /** Traveller picked a fare → snapshot it in a pending order. */
    public function select(Request $r)
    {
        $v = $r->validate(['hash' => 'required|alpha_num|max:40', 'key' => 'required|string|max:64', 'i' => 'required|integer|min:0']);
        $cached = $this->api->flightCached($v['hash']);
        if (! $cached) {
            return back()->with('fl_error', 'Prices have expired. Please search again for the latest fares.');
        }
        $journey = collect($cached['journeys'])->firstWhere('key', $v['key']);
        $offer = $journey ? collect($journey['offers'])->firstWhere('i', (int) $v['i']) : null;
        $offerId = $cached['offers'][(int) $v['i']] ?? null;
        if (! $journey || ! $offer || ! $offerId) {
            return back()->with('fl_error', 'That fare is no longer available. Please choose another.');
        }
        $b = $cached['body'];
        $order = Order::create([
            'type' => 'flight', 'status' => 'pending_payment', 'customer_name' => '', 'email' => '',
            'currency' => $offer['currency'], 'amount' => $offer['total'], 'gateway' => 'liteapi', 'ip' => $r->ip(),
            'details' => [
                'mode' => $this->api->mode(),
                'offer_id' => $offerId,
                'offer' => $offer,
                'journey' => ['legs' => $journey['legs'], 'airlines' => $journey['airlines'], 'min' => $journey['min']],
                'adults' => $b['adults'], 'children' => $b['children'] ?? 0, 'infants' => $b['infants'] ?? 0,
                'cabin' => $b['cabinClass'], 'expires' => now()->addMinutes(10)->toIso8601String(),
                'search' => $r->input('back'),
            ],
        ]);

        return redirect()->route('flights.checkout', $order);
    }

    public function checkout(Order $order)
    {
        abort_unless($order->type === 'flight', 404);
        if (in_array($order->status, ['fulfilled', 'processing', 'needs_attention'])) {
            return redirect()->route('flights.booking', $order);
        }

        return view('flights.checkout', [
            'o' => $order, 'd' => $order->details, 'sandbox' => ($order->details['mode'] ?? '') === 'sandbox',
            'countries' => self::countries(), 'dial' => self::dial(),
        ]);
    }

    /** Passenger details → prebook with the airline (holds seats, confirms price) → payment step. */
    public function passengers(Request $r, Order $order)
    {
        abort_unless($order->type === 'flight' && $order->status === 'pending_payment', 404);
        $d = $order->details;
        $n = ($d['adults'] ?? 1) + ($d['children'] ?? 0) + ($d['infants'] ?? 0);
        $lastDate = collect($d['journey']['legs'] ?? [])->max('dep');
        $rules = [
            'contact.first_name' => 'required|string|max:50', 'contact.last_name' => 'required|string|max:50',
            'contact.email' => 'required|email:rfc|max:120', 'contact.dial' => 'required|digits_between:1,4',
            'contact.phone' => 'required|string|min:5|max:20', 'agree' => 'accepted',
            'pax' => 'required|array|size:'.$n,
        ];
        foreach (range(0, $n - 1) as $i) {
            $rules += [
                "pax.$i.type" => 'required|in:0,1,2', "pax.$i.title" => 'required|in:MR,MS,MRS,MISS',
                "pax.$i.first_name" => 'required|string|max:50|regex:/^[\pL\s\'-]+$/u', "pax.$i.last_name" => 'required|string|max:50|regex:/^[\pL\s\'-]+$/u',
                "pax.$i.birthday" => 'required|date|before:today', "pax.$i.gender" => 'required|in:M,F',
                "pax.$i.nationality" => 'required|size:2', "pax.$i.doc_number" => 'required|string|max:20',
                "pax.$i.doc_country" => 'required|size:2', "pax.$i.doc_expiry" => 'required|date|after:'.substr((string) $lastDate, 0, 10),
            ];
        }
        $v = $r->validate($rules, [
            'pax.*.doc_expiry.after' => 'Passport must be valid beyond your travel dates.',
            'pax.*.first_name.regex' => 'Use letters only, as shown on the passport.',
            'pax.*.last_name.regex' => 'Use letters only, as shown on the passport.',
        ]);

        $passengers = [];
        foreach ($v['pax'] as $p) {
            $passengers[] = [
                'passengerType' => (int) $p['type'], 'title' => $p['title'],
                'firstName' => trim($p['first_name']), 'lastName' => trim($p['last_name']),
                'birthday' => Carbon::parse($p['birthday'])->toDateString(), 'gender' => $p['gender'],
                'nationality' => strtoupper($p['nationality']), 'documentType' => 'passport',
                'documentNumber' => strtoupper(preg_replace('/\s+/', '', $p['doc_number'])),
                'documentIssueCountry' => strtoupper($p['doc_country']), 'documentExpiry' => Carbon::parse($p['doc_expiry'])->toDateString(),
            ];
        }
        $c = $v['contact'];
        $contact = ['email' => $c['email'], 'firstName' => trim($c['first_name']), 'lastName' => trim($c['last_name']),
            'phoneCountryCode' => ltrim($c['dial'], '+'), 'phoneNumber' => preg_replace('/[^\d]/', '', $c['phone'])];

        $res = $this->api->flightPrebook($d['offer_id'], $contact, $passengers, array_filter(['ip' => $r->ip(), 'country' => $r->header('CF-IPCountry'), 'user_agent' => substr((string) $r->userAgent(), 0, 200), 'platform' => 'web']));
        if (isset($res['error'])) {
            $msg = $res['raw']['error']['description'] ?? $res['error'];

            return back()->withInput()->with('fl_error', 'The airline could not hold this fare: '.$msg.' Please check the details or choose another flight.');
        }
        $pb = $res['data'];
        $d['contact'] = $contact;
        $d['passengers'] = $passengers;
        $d['prebook_id'] = $pb['prebookId'] ?? null;
        $d['transaction_id'] = $pb['transactionId'] ?? null;
        $d['secret_key'] = $pb['secretKey'] ?? null;
        $d['price_before'] = $order->amount;
        $d['ticket_limit'] = $pb['booking']['order']['ticketLimitTime'] ?? null;
        $newPrice = (float) ($pb['price'] ?? ($pb['booking']['pricing']['totalAmount'] ?? $order->amount));
        $order->update([
            'customer_name' => $contact['firstName'].' '.$contact['lastName'], 'email' => $contact['email'],
            'phone' => '+'.$contact['phoneCountryCode'].' '.$contact['phoneNumber'],
            'amount' => $newPrice, 'currency' => $pb['currency'] ?? $order->currency,
            'gateway_ref' => $d['transaction_id'], 'details' => $d,
        ]);

        return redirect()->route('flights.checkout', [$order, 'step' => 'pay']);
    }

    public function complete(Order $order)
    {
        abort_unless($order->type === 'flight', 404);
        if ($order->status !== 'pending_payment') {
            return redirect()->route('flights.booking', $order);
        }
        $d = $order->details;
        if (empty($d['prebook_id']) || empty($d['transaction_id'])) {
            return redirect()->route('flights.checkout', $order);
        }
        $order->update(['status' => 'processing']);
        $res = $this->api->flightBook($d['prebook_id'], $d['transaction_id'], $order->number);

        if (isset($res['error'])) {
            $order->update(['status' => 'needs_attention', 'error' => $res['error'], 'paid_at' => now()]);
            Log::error('Flight booking failed after payment', ['order' => $order->number, 'error' => $res['error'], 'raw' => $res['raw'] ?? null]);
            $this->notifyAdmin($order, 'Flight booking needs attention: '.$res['error']);

            return redirect()->route('flights.booking', $order);
        }
        $b = $res['data'];
        $bk = $b['booking'] ?? $b;
        $order->update(['paid_at' => now(), 'supplier_ref' => $bk['bookingId'] ?? ($b['bookingId'] ?? null)]);
        $this->applyStatus($order, $bk);

        return redirect()->route('flights.booking', $order);
    }

    /** Map a provider booking payload onto the order; email once confirmed. */
    public function applyStatus(Order $order, array $bk): void
    {
        $status = strtoupper((string) ($bk['status'] ?? ''));
        $pnrs = collect($bk['airlineLocators'] ?? [])->map(fn ($l) => trim(($l['airlineCode'] ?? '').' '.($l['airlinePnr'] ?? '')))->filter()->values()->all();
        $f = $order->fulfilment ?? [];
        $f = array_merge($f, array_filter([
            'booking_id' => $bk['bookingId'] ?? null,
            'booking_ref' => $bk['bookingRef'] ?? null,
            'status' => $status ?: null,
            'pnrs' => $pnrs ?: null,
            'order_ref' => $bk['order']['reference']['orderId'] ?? null,
            'ticket_numbers' => collect($bk['passengers'] ?? [])->pluck('ticketNumber')->filter()->values()->all() ?: null,
        ]));
        $new = match (true) {
            in_array($status, ['CONFIRMED', 'TICKETED', 'ISSUED', 'COMPLETED', 'BOOKED']) => 'fulfilled',
            in_array($status, ['FAILED', 'REJECTED', 'ERROR', 'CANCELLED', 'CANCELED']) => 'needs_attention',
            default => 'processing',
        };
        $order->update(['status' => $new, 'fulfilment' => $f, 'fulfilled_at' => $new === 'fulfilled' ? ($order->fulfilled_at ?? now()) : $order->fulfilled_at]);
        if ($new === 'fulfilled') {
            $this->sendConfirmation($order->fresh());
        } elseif ($new === 'needs_attention' && ! ($f['alerted'] ?? false)) {
            $this->notifyAdmin($order, 'Flight booking '.$status.' after payment');
            $order->update(['fulfilment' => $f + ['alerted' => true]]);
        }
    }

    /** Re-check a processing booking with the provider (used by the booking page and the scheduler). */
    public function refresh(Order $order): void
    {
        if ($order->type !== 'flight' || $order->status !== 'processing' || ! $order->supplier_ref) {
            return;
        }
        $bk = $this->api->flightBooking($order->supplier_ref);
        if ($bk) {
            $this->applyStatus($order, $bk);
        }
    }

    public function status(Order $order)
    {
        abort_unless($order->type === 'flight', 404);
        $key = 'fl.poll.'.$order->id;
        if (! \Illuminate\Support\Facades\Cache::has($key)) {
            \Illuminate\Support\Facades\Cache::put($key, 1, 15);
            $this->refresh($order);
        }

        return response()->json(['status' => $order->fresh()->status]);
    }

    public function booking(Order $order)
    {
        abort_unless($order->type === 'flight', 404);

        return view('flights.booking', ['o' => $order, 'd' => $order->details, 'f' => $order->fulfilment ?? []]);
    }

    protected function sendConfirmation(Order $o): void
    {
        if ($o->emailed_at || ! $o->email) {
            return;
        }
        try {
            Mail::send('emails.flight-booking', ['o' => $o, 'd' => $o->details, 'f' => $o->fulfilment ?? []], function ($m) use ($o) {
                $legs = $o->details['journey']['legs'] ?? [];
                $m->to($o->email, $o->customer_name)->subject('Flight booked: '.($legs[0]['from'] ?? '').' → '.($legs[0]['to'] ?? '').' · '.$o->number);
                if ($bcc = Setting::get('contact_email')) {
                    $m->bcc($bcc);
                }
            });
            $o->update(['emailed_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Flight confirmation email failed: '.$e->getMessage());
        }
    }

    protected function notifyAdmin(Order $o, string $msg): void
    {
        try {
            if ($to = Setting::get('contact_email')) {
                Mail::raw($msg."\n\nOrder: ".$o->number."\nContact: ".$o->customer_name.' <'.$o->email.'> '.$o->phone."\nAmount: ".$o->money()."\nPrebook: ".($o->details['prebook_id'] ?? '')."\nTransaction: ".($o->details['transaction_id'] ?? ''), fn ($m) => $m->to($to)->subject('[Travelety] '.$msg));
            }
        } catch (\Throwable $e) {
            Log::warning('Admin notify failed: '.$e->getMessage());
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Click;
use App\Services\Links;
use Illuminate\Http\Request;

/** Outbound redirects: keeps partner URLs out of page source and counts clicks. */
class GoController extends Controller
{
    public function flights(Request $r)
    {
        $from = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $r->query('from', 'DXB')));
        $to = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $r->query('to', '')));
        $date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : null;
        $depart = $date($r->query('depart')) ?? now()->addDays(14)->toDateString();
        $return = $r->query('trip') === 'ow' ? '' : ($date($r->query('return')) ?? \Carbon\Carbon::parse($depart)->addDays(7)->toDateString());

        $this->log('flights', "{$from}-{$to}", $r);

        if ($to && \App\Models\Setting::get('flight_engine') !== 'whitelabel' && app(\App\Services\LiteApi::class)->enabled()) {
            return redirect()->route('flights.search', array_filter([
                'from' => $from, 'to' => $to, 'depart' => $depart, 'return' => $return ?: null,
                'adults' => $r->query('adults'), 'children' => $r->query('children') ?: null, 'infants' => $r->query('infants') ?: null,
                'cabin' => (int) $r->query('cabin', 0) === 1 ? 'BUSINESS' : null,
            ]));
        }

        return redirect()->away(Links::flights(
            $from, $to ?: $from, $depart, $return ?: null,
            max(1, min(9, (int) $r->query('adults', 1))),
            max(0, min(8, (int) $r->query('children', 0))),
            max(0, min(4, (int) $r->query('infants', 0))),
            (int) $r->query('cabin', 0) === 1 ? 1 : 0,
        ));
    }

    public function hotels(Request $r)
    {
        if (app(\App\Services\LiteApi::class)->enabled()) {
            return redirect()->route('hotels.search', array_filter(['q' => $r->query('city'), 'checkin' => $r->query('checkin'), 'checkout' => $r->query('checkout'), 'adults' => $r->query('adults')]));
        }
        if (! Links::hotelsEnabled()) {
            return redirect()->route('hotels.index');
        }
        $city = trim((string) $r->query('city', ''));
        $this->log('hotels', $city ?: null, $r);
        $date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : null;

        return redirect()->away(Links::hotels($city ?: null, $date($r->query('checkin')), $date($r->query('checkout')), max(1, min(9, (int) $r->query('adults', 2)))));
    }

    public function partner(Request $r, string $key)
    {
        if ($key === 'esim') {
            return redirect()->route('esim.index');
        }
        if ($key === 'transfers') {
            return redirect()->route('transfers.index');
        }
        $url = Links::partner($key);
        $this->log('partner', $key, $r);

        return $url ? redirect()->away($url) : redirect()->route('home');
    }

    protected function log(string $type, ?string $target, Request $r): void
    {
        if (preg_match('/bot|crawl|spider|slurp/i', (string) $r->userAgent())) {
            return;
        }
        try {
            Click::create([
                'type' => $type, 'target' => $target ? substr($target, 0, 120) : null,
                'source_path' => substr((string) parse_url((string) $r->headers->get('referer'), PHP_URL_PATH), 0, 250),
                'country' => $r->header('CF-IPCountry'),
            ]);
        } catch (\Throwable) {
        }
    }
}

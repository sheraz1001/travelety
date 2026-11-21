<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Click;
use App\Services\Geocoder;
use App\Services\GetTransfer;
use App\Services\Links;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransferController extends Controller
{
    public const POPULAR = ['DXB', 'AUH', 'SHJ', 'IST', 'JED', 'MED', 'LHR', 'BKK', 'TBS', 'BAK', 'CAI', 'KUL'];

    public function index(Request $r, Geocoder $geo, GetTransfer $gt, ?string $city = null)
    {
        $airport = null;
        if ($city) {
            $airport = Airport::where('city_slug', $city)->orderBy('priority')->first();
            abort_unless($airport, 404);
        }
        if ($r->filled('airport')) {
            $airport = Airport::byCode(substr(preg_replace('/[^A-Za-z]/', '', $r->query('airport')), 0, 3)) ?? $airport;
        }

        $to = trim((string) $r->query('to', ''));
        $date = $r->query('date') && strtotime($r->query('date')) ? Carbon::parse($r->query('date')) : now()->addDays(7)->setTime(12, 0);
        if ($date->lt(now()->addHours(6))) {
            $date = now()->addHours(7)->startOfHour();
        }
        $pax = max(1, min(16, (int) $r->query('pax', 2)));
        $direction = $r->query('dir') === 'to' ? 'to' : 'from';

        $result = null;
        $error = null;
        if ($airport && $to !== '') {
            $a = $geo->airport($airport);
            $b = $geo->search($to, $airport->country_code) ?? $geo->search($to.', '.$airport->city);
            if (! $a || ! $b) {
                $error = "We couldn't find that address. Try a hotel name or area, for example \"Dubai Marina\".";
            } elseif ($gt->enabled()) {
                [$p1, $p2] = $direction === 'from' ? [$a, $b] : [$b, $a];
                $result = $gt->prices($p1, $p2, $date->format('Y-m-d\TH:i'), $pax);
                $result['from'] = $direction === 'from' ? $airport->name : $b['name'];
                $result['to'] = $direction === 'from' ? $b['name'] : $airport->name;
            }
        }

        $popular = Airport::whereIn('iata', self::POPULAR)->get()->sortBy(fn ($x) => array_search($x->iata, self::POPULAR));

        return view('transfers.index', compact('airport', 'to', 'date', 'pax', 'direction', 'result', 'error', 'popular') + ['enabled' => $gt->enabled()]);
    }

    public function book(Request $r)
    {
        try {
            Click::create(['type' => 'transfer', 'target' => substr((string) $r->query('airport').' '.$r->query('vehicle'), 0, 120), 'source_path' => '/transfers', 'country' => $r->header('CF-IPCountry')]);
        } catch (\Throwable) {
        }
        $partner = $r->query('partner') === 'kiwitaxi' ? Links::partner('transfers') : Links::partner('gettransfer');

        return redirect()->away($partner ?: route('home'));
    }
}

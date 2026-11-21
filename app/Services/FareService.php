<?php

namespace App\Services;

use App\Models\FlightRoute;
use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls cached fare data from the flights data API, server-side.
 * Prices are cached by the provider (2–7 days old), so they are used for content,
 * never shown as a bookable price.
 */
class FareService
{
    protected function get(string $path, array $query): array
    {
        $token = config('travelety.data_api_token');
        if (! $token) {
            return [];
        }

        try {
            $res = Http::timeout(20)->retry(2, 500)
                ->withHeaders(['X-Access-Token' => $token, 'Accept-Encoding' => 'gzip'])
                ->get(rtrim(config('travelety.data_api_base'), '/').$path, $query);

            return $res->successful() ? ($res->json() ?? []) : [];
        } catch (\Throwable $e) {
            Log::warning('Fare API failed', ['path' => $path, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /** Cheapest fare per month for the next 12 months. */
    public function monthly(string $origin, string $destination): array
    {
        $currency = Setting::get('currency', 'aed');
        $json = $this->get('/aviasales/v3/grouped_prices', [
            'origin' => $origin, 'destination' => $destination,
            'group_by' => 'departure_at', 'currency' => $currency, 'market' => 'ae',
        ]);

        $months = [];
        foreach (($json['data'] ?? []) as $date => $row) {
            $m = substr($row['departure_at'] ?? $date, 0, 7);
            if (! isset($months[$m]) || $row['price'] < $months[$m]['price']) {
                $months[$m] = Arr::only($row, ['price', 'airline', 'transfers', 'departure_at', 'return_at', 'duration', 'duration_to']);
            }
        }

        if (! $months) {
            $old = $this->get('/v1/prices/monthly', ['origin' => $origin, 'destination' => $destination, 'currency' => $currency]);
            foreach (($old['data'] ?? []) as $m => $row) {
                $months[substr($m, 0, 7)] = [
                    'price' => $row['price'] ?? null, 'airline' => $row['airline'] ?? null,
                    'transfers' => $row['transfers'] ?? null, 'departure_at' => $row['departure_at'] ?? null,
                    'return_at' => $row['return_at'] ?? null,
                ];
            }
        }

        ksort($months);

        $thisMonth = now()->format('Y-m');

        return array_filter($months, fn ($r, $m) => ! empty($r['price']) && $m >= $thisMonth, ARRAY_FILTER_USE_BOTH);
    }

    /** Cheapest upcoming fares with dates. */
    public function cheapest(string $origin, string $destination, int $limit = 10): array
    {
        $json = $this->get('/aviasales/v3/prices_for_dates', [
            'origin' => $origin, 'destination' => $destination,
            'currency' => Setting::get('currency', 'aed'), 'sorting' => 'price',
            'unique' => 'false', 'direct' => 'false', 'one_way' => 'false', 'limit' => $limit, 'page' => 1, 'market' => 'ae',
        ]);

        return collect($json['data'] ?? [])->map(fn ($r) => Arr::only($r, [
            'price', 'airline', 'flight_number', 'departure_at', 'return_at', 'transfers', 'duration', 'duration_to',
        ]))->values()->all();
    }

    /** Refresh one route and store the summary on it. Returns true if fares were found. */
    public function refresh(FlightRoute $route): bool
    {
        $monthly = $this->monthly($route->origin_iata, $route->destination_iata);
        $cheapest = $this->cheapest($route->origin_iata, $route->destination_iata);

        if (! $monthly && ! $cheapest) {
            $route->update(['fares_updated_at' => now()]);

            return false;
        }

        $all = collect($cheapest)->merge(array_values($monthly))->filter(fn ($r) => ! empty($r['price']));
        $min = $all->min('price');
        $cheapMonth = collect($monthly)->sortBy('price')->keys()->first();
        $airlines = $all->pluck('airline')->filter()->countBy()->sortDesc();
        $direct = $all->contains(fn ($r) => (int) ($r['transfers'] ?? 1) === 0);
        $duration = $all->filter(fn ($r) => (int) ($r['transfers'] ?? 1) === 0)->pluck('duration_to')->filter()->min()
            ?? $all->pluck('duration_to')->filter()->min();

        $route->update([
            'fare_data' => ['monthly' => $monthly, 'cheapest' => $cheapest, 'airlines' => $airlines->keys()->take(8)->values()->all()],
            'min_price' => $min ? (int) round($min) : null,
            'cheapest_month' => $cheapMonth,
            'main_airline' => $airlines->keys()->first(),
            'is_direct' => $direct,
            'duration_minutes' => $duration ? (int) $duration : null,
            'fares_updated_at' => now(),
        ]);

        return true;
    }
}

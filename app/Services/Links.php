<?php

namespace App\Services;

use App\Models\Setting;

/** Builds links into the branded search (White Label) and partner pages. */
class Links
{
    public static function flights(string $from, string $to, ?string $depart = null, ?string $return = null, int $adults = 1, int $children = 0, int $infants = 0, int $cabin = 0): string
    {
        $depart ??= now()->addDays(14)->toDateString();
        $return ??= ''; // empty = one way

        return rtrim(Setting::get('search_domain'), '/').'/flights/?'.http_build_query([
            'origin_iata' => strtoupper($from), 'destination_iata' => strtoupper($to),
            'depart_date' => $depart, 'return_date' => $return, 'adults' => $adults,
            'children' => $children, 'infants' => $infants, 'trip_class' => $cabin,
            'currency' => strtolower(Currency::current()), 'locale' => Setting::get('locale'), 'with_request' => 'true',
        ]);
    }

    public static function partner(string $key): ?string
    {
        if ($key === 'esim' && \App\Models\EsimPlan::query()->exists()) {
            return route('esim.index');
        }
        $url = Setting::get('link_'.$key);

        return $url ?: null;
    }

    public static function hotelsEnabled(): bool
    {
        return (bool) Setting::get('link_hotels') || app(\App\Services\LiteApi::class)->enabled();
    }

    public static function hotels(?string $city = null, ?string $in = null, ?string $out = null, int $adults = 2): string
    {
        $base = Setting::get('link_hotels') ?: rtrim(Setting::get('search_domain'), '/').'/hotels';
        if (! $city) {
            return $base;
        }
        $sep = str_contains($base, '?') ? '&' : '?';

        return $base.$sep.http_build_query([
            'destination' => $city,
            'checkIn' => $in ?? now()->addDays(14)->toDateString(),
            'checkOut' => $out ?? now()->addDays(17)->toDateString(),
            'adults' => $adults, 'currency' => strtolower(Currency::current()), 'locale' => Setting::get('locale'),
        ]);
    }

    public static function money(?int $amount): string
    {
        return $amount ? Currency::show((float) $amount, Currency::base(), 0) : '—';
    }

    public static function duration(?int $minutes): ?string
    {
        if (! $minutes) {
            return null;
        }

        return intdiv($minutes, 60).'h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).'m';
    }
}

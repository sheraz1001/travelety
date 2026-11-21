<?php

namespace App\Http\Controllers;

use App\Models\EsimPlan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EsimController extends Controller
{
    /** One row per destination: cheapest price, plan count, region. */
    public static function summary()
    {
        $rows = Cache::remember('esim.index.v2', 3600, fn () => EsimPlan::active()
            ->selectRaw('slug, max(name) as name, max(region) as region, max(group_type) as group_type, min(price_usd) as from_usd, count(*) as plans, sum(case when data_gb is null then 1 else 0 end) as unlimited')
            ->groupBy('slug')->orderBy('name')->toBase()->get()->map(fn ($r) => (array) $r)->all());

        return collect($rows)->map(fn ($r) => (object) $r);
    }

    public static function forCountry(?string $country): ?object
    {
        if (! $country) {
            return null;
        }
        $aliases = ['united-states' => 'united-states', 'turkiye' => 'turkey', 'czechia' => 'czech-republic'];
        $slug = $aliases[Str::slug($country)] ?? Str::slug($country);

        return static::summary()->firstWhere('slug', $slug) ?? static::summary()->firstWhere('name', $country);
    }

    public function index()
    {
        $all = static::summary();
        $popularSlugs = ['turkey', 'united-kingdom', 'saudi-arabia', 'georgia', 'thailand', 'azerbaijan', 'egypt', 'india', 'pakistan', 'philippines', 'united-states', 'maldives'];
        $popular = $all->whereIn('slug', $popularSlugs)->sortBy(fn ($d) => array_search($d->slug, $popularSlugs))->values();
        $multi = $all->where('group_type', '!=', 'country')->sortBy('from_usd')->values();
        $byRegion = $all->where('group_type', 'country')->groupBy(fn ($d) => $d->region === 'Other' ? 'More destinations' : $d->region)
            ->sortBy(fn ($v, $k) => array_search($k, ['Middle East & Africa', 'Europe & CIS', 'Asia Pacific', 'North America', 'Latin America', 'More destinations']));
        $cheapest = EsimPlan::active()->min('price_usd');

        return view('esim.index', compact('all', 'popular', 'multi', 'byRegion', 'cheapest'));
    }

    public function show(string $slug)
    {
        $plans = EsimPlan::active()->where('slug', $slug)->orderBy('price_usd')->get();
        abort_if($plans->isEmpty(), 404);
        $first = $plans->first();
        $name = $plans->countBy('name')->sortDesc()->keys()->first();
        $info = [
            'networks' => $plans->pluck('networks')->filter()->countBy()->sortDesc()->keys()->first(),
            'other' => $plans->pluck('other_info')->filter()->countBy()->sortDesc()->keys()->first(),
            'coverage' => $first->group_type !== 'country' ? $plans->pluck('coverage')->filter()->sortByDesc(fn ($c) => strlen($c))->first() : null,
            'top_ups' => $plans->contains('top_ups', true),
            'voice' => $plans->contains(fn ($p) => str_contains($p->plan_type, 'voice')),
            'operators' => $plans->pluck('operator')->filter()->unique()->values(),
        ];
        $bestValue = $plans->whereNotNull('data_gb')->where('data_gb', '>=', 3)->sortBy(fn ($p) => $p->pricePerGb())->first();
        $destination = \App\Models\Destination::published()->where('country', $name)->orderByDesc('is_featured')->first();
        $route = $destination ? \App\Models\FlightRoute::published()->where('destination_iata', $destination->iata)->orderBy('priority')->first() : null;
        $related = static::summary()->where('region', $first->region)->where('slug', '!=', $slug)->where('group_type', 'country')->sortByDesc('plans')->take(10);
        $regional = $first->group_type === 'country'
            ? static::summary()->where('group_type', '!=', 'country')->filter(fn ($r) => EsimPlan::active()->where('slug', $r->slug)->where('coverage', 'like', '%'.$name.'%')->exists())->values()
            : collect();

        return view('esim.show', compact('plans', 'name', 'first', 'info', 'bestValue', 'destination', 'route', 'related', 'regional', 'slug'));
    }

    public function go(EsimPlan $plan)
    {
        try {
            \App\Models\Click::create(['type' => 'esim', 'target' => $plan->slug.':'.$plan->external_id, 'source_path' => substr((string) parse_url((string) request()->headers->get('referer'), PHP_URL_PATH), 0, 250), 'country' => request()->header('CF-IPCountry')]);
        } catch (\Throwable) {
        }

        return redirect()->away($plan->affiliateUrl());
    }
}

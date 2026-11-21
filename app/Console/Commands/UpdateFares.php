<?php

namespace App\Console\Commands;

use App\Models\FlightRoute;
use App\Services\FareService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class UpdateFares extends Command
{
    protected $signature = 'fares:update {--limit=150 : Routes per run} {--route= : One route slug}';

    protected $description = 'Refresh cached fares for route pages and publish routes that have fares';

    public function handle(FareService $fares): int
    {
        if (! (config('travelety.data_api_token') ?: \App\Models\Setting::get('fares_api_token'))) {
            $this->error('Fare API token missing: add it in Admin → Site settings');

            return self::FAILURE;
        }

        $routes = $this->option('route')
            ? FlightRoute::where('slug', $this->option('route'))->get()
            : FlightRoute::orderByRaw('fares_updated_at IS NOT NULL')->orderBy('fares_updated_at')->orderBy('priority')
                ->limit((int) $this->option('limit'))->get();

        $found = 0;
        foreach ($routes as $route) {
            $ok = $fares->refresh($route);
            if ($ok) {
                $found++;
                // Routes go live automatically once real fare data exists
                if (! $route->is_published) {
                    $route->update(['is_published' => true, 'is_indexable' => true]);
                }
            }
            $this->line(($ok ? '✓ ' : '· ').$route->slug.($ok ? ' '.$route->min_price : ''));
            usleep(250000); // stay well inside API rate limits
        }

        Cache::forget('sitemap.v2.flights');
        $this->info("Updated {$routes->count()} routes, {$found} with fares.");

        return self::SUCCESS;
    }
}

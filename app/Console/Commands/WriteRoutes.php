<?php

namespace App\Console\Commands;

use App\Models\FlightRoute;
use App\Models\Setting;
use App\Services\AiWriter;
use App\Services\ContentGenerator;
use Illuminate\Console\Command;

class WriteRoutes extends Command
{
    protected $signature = 'routes:write {--limit= : How many route pages to write}';

    protected $description = 'Write unique editorial text for live route pages that have fares but no text yet';

    public function handle(ContentGenerator $gen, AiWriter $ai): int
    {
        if (! $ai->enabled()) {
            $this->warn('AI_API_KEY not set; skipping.');

            return self::SUCCESS;
        }
        $limit = (int) ($this->option('limit') ?? Setting::get('routes_content_per_run'));
        $routes = FlightRoute::published()->whereNotNull('min_price')->whereNull('content_generated_at')
            ->orderBy('priority')->limit($limit)->get();

        foreach ($routes as $r) {
            try {
                $gen->forRoute($r);
                $this->info('✓ '.$r->slug);
            } catch (\Throwable $e) {
                $this->error('✗ '.$r->slug.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}

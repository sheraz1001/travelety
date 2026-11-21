<?php

namespace App\Console\Commands;

use App\Models\Destination;
use App\Services\AiWriter;
use App\Services\ContentGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WriteDestinations extends Command
{
    protected $signature = 'destinations:write {--limit=5} {--slug=} {--publish : Publish after writing}';

    protected $description = 'Write guide + hotels content for destinations that have none yet';

    public function handle(ContentGenerator $gen, AiWriter $ai): int
    {
        if (! $ai->enabled()) {
            $this->warn('AI_API_KEY not set; skipping.');

            return self::SUCCESS;
        }
        $q = Destination::query();
        $this->option('slug') ? $q->where('slug', $this->option('slug')) : $q->whereNull('guide_content')->orderByDesc('is_featured')->orderBy('id');

        foreach ($q->limit((int) $this->option('limit'))->get() as $d) {
            try {
                $gen->forDestination($d);
                if ($this->option('publish') && $d->fresh()->guide_content) {
                    $d->update(['is_published' => true]);
                }
                $this->info('✓ '.$d->city);
            } catch (\Throwable $e) {
                $this->error('✗ '.$d->city.': '.$e->getMessage());
            }
        }
        Cache::forget('sitemap.v2.destinations');

        return self::SUCCESS;
    }
}

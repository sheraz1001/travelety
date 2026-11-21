<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\Setting;
use App\Models\Topic;
use App\Services\AiWriter;
use App\Services\ContentGenerator;
use App\Services\QualityChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DailyBlog extends Command
{
    protected $signature = 'blog:daily {--topic= : Write a specific topic id} {--force : Ignore the daily limit}';

    protected $description = 'Write the next blog article(s) from the topic queue, check quality, then publish or hold for review';

    public function handle(ContentGenerator $gen, QualityChecker $qc, AiWriter $ai): int
    {
        if (! $ai->enabled()) {
            $this->warn('AI_API_KEY not set; skipping.');

            return self::SUCCESS;
        }

        $perDay = (int) Setting::get('blog_posts_per_day');
        $already = Post::where('ai_generated', true)->whereDate('created_at', today())->count();
        if (! $this->option('force') && $already >= $perDay) {
            $this->info("Daily limit reached ({$perDay}).");

            return self::SUCCESS;
        }

        $topics = $this->option('topic')
            ? Topic::whereKey($this->option('topic'))->get()
            : Topic::where('status', 'queued')->orderBy('priority')->orderBy('id')->limit(max(1, $perDay - $already))->get();

        if ($topics->isEmpty()) {
            $this->warn('Topic queue is empty — add keywords under Blog topic queue in the admin.');

            return self::SUCCESS;
        }

        $minWords = (int) Setting::get('blog_min_words');
        $auto = (bool) Setting::get('blog_auto_publish');

        foreach ($topics as $t) {
            $t->update(['status' => 'writing', 'error' => null]);
            try {
                $post = $gen->blogPost($t, $minWords);
                $report = $qc->check($post, $minWords);
                $publish = $auto && $report['passed'];
                $post->update([
                    'quality_report' => $report,
                    'status' => $publish ? 'published' : 'review',
                    'published_at' => $publish ? now() : null,
                ]);
                $t->update(['status' => 'done', 'post_id' => $post->id]);
                $this->info(($publish ? 'Published: ' : 'Ready for review: ').$post->title);
            } catch (\Throwable $e) {
                $t->update(['status' => 'failed', 'error' => substr($e->getMessage(), 0, 500)]);
                $this->error('Failed "'.$t->keyword.'": '.$e->getMessage());
            }
        }
        Cache::forget('sitemap.blog');

        return self::SUCCESS;
    }
}

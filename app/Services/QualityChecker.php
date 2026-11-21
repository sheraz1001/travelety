<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Str;

/** Automatic checks a post must pass before it can be auto-published. */
class QualityChecker
{
    public function check(Post $post, int $minWords = 1200): array
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $post->body)));
        $words = str_word_count($text);
        $kw = Str::lower((string) $post->focus_keyword);
        $first = Str::lower(Str::words($text, 120, ''));
        $h2 = preg_match_all('/<h2/i', (string) $post->body);
        $banned = ['in today\'s fast-paced', 'look no further', 'embark on', 'unlock the', 'hidden gem', 'delve into', 'tapestry', 'as an ai'];
        $bannedFound = array_values(array_filter($banned, fn ($b) => str_contains(Str::lower($text), $b)));
        $similar = Post::where('id', '!=', $post->id)->get(['title'])->first(function ($p) use ($post) {
            similar_text(Str::lower($p->title), Str::lower($post->title), $pct);

            return $pct > 80;
        });

        $checks = [
            'word_count' => ['pass' => $words >= $minWords, 'value' => $words, 'need' => $minWords],
            'keyword_in_intro' => ['pass' => $kw === '' || $this->containsKeyword($first, $kw)],
            'headings' => ['pass' => $h2 >= 4, 'value' => $h2, 'need' => 4],
            'meta_title' => ['pass' => filled($post->meta_title) && mb_strlen($post->meta_title) <= 70],
            'meta_description' => ['pass' => mb_strlen((string) $post->meta_description) >= 110 && mb_strlen((string) $post->meta_description) <= 165],
            'faqs' => ['pass' => count($post->faqs ?? []) >= 3, 'value' => count($post->faqs ?? [])],
            'no_filler_phrases' => ['pass' => ! $bannedFound, 'found' => $bannedFound],
            'not_duplicate' => ['pass' => ! $similar, 'similar_to' => $similar?->title],
            'has_table_or_list' => ['pass' => (bool) preg_match('/<(table|ul|ol)/i', (string) $post->body)],
        ];

        $passed = collect($checks)->every(fn ($c) => $c['pass']);

        return ['passed' => $passed, 'checked_at' => now()->toIso8601String(), 'checks' => $checks];
    }

    protected function containsKeyword(string $haystack, string $kw): bool
    {
        $parts = array_filter(explode(' ', $kw), fn ($w) => strlen($w) > 3);
        if (! $parts) {
            return true;
        }
        $hits = collect($parts)->filter(fn ($w) => str_contains($haystack, $w))->count();

        return $hits / count($parts) >= 0.6;
    }
}

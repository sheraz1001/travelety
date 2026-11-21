<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Thin client for the AI model that drafts content. Provider and model are set in .env. */
class AiWriter
{
    public function enabled(): bool
    {
        return (bool) config('travelety.ai.key');
    }

    public function text(string $system, string $prompt, ?int $maxTokens = null): string
    {
        $cfg = config('travelety.ai');
        if (! $cfg['key']) {
            throw new RuntimeException('AI_API_KEY is not set in .env');
        }
        $max = $maxTokens ?? $cfg['max_tokens'];

        if ($cfg['provider'] === 'openai') {
            $res = Http::timeout(240)->withToken($cfg['key'])->post('https://api.openai.com/v1/chat/completions', [
                'model' => $cfg['model'], 'max_completion_tokens' => $max,
                'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]],
            ])->throw()->json();

            return trim($res['choices'][0]['message']['content'] ?? '');
        }

        $res = Http::timeout(240)->withHeaders([
            'x-api-key' => $cfg['key'], 'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $cfg['model'], 'max_tokens' => $max, 'system' => $system,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ])->throw()->json();

        return trim(collect($res['content'] ?? [])->where('type', 'text')->pluck('text')->implode("\n"));
    }

    /** Ask for JSON and decode it (tolerates code fences). */
    public function json(string $system, string $prompt, ?int $maxTokens = null): array
    {
        $raw = $this->text($system."\nReturn ONLY valid JSON. No markdown fences, no commentary.", $prompt, $maxTokens);
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw);
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        $data = $start !== false ? json_decode(substr($raw, $start, $end - $start + 1), true) : null;
        if (! is_array($data)) {
            throw new RuntimeException('AI returned invalid JSON');
        }

        return $data;
    }
}

<?php

namespace App\Services;

use App\Models\Destination;
use App\Models\FlightRoute;
use App\Models\Post;
use App\Models\Topic;
use Illuminate\Support\Str;

class ContentGenerator
{
    public const SYSTEM = <<<'TXT'
You are the senior travel editor of Travelety, a travel search site for travellers in the UAE and the Gulf.
Write accurate, specific, genuinely useful travel content in clear British-leaning English.
Rules:
- Use the facts supplied. Never invent prices, schedules, airlines, statistics or quotes.
- If you are unsure of a rule (visa, entry, baggage), say travellers should confirm with the official source, and keep it general.
- No filler ("in today's fast-paced world", "look no further", "embark on a journey"). No clichés. No emojis.
- Write for UAE residents of many nationalities: mention that visa rules depend on passport where relevant.
- Output clean semantic HTML only: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <table>, <thead>, <tbody>, <tr>, <th>, <td>. No <h1>, no inline styles, no scripts, no links.
TXT;

    public function __construct(protected AiWriter $ai) {}

    public function forRoute(FlightRoute $r): void
    {
        $from = $r->origin();
        $to = $r->destination();
        $facts = [
            'origin' => "{$from->city} ({$from->iata}, {$from->name}, {$from->country})",
            'destination' => "{$to->city} ({$to->iata}, {$to->name}, {$to->country})",
            'lowest_recent_fare' => $r->min_price ? Links::money($r->min_price).' (cached, recent searches)' : 'unknown',
            'cheapest_month' => $r->cheapest_month,
            'direct_flights_seen' => $r->is_direct,
            'typical_flight_time' => Links::duration($r->duration_minutes),
            'airline_codes_seen' => $r->fare_data['airlines'] ?? [],
            'monthly_lowest' => collect($r->fare_data['monthly'] ?? [])->map(fn ($m) => $m['price'])->all(),
        ];

        $data = $this->ai->json(self::SYSTEM, "Write the editorial section for the route page \"Flights from {$from->city} to {$to->city}\".\n"
            .'FACTS (JSON): '.json_encode($facts)."\n\n"
            ."Return JSON with keys:\n"
            ."- content: 450–700 words of HTML. Sections (use <h2>): 'About this route' (airports, flight time, direct or connecting, airlines by name if you know the code), 'When to fly for the best fares' (use monthly_lowest; describe the pattern, don't list every number), 'Tips for booking {$from->city} to {$to->city}' (5–7 practical, route-specific bullets: airport transfers, terminals, baggage on low-cost carriers, peak seasons such as Eid, summer and school holidays), 'Arriving in {$to->city}' (getting from the airport to the city, entry note).\n"
            ."- faqs: array of 5 objects {q, a} specific to this route; answers 1–3 sentences.\n"
            ."- meta_title: max 60 chars, include 'Flights {$from->city} to {$to->city}'.\n"
            .'- meta_description: 140–155 chars, mention comparing airlines and the cheapest month if known.', 4000);

        $r->update([
            'content' => Sanitizer::html($data['content'] ?? ''),
            'faqs' => $this->faqs($data['faqs'] ?? []),
            'meta_title' => Str::limit($data['meta_title'] ?? $r->title(), 70, ''),
            'meta_description' => Str::limit($data['meta_description'] ?? '', 160, ''),
            'content_generated_at' => now(),
        ]);
    }

    public function forDestination(Destination $d): void
    {
        $data = $this->ai->json(self::SYSTEM, "Create the destination content for {$d->city}, {$d->country} (airport code {$d->iata}).\n"
            ."Return JSON with keys:\n"
            ."- tagline: max 70 chars, evocative but concrete.\n"
            ."- intro: 120–180 words HTML (<p> only) — why go, who it suits, flight time from the UAE in general terms.\n"
            ."- hotels_content: 500–700 words HTML for the 'Hotels in {$d->city}' page: <h2>Where to stay in {$d->city}</h2> covering 4–6 real neighbourhoods (who each suits, rough budget level), then <h2>Choosing a hotel</h2> with practical advice, then <h2>When to book</h2>.\n"
            ."- guide_content: 700–1000 words HTML travel guide for UAE travellers: <h2> sections 'Getting there from the UAE', 'Getting around', 'Top things to do', 'Food to try', 'Money, SIM and practical tips', 'Sample 4-day itinerary'.\n"
            ."- best_time: 2–3 sentences.\n"
            ."- visa_uae: 2–3 sentences, general; say it depends on passport and to check the official source.\n"
            ."- areas: array of 4–6 {name, description (1 sentence), budget: 'budget'|'mid'|'luxury'}.\n"
            ."- things_to_do: array of 6–8 {name, description (1 sentence)}.\n"
            ."- weather: array of 12 {month: 'Jan'.., avg_high_c: integer, note: short} with realistic climate averages.\n"
            ."- faqs: array of 6 {q, a}.\n"
            ."- meta_title: max 60 chars.\n"
            .'- meta_description: 140–155 chars.', 8000);

        $d->update([
            'tagline' => Str::limit($data['tagline'] ?? '', 90, ''),
            'intro' => Sanitizer::html($data['intro'] ?? ''),
            'hotels_content' => Sanitizer::html($data['hotels_content'] ?? ''),
            'guide_content' => Sanitizer::html($data['guide_content'] ?? ''),
            'best_time' => strip_tags($data['best_time'] ?? ''),
            'visa_uae' => strip_tags($data['visa_uae'] ?? ''),
            'areas' => array_values(array_filter($data['areas'] ?? [], 'is_array')),
            'things_to_do' => array_values(array_filter($data['things_to_do'] ?? [], 'is_array')),
            'weather' => array_values(array_filter($data['weather'] ?? [], 'is_array')),
            'faqs' => $this->faqs($data['faqs'] ?? []),
            'meta_title' => Str::limit($data['meta_title'] ?? "{$d->city} travel guide", 70, ''),
            'meta_description' => Str::limit($data['meta_description'] ?? '', 160, ''),
            'content_generated_at' => now(),
        ]);
    }

    public function blogPost(Topic $t, int $minWords = 1200): Post
    {
        $facts = [];
        if ($t->origin_iata && $t->destination_iata) {
            $route = FlightRoute::where('origin_iata', $t->origin_iata)->where('destination_iata', $t->destination_iata)->first();
            if ($route?->fare_data) {
                $facts['route_fares'] = [
                    'lowest_recent' => Links::money($route->min_price), 'cheapest_month' => $route->cheapest_month,
                    'monthly_lowest' => collect($route->fare_data['monthly'] ?? [])->map(fn ($m) => $m['price'])->all(),
                    'direct' => $route->is_direct, 'flight_time' => Links::duration($route->duration_minutes),
                ];
            }
        }

        $existing = Post::latest()->limit(40)->pluck('title')->all();

        $data = $this->ai->json(self::SYSTEM, "Write a high-quality blog article.\n"
            ."Target keyword: \"{$t->keyword}\"\n"
            .($t->title_hint ? "Working title: {$t->title_hint}\n" : '')
            .($t->notes ? "Editor notes: {$t->notes}\n" : '')
            .($facts ? 'Live fare facts you may use (cached, label them as recent fares that change daily): '.json_encode($facts)."\n" : '')
            .'Avoid duplicating these existing articles: '.json_encode($existing)."\n\n"
            ."Requirements: at least {$minWords} words; search intent answered in the first 100 words; scannable <h2>/<h3> structure; at least one useful <table> or checklist; concrete specifics (areas, timings, costs in AED where generally known, transport options); a short 'Bottom line' section at the end. Natural use of the keyword in the first paragraph and one <h2>.\n\n"
            ."Return JSON with keys: title (max 65 chars, compelling, no clickbait), slug (lowercase-hyphenated, max 70 chars), meta_title (max 60), meta_description (140–155), excerpt (2 sentences), focus_keyword, body (HTML), faqs (array of 5 {q,a}), cover_alt (image alt text), suggested_destination_city.", 9000);

        $title = Str::limit(strip_tags($data['title'] ?? $t->keyword), 90, '');
        $slug = Str::slug($data['slug'] ?? $title) ?: Str::slug($title);
        if (Post::where('slug', $slug)->exists()) {
            $slug .= '-'.now()->format('ymd');
        }

        $destination = null;
        if (! empty($data['suggested_destination_city'])) {
            $destination = Destination::where('city', $data['suggested_destination_city'])->first();
        }

        return Post::create([
            'category_id' => $t->category_id,
            'destination_id' => $destination?->id,
            'title' => $title,
            'slug' => $slug,
            'excerpt' => strip_tags($data['excerpt'] ?? ''),
            'body' => Sanitizer::html($data['body'] ?? ''),
            'faqs' => $this->faqs($data['faqs'] ?? []),
            'focus_keyword' => $data['focus_keyword'] ?? $t->keyword,
            'meta_title' => Str::limit($data['meta_title'] ?? $title, 70, ''),
            'meta_description' => Str::limit($data['meta_description'] ?? '', 160, ''),
            'cover_alt' => $data['cover_alt'] ?? $title,
            'ai_generated' => true,
            'status' => 'draft',
        ]);
    }

    protected function faqs(array $faqs): array
    {
        return collect($faqs)->filter(fn ($f) => is_array($f) && ! empty($f['q']) && ! empty($f['a']))
            ->map(fn ($f) => ['q' => strip_tags($f['q']), 'a' => strip_tags($f['a'])])->values()->all();
    }
}

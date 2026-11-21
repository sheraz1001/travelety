<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Category;
use App\Models\Destination;
use App\Models\FlightRoute;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;

class SiteController extends Controller
{
    public function home()
    {
        $routes = FlightRoute::published()->orderBy('priority')->limit(9)->get();

        $order = ['IST', 'NRT', 'CDG', 'DPS', 'LHR', 'BKK', 'SYD', 'JFK'];
        $featured = Destination::whereIn('iata', $order)->get()
            ->sortBy(fn ($d) => array_search($d->iata, $order))->values();

        $esimGroups = [];
        foreach ([
            'Popular countries' => ['turkey', 'united-kingdom', 'united-states', 'saudi-arabia', 'egypt', 'france'],
            'Regional' => ['europe', 'asia', 'middle-east-and-north-africa', 'latin-america', 'africa', 'world'],
            'Asia' => ['japan', 'thailand', 'indonesia', 'india', 'singapore', 'malaysia'],
        ] as $label => $slugs) {
            $rows = \App\Models\EsimPlan::whereIn('slug', $slugs)
                ->selectRaw('slug, max(name) as name, count(*) as plans, min(price_usd) as from_price, sum(case when data_gb is null then 1 else 0 end) as unlimited')
                ->groupBy('slug')->get()->keyBy('slug');
            $esimGroups[$label] = collect($slugs)->map(fn ($sl) => $rows[$sl] ?? null)->filter()->values();
        }
        $esimCount = \App\Models\EsimPlan::distinct()->count('slug');

        $posts = Post::live()->with('category')->latest('published_at')->limit(3)->get();

        $faqs = [
            ['q' => 'Do I book with '.\App\Models\Setting::get('site_name').'?', 'a' => 'We are a travel search and comparison site. Once you find a deal, you complete your booking directly with the airline, hotel or travel agency you chose. They issue your ticket, take your payment and handle any changes.'],
            ['q' => 'Do you charge any fees?', 'a' => 'No. Searching and comparing is completely free, and we never add anything to the price. We may earn a small commission from our partners when you book, at no extra cost to you.'],
            ['q' => 'Why did the price change when I clicked through?', 'a' => 'Fares update constantly and seats at a given price can sell out. We show the most recent prices available, but the final price is always confirmed on the provider\'s page before you pay.'],
            ['q' => 'Who do I contact about my booking?', 'a' => 'Your booking confirmation comes from the company you booked with. Contact them for changes, cancellations, refunds or baggage questions, using the details in your confirmation email.'],
            ['q' => 'Can I find cheap Umrah flights here?', 'a' => 'Yes. Search for flights to Jeddah (JED) or Madinah (MED) from your city. Fares for Ramadan and school holidays rise early, so book ahead or set a price alert.'],
        ];

        return view('home', compact('routes', 'featured', 'posts', 'faqs', 'esimGroups', 'esimCount'));
    }

    public function flights()
    {
        $origins = Airport::where('is_origin', true)->where('country_code', 'AE')->orderBy('priority')->get();
        $byCountry = FlightRoute::published()->orderBy('priority')->get()
            ->groupBy(fn ($r) => $r->destination()?->country ?? 'Other')->sortKeys();

        return view('flights.index', compact('origins', 'byCountry'));
    }

    public function route(string $slug)
    {
        $route = FlightRoute::where('slug', $slug)->published()->firstOrFail();
        $dest = $route->destination();

        $related = FlightRoute::published()->where('origin_iata', $route->origin_iata)->where('id', '!=', $route->id)
            ->whereIn('destination_iata', Airport::where('country_code', $dest?->country_code)->pluck('iata'))
            ->limit(8)->get();
        if ($related->count() < 6) {
            $related = $related->merge(FlightRoute::published()->where('origin_iata', $route->origin_iata)
                ->where('id', '!=', $route->id)->whereNotIn('id', $related->pluck('id'))->orderBy('priority')->limit(12 - $related->count())->get());
        }

        $alternatives = FlightRoute::published()->where('destination_iata', $route->destination_iata)->where('id', '!=', $route->id)->get();
        $return = FlightRoute::published()->where('origin_iata', $route->destination_iata)->where('destination_iata', $route->origin_iata)->first();
        $guide = $route->destinationGuide();

        return view('flights.show', compact('route', 'related', 'alternatives', 'return', 'guide'));
    }

    public function flightsFrom(string $city)
    {
        $airport = Airport::where('city_slug', $city)->orderBy('priority')->firstOrFail();
        $codes = Airport::where('city_slug', $city)->pluck('iata');
        $routes = FlightRoute::published()->whereIn('origin_iata', $codes)->orderBy('priority')->get();
        abort_if($routes->isEmpty() && ! $airport->is_origin, 404);

        return view('flights.hub', ['mode' => 'from', 'city' => $airport->city, 'iata' => $airport->iata, 'routes' => $routes, 'guide' => null]);
    }

    public function flightsTo(string $city)
    {
        $airport = Airport::where('city_slug', $city)->orderBy('priority')->firstOrFail();
        $codes = Airport::where('city_slug', $city)->pluck('iata');
        $routes = FlightRoute::published()->whereIn('destination_iata', $codes)->orderBy('priority')->get();
        $guide = Destination::published()->where('slug', $city)->first();

        return view('flights.hub', ['mode' => 'to', 'city' => $airport->city, 'iata' => $airport->iata, 'routes' => $routes, 'guide' => $guide]);
    }

    public function hotels()
    {
        $destinations = Destination::published()->whereNotNull('hotels_content')->orderByDesc('is_featured')->orderBy('city')->get();

        return view('hotels.index', compact('destinations'));
    }

    public function hotel(string $slug)
    {
        $d = Destination::published()->where('slug', $slug)->whereNotNull('hotels_content')->firstOrFail();
        $routes = $d->routesTo();

        return view('hotels.show', compact('d', 'routes'));
    }

    public function travel()
    {
        $byCountry = Destination::published()->orderBy('city')->get()->groupBy('country')->sortKeys();

        return view('travel.index', compact('byCountry'));
    }

    public function guide(string $slug)
    {
        $d = Destination::published()->where('slug', $slug)->firstOrFail();
        $routes = $d->routesTo();
        $posts = Post::live()->where('destination_id', $d->id)->latest('published_at')->limit(3)->get();

        return view('travel.show', compact('d', 'routes', 'posts'));
    }

    public function blog(?string $category = null)
    {
        $cat = $category ? Category::where('slug', $category)->firstOrFail() : null;
        $posts = Post::live()->with('category')->when($cat, fn ($q) => $q->where('category_id', $cat->id))
            ->latest('published_at')->paginate(12);

        return view('blog.index', ['posts' => $posts, 'category' => $cat, 'categories' => Category::orderBy('name')->get()]);
    }

    public function post(string $slug)
    {
        $post = Post::live()->with(['category', 'destination'])->where('slug', $slug)->firstOrFail();
        Post::whereKey($post->id)->increment('views');
        $related = Post::live()->with('category')->where('id', '!=', $post->id)
            ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
            ->latest('published_at')->limit(3)->get();

        return view('blog.show', compact('post', 'related'));
    }

    public function feed()
    {
        $posts = Post::live()->latest('published_at')->limit(30)->get();

        return response()->view('blog.feed', compact('posts'))->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public function page(string $slug)
    {
        $pages = [
            'about' => ['About us', 'Who we are and how our travel search works.'],
            'contact' => ['Contact us', 'Get in touch with our team.'],
            'privacy' => ['Privacy policy', 'How we collect and use information.'],
            'terms' => ['Terms of use', 'The terms for using our website.'],
        ];
        abort_unless(isset($pages[$slug]), 404);
        [$title, $description] = $pages[$slug];

        return view('pages.show', compact('slug', 'title', 'description'));
    }

    public function airportsJs()
    {
        $js = Cache::remember('airports.js', 3600, function () {
            $list = Airport::orderBy('priority')->get()->map(fn ($a) => [$a->iata, $a->city, $a->name, $a->country ?? ''])->values();

            return 'window.TRV_AIRPORTS='.json_encode($list, JSON_UNESCAPED_UNICODE).';';
        });

        return response($js)->header('Content-Type', 'application/javascript')->header('Cache-Control', 'public, max-age=3600');
    }
}

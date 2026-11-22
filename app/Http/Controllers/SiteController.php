<?php

namespace App\Http\Controllers;

use App\Models\Airport;
use App\Models\Category;
use App\Models\Destination;
use App\Models\FlightRoute;
use App\Models\Post;
use App\Models\Setting;
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
            ['q' => 'Do I book with '.Setting::get('site_name').'?', 'a' => app(\App\Services\LiteApi::class)->enabled()
                ? 'Yes for flights and hotels: you search, choose and pay on our secure checkout, and the airline or hotel confirms your booking. For eSIMs, transfers, car rental and tours we send you to a trusted partner to complete the booking.'
                : 'We are a travel search and comparison site. Once you find a deal, you complete your booking directly with the airline, hotel or travel agency you chose. They issue your ticket, take your payment and handle any changes.'],
            ['q' => 'Do you charge any fees?', 'a' => 'Searching is free. The price shown is the total you pay, with no extra booking fee added at checkout.'],
            ['q' => 'Why did the price change when I clicked through?', 'a' => 'Fares update constantly and seats at a given price can sell out. We show the most recent prices available, but the final price is always confirmed on the provider\'s page before you pay.'],
            ['q' => 'Who do I contact about my booking?', 'a' => 'Your booking confirmation comes from the company you booked with. Contact them for changes, cancellations, refunds or baggage questions, using the details in your confirmation email.'],
            ['q' => 'Can I find cheap Umrah flights here?', 'a' => 'Yes. Search for flights to Jeddah (JED) or Madinah (MED) from your city. Fares for Ramadan and school holidays rise early, so book ahead or set a price alert.'],
        ];

        return view('home', compact('routes', 'featured', 'posts', 'faqs', 'esimGroups', 'esimCount'));
    }

    public function flights()
    {
        $origins = Airport::where('is_origin', true)->orderBy('priority')->get();
        $routes = FlightRoute::published()->orderBy('priority')->get();
        $byCountry = $routes->groupBy(fn ($r) => $r->destination()?->country ?? 'Other')->sortKeys();
        $from = strtoupper((string) Setting::get('default_origin', 'DXB'));
        $fromCity = Airport::byCode($from)?->city ?? $from;
        $prices = $routes->where('origin_iata', $from)->filter(fn ($r) => $r->min_price)->keyBy('destination_iata');

        $order = ['IST', 'LHR', 'CDG', 'BKK', 'DPS', 'NRT', 'MLE', 'JFK', 'SIN', 'FCO', 'SYD', 'CPT'];
        $popular = Destination::whereIn('iata', array_diff($order, [$from]))->get()
            ->sortBy(fn ($d) => array_search($d->iata, $order))->values()->take(8);
        $inSite = app(\App\Services\LiteApi::class)->enabled() && Setting::get('flight_engine') !== 'whitelabel';
        $site = Setting::get('site_name');

        $faqs = array_values(array_filter([
            $inSite ? ['q' => "Can I book my flight on {$site}?", 'a' => "Yes. Search, pick a fare and pay on {$site}'s secure checkout. Your ticket is issued by the airline and you get a confirmation email with your booking reference and the airline reference (PNR)."] : null,
            $inSite ? ['q' => 'Can I choose my seat and add bags?', 'a' => 'When the airline offers it, you can pick seats from a seat map, add checked bags and choose meals for each passenger after entering passenger details. Each extra shows its price before you pay.'] : null,
            $inSite ? ['q' => 'Can I book a multi-city trip?', 'a' => 'Yes. Choose Multi-city in the search box and add up to five flights, for example Dubai to London, London to Paris and Paris back to Dubai.'] : null,
            ['q' => 'When is the cheapest time to book?', 'a' => 'For most international routes, booking 6 to 10 weeks ahead gives the best fares. Peak periods such as school holidays, Eid and Christmas fill early, so book those sooner.'],
            ['q' => 'Why did the price change?', 'a' => 'Airlines change fares constantly and seats at a given price can sell out. The final price is confirmed before you pay.'],
            ['q' => 'What baggage is included?', 'a' => 'It depends on the airline and fare. Each result shows whether cabin and checked bags are included, and you can filter for fares with checked baggage.'],
        ]));

        return view('flights.index', compact('origins', 'byCountry', 'routes', 'from', 'fromCity', 'prices', 'popular', 'inSite', 'faqs'));
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
        $order = ['DXB', 'LHR', 'CDG', 'IST', 'DPS', 'MLE', 'NRT', 'BKK', 'SIN', 'FCO', 'JFK', 'JTR'];
        $popular = Destination::whereIn('iata', $order)->get()->sortBy(fn ($d) => array_search($d->iata, $order))->values()->take(8);
        $inSite = app(\App\Services\LiteApi::class)->enabled();
        $site = Setting::get('site_name');

        $faqs = array_values(array_filter([
            $inSite ? ['q' => "Do I book the hotel on {$site}?", 'a' => "Yes. Choose a room and pay on {$site}'s secure checkout. You receive a confirmation email with your booking reference straight away, and the hotel receives your reservation."] : null,
            $inSite ? ['q' => 'Can I cancel my booking?', 'a' => 'It depends on the rate. Each room shows whether it has free cancellation and until when. Non-refundable rates are usually cheaper but cannot be refunded.'] : null,
            $inSite ? ['q' => 'Which currency will I pay in?', 'a' => 'Prices are shown in the currency you pick at the top of the page, and the amount at checkout matches the price you see.'] : null,
            ['q' => 'Are taxes included in the price?', 'a' => 'The price shown includes the taxes collected at booking. Some cities also charge a local tourism fee that you pay at the hotel, so check the room details before you book.'],
            ['q' => 'When should I book a hotel?', 'a' => 'For busy cities and holiday periods, book four to eight weeks ahead. Free-cancellation rates let you lock in a room early and still change your plans.'],
        ]));

        return view('hotels.index', compact('destinations', 'popular', 'inSite', 'faqs'));
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

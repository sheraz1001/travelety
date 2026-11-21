<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Destination;
use App\Models\FlightRoute;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index()
    {
        $maps = ['pages', 'flights', 'destinations', 'blog'];

        return response()->view('sitemap.index', compact('maps'))->header('Content-Type', 'application/xml');
    }

    public function show(string $type)
    {
        $urls = Cache::remember("sitemap.$type", 3600, fn () => match ($type) {
            'pages' => collect([
                [route('home'), now()], [route('flights.index'), now()], [route('hotels.index'), now()],
                [route('travel.index'), now()], [route('blog.index'), now()],
                [route('flights.from', 'dubai'), now()], [route('flights.from', 'abu-dhabi'), now()], [route('flights.from', 'sharjah'), now()],
                [route('page', 'about'), null], [route('page', 'contact'), null],
            ])->merge(Category::all()->map(fn ($c) => [route('blog.category', $c->slug), null])),
            'flights' => FlightRoute::published()->where('is_indexable', true)->get(['slug', 'updated_at'])
                ->map(fn ($r) => [route('flights.show', $r->slug), $r->updated_at]),
            'destinations' => Destination::published()->where('is_indexable', true)->get()->flatMap(fn ($d) => array_filter([
                [route('travel.show', $d->slug), $d->updated_at],
                $d->hotels_content ? [route('hotels.show', $d->slug), $d->updated_at] : null,
                [route('flights.to', $d->slug), $d->updated_at],
            ])),
            'blog' => Post::live()->where('noindex', false)->get(['slug', 'updated_at'])->map(fn ($p) => [route('blog.show', $p->slug), $p->updated_at]),
            default => abort(404),
        });

        return response()->view('sitemap.urls', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }

    public function robots()
    {
        $body = "User-agent: *\nDisallow: /admin\nDisallow: /go/\nAllow: /\n\nSitemap: ".route('sitemap')."\n";

        return response($body)->header('Content-Type', 'text/plain');
    }
}

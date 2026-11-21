<?php

use App\Http\Controllers\GoController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SubscribeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');

Route::get('/flights', [SiteController::class, 'flights'])->name('flights.index');
Route::get('/flights/{slug}', [SiteController::class, 'route'])->name('flights.show');
Route::get('/flights-from/{city}', [SiteController::class, 'flightsFrom'])->name('flights.from');
Route::get('/flights-to/{city}', [SiteController::class, 'flightsTo'])->name('flights.to');

Route::get('/hotels', [SiteController::class, 'hotels'])->name('hotels.index');
Route::get('/hotels/{slug}', [SiteController::class, 'hotel'])->name('hotels.show');

Route::get('/travel', [SiteController::class, 'travel'])->name('travel.index');
Route::get('/travel/{slug}', [SiteController::class, 'guide'])->name('travel.show');

Route::get('/blog', [SiteController::class, 'blog'])->name('blog.index');
Route::get('/blog/feed', [SiteController::class, 'feed'])->name('blog.feed');
Route::get('/blog/category/{category}', [SiteController::class, 'blog'])->name('blog.category');
Route::get('/blog/{slug}', [SiteController::class, 'post'])->name('blog.show');

Route::get('/go/flights', [GoController::class, 'flights'])->name('go.flights');
Route::get('/go/hotels', [GoController::class, 'hotels'])->name('go.hotels');
Route::get('/go/{key}', [GoController::class, 'partner'])->whereIn('key', ['transfers', 'tours', 'esim', 'cars', 'insurance', 'compensation', 'hotels'])->name('go.partner');

Route::post('/subscribe', [SubscribeController::class, 'store'])->middleware('throttle:10,1')->name('subscribe');
Route::get('/unsubscribe/{token}', [SubscribeController::class, 'unsubscribe'])->name('unsubscribe');

Route::get('/js/airports.js', [SiteController::class, 'airportsJs'])->name('airports.js');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-{type}.xml', [SitemapController::class, 'show'])->name('sitemap.show');
Route::get('/robots.txt', [SitemapController::class, 'robots']);

// Serves uploads when the host does not allow the public/storage symlink
Route::get('/storage/{path}', function (string $path) {
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    abort_unless(! str_contains($path, '..') && $disk->exists($path), 404);

    return response()->file($disk->path($path), ['Cache-Control' => 'public, max-age=2592000']);
})->where('path', '.*');

Route::get('/_setup/{task}', \App\Http\Controllers\SetupController::class)->middleware('throttle:20,1');

Route::get('/{slug}', [SiteController::class, 'page'])->whereIn('slug', ['about', 'contact', 'privacy', 'terms'])->name('page');

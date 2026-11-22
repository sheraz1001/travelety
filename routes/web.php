<?php

use App\Http\Controllers\GoController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SubscribeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');

Route::get('/flights', [SiteController::class, 'flights'])->name('flights.index');
Route::controller(\App\Http\Controllers\FlightBookingController::class)->group(function () {
    Route::get('/flights/search', 'search')->name('flights.search');
    Route::get('/flights/search.json', 'searchData')->middleware('throttle:30,1')->name('flights.search.data');
    Route::post('/flights/select', 'select')->middleware('throttle:30,1')->name('flights.select');
    Route::get('/flights/checkout/{order}', 'checkout')->name('flights.checkout');
    Route::post('/flights/checkout/{order}', 'passengers')->middleware('throttle:15,1')->name('flights.passengers');
    Route::get('/flights/checkout/{order}/complete', 'complete')->name('flights.complete');
    Route::get('/flights/booking/{order}', 'booking')->name('flights.booking');
    Route::get('/flights/booking/{order}/status', 'status')->middleware('throttle:30,1')->name('flights.status');
});
Route::get('/flights/{slug}', [SiteController::class, 'route'])->name('flights.show');
Route::get('/flights-from/{city}', [SiteController::class, 'flightsFrom'])->name('flights.from');
Route::get('/flights-to/{city}', [SiteController::class, 'flightsTo'])->name('flights.to');

Route::get('/hotels', [SiteController::class, 'hotels'])->name('hotels.index');
Route::controller(\App\Http\Controllers\HotelBookingController::class)->group(function () {
    Route::get('/hotels/search', 'search')->name('hotels.search');
    Route::get('/hotels/search.json', 'searchData')->middleware('throttle:40,1')->name('hotels.search.data');
    Route::get('/hotels/places', 'places')->middleware('throttle:60,1')->name('hotels.places');
    Route::get('/hotels/stay/{hotelId}', 'show')->name('hotels.stay');
    Route::post('/hotels/stay/{hotelId}/reserve', 'prebook')->middleware('throttle:20,1')->name('hotels.prebook');
    Route::get('/hotels/checkout/{order}', 'checkout')->name('hotels.checkout');
    Route::post('/hotels/checkout/{order}', 'guest')->middleware('throttle:20,1')->name('hotels.guest');
    Route::get('/hotels/checkout/{order}/complete', 'complete')->name('hotels.complete');
    Route::get('/hotels/booking/{order}', 'booking')->name('hotels.booking');
});
Route::get('/hotels/{slug}', [SiteController::class, 'hotel'])->name('hotels.show');

Route::get('/travel', [SiteController::class, 'travel'])->name('travel.index');
Route::get('/travel/{slug}', [SiteController::class, 'guide'])->name('travel.show');

Route::get('/blog', [SiteController::class, 'blog'])->name('blog.index');
Route::get('/blog/feed', [SiteController::class, 'feed'])->name('blog.feed');
Route::get('/blog/category/{category}', [SiteController::class, 'blog'])->name('blog.category');
Route::get('/blog/{slug}', [SiteController::class, 'post'])->name('blog.show');

Route::get('/esim', [\App\Http\Controllers\EsimController::class, 'index'])->name('esim.index');
Route::get('/esim/{slug}', [\App\Http\Controllers\EsimController::class, 'show'])->name('esim.show');
Route::get('/go/esim/{plan}', [\App\Http\Controllers\EsimController::class, 'go'])->whereNumber('plan')->name('esim.go');

Route::get('/transfers', [\App\Http\Controllers\TransferController::class, 'index'])->name('transfers.index');
Route::get('/transfers/book', [\App\Http\Controllers\TransferController::class, 'book'])->name('transfers.book');
Route::get('/transfers/{city}', [\App\Http\Controllers\TransferController::class, 'index'])->name('transfers.city');

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

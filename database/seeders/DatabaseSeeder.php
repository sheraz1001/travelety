<?php

namespace Database\Seeders;

use App\Models\Airport;
use App\Models\Category;
use App\Models\Destination;
use App\Models\FlightRoute;
use App\Models\Setting;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Setting::DEFAULTS as $k => $v) {
            Setting::firstOrCreate(['key' => $k], ['value' => $v]);
        }

        // Airports
        foreach (require database_path('data/airports.php') as [$iata, $city, $name, $cc, $country, $origin, $prio]) {
            Airport::updateOrCreate(['iata' => $iata], [
                'city' => $city, 'name' => $name, 'country_code' => $cc, 'country' => $country,
                'city_slug' => Str::slug($city), 'is_origin' => (bool) $origin,
                'is_destination' => $cc !== 'AE' || $iata === 'DXB' || $iata === 'AUH', 'priority' => $prio,
            ]);
        }

        // Destinations: one per city (main airport), skipping UAE cities
        Airport::where('country_code', '!=', 'AE')->orderBy('priority')->get()->unique('city_slug')
            ->each(function (Airport $a) {
                Destination::firstOrCreate(['slug' => $a->city_slug], [
                    'city' => $a->city, 'country' => $a->country, 'iata' => $a->iata,
                    'is_published' => false, 'is_featured' => $a->priority <= 4,
                ]);
            });

        // Routes: UAE origins → every destination airport (published once fares are found)
        $origins = Airport::where('is_origin', true)->where('country_code', 'AE')->get();
        $dests = Airport::where('is_destination', true)->where('country_code', '!=', 'AE')
            ->orderBy('priority')->get()->unique('city_slug'); // one main airport per city
        foreach ($origins as $o) {
            foreach ($dests as $d) {
                FlightRoute::firstOrCreate(
                    ['origin_iata' => $o->iata, 'destination_iata' => $d->iata],
                    ['slug' => FlightRoute::makeSlug($o, $d), 'priority' => $o->priority * 1000 + $d->priority]
                );
            }
        }

        // Blog categories
        $cats = collect([
            'Flight deals' => 'Cheap fares, route guides and the best time to book.',
            'Travel guides' => 'Destination guides written for travellers from the UAE.',
            'Visa & entry' => 'Visa and entry requirements explained for UAE residents.',
            'Umrah & Hajj' => 'Flights, hotels and practical tips for pilgrims.',
            'Travel tips' => 'Baggage, airports, eSIMs, insurance and money.',
            'Hotels & stays' => 'Where to stay and how to book smarter.',
        ])->mapWithKeys(fn ($desc, $name) => [$name => Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'description' => $desc])]);

        // Starter topic queue (add more from Semrush in the admin)
        $topics = [
            ['cheap flights from dubai to istanbul', 'Flight deals', 'DXB', 'IST'],
            ['best time to visit georgia from uae', 'Travel guides', 'DXB', 'TBS'],
            ['azerbaijan visa for uae residents', 'Visa & entry', null, null],
            ['dubai to karachi flights baggage allowance', 'Travel tips', 'DXB', 'KHI'],
            ['umrah from dubai complete guide', 'Umrah & Hajj', 'DXB', 'JED'],
            ['where to stay in istanbul first time', 'Hotels & stays', null, null],
            ['cheap weekend getaways from dubai', 'Travel guides', null, null],
            ['maldives on a budget from uae', 'Travel guides', 'DXB', 'MLE'],
            ['best travel esim for europe', 'Travel tips', null, null],
            ['schengen visa from uae step by step', 'Visa & entry', null, null],
            ['cheapest time to fly dubai to manila', 'Flight deals', 'DXB', 'MNL'],
            ['things to do in baku in 3 days', 'Travel guides', 'DXB', 'BAK'],
            ['dubai airport terminal guide', 'Travel tips', null, null],
            ['sharjah to kochi cheap flights', 'Flight deals', 'SHJ', 'COK'],
            ['madinah hotels near masjid nabawi', 'Umrah & Hajj', null, null],
            ['thailand travel guide for uae residents', 'Travel guides', 'DXB', 'BKK'],
            ['uk visit visa from dubai', 'Visa & entry', null, null],
            ['eid holiday trips from uae', 'Travel guides', null, null],
            ['travel insurance for schengen visa uae', 'Travel tips', null, null],
            ['abu dhabi to london flights guide', 'Flight deals', 'AUH', 'LHR'],
        ];
        foreach ($topics as $i => [$kw, $cat, $o, $d]) {
            Topic::firstOrCreate(['keyword' => $kw], [
                'category_id' => $cats[$cat]->id, 'origin_iata' => $o, 'destination_iata' => $d, 'priority' => $i + 1,
            ]);
        }
    }
}

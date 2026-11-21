<?php

namespace App\Console\Commands;

use App\Models\FlightRoute;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Services\Links;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendFareAlerts extends Command
{
    protected $signature = 'alerts:send';

    protected $description = 'Email subscribers when a watched route is cheaper than last time';

    public function handle(): int
    {
        $site = Setting::get('site_name');
        $sent = 0;
        Subscriber::where('is_active', true)->whereNotNull('destination_iata')
            ->where(fn ($q) => $q->whereNull('last_notified_at')->orWhere('last_notified_at', '<', now()->subDays(3)))
            ->chunkById(200, function ($subs) use ($site, &$sent) {
                foreach ($subs as $s) {
                    $route = FlightRoute::published()->where('origin_iata', $s->origin_iata)->where('destination_iata', $s->destination_iata)->first();
                    if (! $route?->min_price) {
                        continue;
                    }
                    $threshold = $s->target_price ?: ($s->last_notified_price ? (int) floor($s->last_notified_price * 0.95) : PHP_INT_MAX);
                    if ($route->min_price > $threshold) {
                        continue;
                    }
                    $title = "{$route->origin()?->city} to {$route->destination()?->city} from ".Links::money($route->min_price);
                    $html = view('emails.fare-alert', ['route' => $route, 'sub' => $s, 'site' => $site])->render();
                    try {
                        Mail::html($html, fn ($m) => $m->to($s->email)->subject("Fare alert: {$title}"));
                        $s->update(['last_notified_price' => $route->min_price, 'last_notified_at' => now()]);
                        $sent++;
                    } catch (\Throwable $e) {
                        $this->error($e->getMessage());
                    }
                }
            });
        $this->info("Sent {$sent} alerts.");

        return self::SUCCESS;
    }
}

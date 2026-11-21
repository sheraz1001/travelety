<?php

namespace App\Filament\Widgets;

use App\Models\Click;
use App\Models\FlightRoute;
use App\Models\Post;
use App\Models\Subscriber;
use App\Models\Topic;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $clicks7 = Click::where('created_at', '>=', now()->subDays(7))->count();
        $trend = collect(range(13, 0))->map(fn ($d) => Click::whereDate('created_at', now()->subDays($d))->count())->all();

        return [
            Stat::make('Booking clicks (7 days)', number_format($clicks7))->chart($trend)->color('success')
                ->description(Click::whereDate('created_at', today())->count().' today'),
            Stat::make('Live route pages', FlightRoute::published()->count().' / '.FlightRoute::count())
                ->description(FlightRoute::whereNotNull('content_generated_at')->count().' with written content'),
            Stat::make('Published articles', Post::live()->count())
                ->description(Post::whereIn('status', ['draft', 'review'])->count().' drafts · '.Topic::where('status', 'queued')->count().' topics queued'),
            Stat::make('Alert subscribers', Subscriber::where('is_active', true)->count()),
        ];
    }
}

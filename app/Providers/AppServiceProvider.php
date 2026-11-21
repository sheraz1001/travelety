<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        View::composer('*', function ($view) {
            static $site;
            try {
                $site ??= Setting::get('site_name', config('app.name'));
            } catch (\Throwable) {
                $site = config('app.name');
            }
            $view->with('site', $site);
        });
    }
}

<?php

use Illuminate\Support\Facades\Schedule;

// One server cron entry runs all of these:  * * * * * php artisan schedule:run
Schedule::command('fares:update --limit=150')->everyFourHours()->withoutOverlapping();
Schedule::command('routes:write')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('destinations:write --limit=3')->dailyAt('04:00')->withoutOverlapping();
Schedule::command('blog:daily')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('alerts:send')->dailyAt('09:00');
Schedule::command('esim:import')->dailyAt('05:15')->withoutOverlapping();
Schedule::command('esim:import')->everyTenMinutes()->withoutOverlapping()->when(fn () => ! \App\Models\EsimPlan::query()->exists());

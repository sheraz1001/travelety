<?php

/*
 | Runs database migrations + idempotent seeding once per deploy,
 | whenever the set of migration files changes. Guarded by a file lock.
 */

return function ($app): void {
    if (! defined('TRAVELETY_DATA')) {
        return;
    }
    $files = glob(dirname(__DIR__).'/database/migrations/*.php') ?: [];
    $stamp = TRAVELETY_DATA.'/migrated-'.md5(implode('|', array_map('basename', $files)).'|seed-v1');
    if (file_exists($stamp)) {
        return;
    }
    $lock = fopen(TRAVELETY_DATA.'/migrate.lock', 'c');
    if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
        return; // another request is migrating
    }
    try {
        @set_time_limit(300);
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->call('migrate', ['--force' => true]);
        $kernel->call('db:seed', ['--force' => true]);
        @file_put_contents($stamp, date('c'));
        foreach (glob(TRAVELETY_DATA.'/migrated-*') ?: [] as $old) {
            $old === $stamp || @unlink($old);
        }
    } catch (Throwable $e) {
        @file_put_contents(TRAVELETY_DATA.'/storage/logs/migrate-error.log', date('c').' '.$e->getMessage()."\n", FILE_APPEND);
    } finally {
        flock($lock, LOCK_UN);
    }
};

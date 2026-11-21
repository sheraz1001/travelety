<?php

/*
 | Self-provisioning for shared hosting (no SSH needed).
 | On first request it creates a private data folder OUTSIDE the deployed code
 | (so Git redeploys never wipe it) holding: .env (with a fresh APP_KEY),
 | the SQLite database, uploads, sessions, cache and logs.
 | Local development keeps using the normal ./.env and ./storage.
 */

$base = dirname(__DIR__);

if (file_exists($base.'/.env') || defined('TRAVELETY_DATA')) {
    return;
}

$data = getenv('TRAVELETY_DATA') ?: dirname($base).'/travelety-data';
if (! is_dir($data) && ! @mkdir($data, 0750, true)) {
    $data = $base.'/storage/travelety-data'; // fallback inside the app
    @mkdir($data, 0750, true);
}

foreach ([
    'storage/app/public', 'storage/app/private', 'storage/framework/cache/data',
    'storage/framework/sessions', 'storage/framework/views', 'storage/logs',
] as $dir) {
    is_dir("$data/$dir") || @mkdir("$data/$dir", 0755, true);
}

$db = "$data/database.sqlite";
file_exists($db) || @touch($db);

$env = "$data/.env";
if (! file_exists($env)) {
    $tpl = (string) @file_get_contents($base.'/.env.hosting');
    $tpl = preg_replace('/^APP_KEY=\s*$/m', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), $tpl);
    $tpl = preg_replace('/^DB_DATABASE=.*$/m', 'DB_DATABASE='.$db, $tpl);
    @file_put_contents($env, $tpl, LOCK_EX);
    @chmod($env, 0600);
}

// public/storage -> uploads folder (recreated if a redeploy removed it)
$link = $base.'/public/storage';
if (! is_link($link) && ! file_exists($link)) {
    @symlink("$data/storage/app/public", $link);
}

define('TRAVELETY_DATA', $data);

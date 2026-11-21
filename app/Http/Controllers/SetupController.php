<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * One-time server setup for hosts without SSH access.
 * Enabled only when SETUP_KEY is set in .env. Remove the key after setup.
 */
class SetupController extends Controller
{
    public function __invoke(Request $r, string $task)
    {
        $key = config('app.setup_key');
        abort_unless($key && hash_equals($key, (string) $r->query('key')), 404);

        $commands = match ($task) {
            'install' => [['migrate', ['--force' => true]], ['db:seed', ['--force' => true]], ['storage:link', []], ['optimize', []], ['filament:optimize', []]],
            'migrate' => [['migrate', ['--force' => true]], ['optimize', []]],
            'optimize' => [['optimize:clear', []], ['optimize', []], ['filament:optimize', []]],
            'fares' => [['fares:update', ['--limit' => (int) $r->query('limit', 40)]]],
            'destinations' => [['destinations:write', ['--limit' => (int) $r->query('limit', 2), '--publish' => true]]],
            'routes' => [['routes:write', ['--limit' => (int) $r->query('limit', 5)]]],
            'blog' => [['blog:daily', ['--force' => true]]],
            'admin' => [],
            default => abort(404),
        };
        if ($task === 'admin') {
            $u = \App\Models\User::firstOrNew(['email' => $r->query('email')]);
            abort_unless(filter_var($u->email, FILTER_VALIDATE_EMAIL) && strlen((string) $r->query('password')) >= 10, 422, 'email and password (10+ chars) required');
            $u->fill(['name' => 'Travelety Admin', 'password' => bcrypt($r->query('password'))])->save();

            return response('Admin saved for '.$u->email, 200)->header('Content-Type', 'text/plain');
        }

        @set_time_limit(600);
        $out = '';
        foreach ($commands as [$cmd, $args]) {
            try {
                Artisan::call($cmd, $args);
                $out .= "$ php artisan $cmd\n".Artisan::output()."\n";
            } catch (\Throwable $e) {
                $out .= "$ php artisan $cmd\nERROR: ".$e->getMessage()."\n\n";
            }
        }

        return response($out, 200)->header('Content-Type', 'text/plain');
    }
}

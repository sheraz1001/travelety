<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\Request;

class SubscribeController extends Controller
{
    public function store(Request $r)
    {
        if ($r->filled('website')) { // honeypot
            return back()->with('subscribed', true);
        }
        $data = $r->validate([
            'email' => 'required|email:rfc|max:190',
            'origin' => 'nullable|alpha|size:3',
            'destination' => 'nullable|alpha|size:3',
        ]);

        Subscriber::firstOrCreate(
            ['email' => strtolower($data['email']), 'origin_iata' => $data['origin'] ?? null, 'destination_iata' => $data['destination'] ?? null],
            ['is_active' => true]
        );

        return back()->with('subscribed', true)->withFragment('alerts');
    }

    public function unsubscribe(string $token)
    {
        Subscriber::where('token', $token)->update(['is_active' => false]);

        return response('<p style="font-family:sans-serif;padding:40px">You have been unsubscribed.</p>');
    }
}

<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#0d1b2a">
  <h2 style="margin:0 0 8px">{{ $route->origin()?->city }} → {{ $route->destination()?->city }}</h2>
  <p style="font-size:16px">Recent fares on the route you're watching are now from <b>{{ \App\Services\Links::money($route->min_price) }}</b>{{ $route->cheapest_month ? ', with '.\Carbon\Carbon::parse($route->cheapest_month.'-01')->format('F').' the cheapest month' : '' }}.</p>
  <p><a href="{{ $route->url() }}" style="display:inline-block;background:#ff6b3d;color:#fff;padding:12px 22px;border-radius:10px;text-decoration:none;font-weight:bold">See fares</a></p>
  <p style="font-size:12px;color:#5b6b7f">Prices change quickly and are confirmed when you search. You're receiving this because you asked {{ $site }} for fare alerts. <a href="{{ route('unsubscribe', $sub->token) }}">Unsubscribe</a>.</p>
</div>

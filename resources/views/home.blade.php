@extends('layouts.app')
@section('content')
<section class="hero">
  <svg class="plane-path" viewBox="0 0 560 300" fill="none"><path d="M10 280 C 160 120, 320 260, 540 30" stroke="#fff" stroke-width="2" stroke-dasharray="6 10"/><circle cx="10" cy="280" r="6" fill="#ff8a5c"/><path d="M528 40l18-16-6 22-6-4z" fill="#fff"/></svg>
  <div class="wrap hero-inner">
    <span class="eyebrow" style="color:#ffb38f">Flights · Hotels · Everything in between</span>
    <h1>The world is closer than your <em>next fare</em>.</h1>
    <p class="lead">Compare prices from hundreds of airlines and travel sites in seconds, then book direct with the one you like best.</p>
    @include('partials.search')
    <div class="hero-trust">
      <span>@include('partials.icon',['n'=>'check','sw'=>2.2])No {{ $site }} booking fees</span>
      <span>@include('partials.icon',['n'=>'check','sw'=>2.2])Hundreds of airlines compared</span>
      <span>@include('partials.icon',['n'=>'check','sw'=>2.2])Book direct with the provider</span>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="sec-head">
      <div><span class="eyebrow">Popular right now</span><h2>Top routes from the UAE</h2></div>
      <p>Recent lowest fares travellers found. Prices change daily, so set an alert if you're flexible.</p>
    </div>
    <div class="routes">
      @foreach($routes as $r)
        <a class="route" href="{{ $r->url() }}">
          <div class="codes">{{ $r->origin_iata }}@include('partials.icon',['n'=>'arrow','sw'=>2.4]){{ $r->destination_iata }}</div>
          <div class="cities">{{ $r->origin()?->city }} → {{ $r->destination()?->city }}</div>
          <div class="go">{{ $r->min_price ? 'From '.\App\Services\Links::money($r->min_price) : 'See fares' }} →</div>
        </a>
      @endforeach
    </div>
    <p style="margin-top:22px"><a href="{{ route('flights.index') }}" class="chip">All flight routes →</a></p>
  </div>
</section>

<section class="dest" id="destinations">
  <div class="wrap">
    <div class="sec-head">
      <div><span class="eyebrow">Get inspired</span><h2>Where to next?</h2></div>
      <p>From long weekends to the trip of a lifetime, pick a place and we'll find you a way there.</p>
    </div>
    @php $tags = ['City break','Beach','Trending',null,'Short haul','Umrah',null]; @endphp
    <div class="dest-grid">
      @foreach($featured as $i => $d)
        <a class="card c{{ $i+1 }}" href="{{ $d->is_published ? $d->guideUrl() : route('flights.to', $d->slug) }}">
          @if($tags[$i] ?? null)<span class="tag">{{ $tags[$i] }}</span>@endif
          <h3>{{ $d->city }}</h3><p>{{ $d->tagline ?: $d->country }}</p>
        </a>
      @endforeach
    </div>
  </div>
</section>

<section id="services">
  <div class="wrap">
    <div class="sec-head">
      <div><span class="eyebrow">Plan the whole trip</span><h2>Everything after the flight</h2></div>
      <p>The ticket is only the start. Sort the rest of your trip in a few taps.</p>
    </div>
    @include('partials.services')
  </div>
</section>

<section class="how">
  <div class="wrap">
    <div class="sec-head">
      <div><span class="eyebrow">How {{ $site }} works</span><h2>Search once. Compare everything.</h2></div>
      <p>We're a travel search engine. We do the comparing so you don't have to open twenty tabs.</p>
    </div>
    <div class="steps">
      <div class="step"><h3>Search</h3><p>Tell us where and when. We check fares from hundreds of airlines and travel agencies at once.</p></div>
      <div class="step"><h3>Compare</h3><p>Sort by price, duration or stops. Filter by airline, baggage and departure times.</p></div>
      <div class="step"><h3>Book direct</h3><p>Choose your deal and complete your booking securely with the airline or agency you picked.</p></div>
    </div>
  </div>
</section>

<section>
  <div class="wrap why">
    <div>
      <span class="eyebrow">Why travellers use us</span>
      <h2 style="font-size:clamp(30px,4vw,46px);margin-top:8px">Honest prices. Real choice. No surprises.</h2>
      <ul>
        <li><span class="ic">@include('partials.icon',['n'=>'money','sw'=>2.2])</span><div><b>We never add a fee</b><span>The price you see is the provider's price. Searching on {{ $site }} is free.</span></div></li>
        <li><span class="ic">@include('partials.icon',['n'=>'search','sw'=>2.2])</span><div><b>Every option side by side</b><span>Low-cost carriers, full-service airlines and online agencies, all in one list.</span></div></li>
        <li><span class="ic">@include('partials.icon',['n'=>'bell','sw'=>2.2])</span><div><b>Price alerts that work for you</b><span>Pick a route and we'll email you when fares drop.</span></div></li>
      </ul>
    </div>
    <div class="why-art" aria-hidden="true">
      <div class="ticket t1"><div class="row"><div><b>DXB</b><br><small>Dubai</small></div><svg width="40" viewBox="0 0 24 24" fill="none" stroke="#ff6b3d" stroke-width="2"><path d="M2 12h20M16 6l6 6-6 6"/></svg><div style="text-align:right"><b>IST</b><br><small>Istanbul</small></div></div><div class="line"></div><div class="row"><small>Direct · 4h 55m</small><span class="badge">Best value</span></div></div>
      <div class="ticket t2"><div class="row"><div><b>AUH</b><br><small>Abu Dhabi</small></div><svg width="40" viewBox="0 0 24 24" fill="none" stroke="#0e7c86" stroke-width="2"><path d="M2 12h20M16 6l6 6-6 6"/></svg><div style="text-align:right"><b>MLE</b><br><small>Malé</small></div></div><div class="line"></div><div class="row"><small>Direct · 4h 10m</small><span class="badge" style="background:#fff1eb;color:#d8501f">Fastest</span></div></div>
    </div>
  </div>
</section>

@if($posts->count())
<section style="background:var(--sand)">
  <div class="wrap">
    <div class="sec-head"><div><span class="eyebrow">From the blog</span><h2>Travel guides & deals</h2></div><p><a class="chip" href="{{ route('blog.index') }}">All articles →</a></p></div>
    <div class="grid-3">@foreach($posts as $post) @include('partials.post-card') @endforeach</div>
  </div>
</section>
@endif

@include('partials.alerts')

<section id="faq">
  <div class="wrap">
    <div class="sec-head" style="justify-content:center;text-align:center"><div><span class="eyebrow">Help</span><h2>Questions, answered</h2></div></div>
    <div class="faq">
      @include('partials.faqs', ['hideHeading' => true, 'faqs' => $faqs])
    </div>
  </div>
</section>
@endsection

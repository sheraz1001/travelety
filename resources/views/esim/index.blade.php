@extends('layouts.app')
@section('title', "Travel eSIM for 200+ Countries — Compare Data Plans | {$site}")
@section('description', 'Compare travel eSIM data plans for 200+ countries and regions. Prices from US$'.number_format($cheapest ?? 4.5, 2).'. Install before you fly and get online the moment you land.')
@section('content')
<section class="page-hero">
  <div class="wrap">
    @include('partials.crumbs', ['items' => ['eSIM' => null]])
    <h1>Travel eSIMs for {{ $all->where('group_type','country')->count() }}+ countries</h1>
    <p class="lead">Mobile data the moment you land. Compare {{ number_format($all->sum('plans')) }} plans by price, data and validity, then install the eSIM on your phone before you fly.</p>
    <div class="esim-search">
      @include('partials.icon',['n'=>'search','sw'=>2.2])
      <input id="esimFilter" type="search" placeholder="Where are you travelling? e.g. Turkey, Europe, Thailand" aria-label="Search destinations" autocomplete="off">
    </div>
    <div class="hero-trust">
      <span>@include('partials.icon',['n'=>'check','sw'=>2.2])No roaming bills</span>
      <span>@include('partials.icon',['n'=>'check','sw'=>2.2])Keep your home number for WhatsApp &amp; 2FA</span>
      <span>@include('partials.icon',['n'=>'check','sw'=>2.2])Plans from {{ \App\Services\Currency::show($cheapest ?? 4.5, 'USD') }}</span>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div id="esimResults" class="chips" style="display:none;margin-bottom:40px"></div>

    <div class="sec-head"><div><span class="eyebrow">Popular right now</span><h2>Most-searched eSIM destinations</h2></div><p>Prices are for the cheapest plan available. Tap a destination to compare every plan.</p></div>
    <div class="esim-grid">
      @foreach($popular as $d)
        <a class="esim-dest" href="{{ route('esim.show', $d->slug) }}">
          <b>{{ $d->name }}</b>
          <span>{{ $d->plans }} plans{{ $d->unlimited ? ' · unlimited available' : '' }}</span>
          <em>from {{ \App\Services\Currency::show($d->from_usd, 'USD') }}</em>
        </a>
      @endforeach
    </div>
  </div>
</section>

<section style="background:var(--sand)">
  <div class="wrap">
    <div class="sec-head"><div><span class="eyebrow">Multi-country</span><h2>Regional &amp; global eSIMs</h2></div><p>Visiting several countries? One eSIM covers the whole trip.</p></div>
    <div class="esim-grid">
      @foreach($multi as $d)
        <a class="esim-dest" href="{{ route('esim.show', $d->slug) }}">
          <b>{{ $d->name }}</b>
          <span>{{ $d->group_type === 'global' ? 'Works worldwide' : 'Multi-country' }} · {{ $d->plans }} plans</span>
          <em>from {{ \App\Services\Currency::show($d->from_usd, 'USD') }}</em>
        </a>
      @endforeach
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="sec-head"><div><span class="eyebrow">All destinations</span><h2>eSIMs by region</h2></div></div>
    @foreach($byRegion as $region => $items)
      <h3 style="font-family:Inter;font-size:20px;letter-spacing:0;margin:30px 0 14px">{{ $region }}</h3>
      <div class="chips">
        @foreach($items as $d)
          <a class="chip esim-chip" data-name="{{ strtolower($d->name) }}" href="{{ route('esim.show', $d->slug) }}">{{ $d->name }} <b>{{ \App\Services\Currency::show($d->from_usd, 'USD') }}</b></a>
        @endforeach
      </div>
    @endforeach
  </div>
</section>

<section class="how">
  <div class="wrap">
    <div class="sec-head"><div><span class="eyebrow">How it works</span><h2>Online in three steps</h2></div><p>Your phone needs to support eSIM. Most iPhones from the XS onwards and recent Samsung and Pixel phones do.</p></div>
    <div class="steps">
      <div class="step"><h3>Pick a plan</h3><p>Choose your destination, how much data you need and for how many days.</p></div>
      <div class="step"><h3>Install before you fly</h3><p>Buy and scan the QR code while you're still on Wi-Fi at home. It takes about two minutes.</p></div>
      <div class="step"><h3>Land and connect</h3><p>Switch to the eSIM for data when you arrive. Your home SIM stays in for calls, WhatsApp and bank codes.</p></div>
    </div>
  </div>
</section>

<section id="faq">
  <div class="wrap faq">
    @include('partials.faqs', ['faqs' => [
      ['q' => 'What is a travel eSIM?', 'a' => 'An eSIM is a digital SIM card built into your phone. A travel eSIM gives you a local data plan for your destination without buying a physical SIM or paying roaming charges.'],
      ['q' => 'Will I keep my home number?', 'a' => 'Yes. Your normal SIM stays active, so you can still receive calls, SMS and verification codes. Most travel eSIMs are data-only, so you use the eSIM for internet and your home line for calls and texts.'],
      ['q' => 'When does the validity period start?', 'a' => 'For most plans, validity starts when the eSIM first connects to a network in the covered country, not when you buy it. Check the details on each plan before you buy.'],
      ['q' => 'Who do I buy the eSIM from?', 'a' => 'You complete your purchase with our eSIM partner, who delivers the eSIM by email and in their app and handles support and top-ups.'],
    ]])
  </div>
</section>
@push('scripts')
<script>
(function(){
  var input=document.getElementById('esimFilter'), out=document.getElementById('esimResults');
  var chips=[].slice.call(document.querySelectorAll('.esim-chip'));
  input.addEventListener('input',function(){
    var q=input.value.trim().toLowerCase();
    if(!q){out.style.display='none';out.innerHTML='';return;}
    var m=chips.filter(function(c){return c.dataset.name.indexOf(q)>-1}).slice(0,24);
    out.innerHTML=m.length?m.map(function(c){return c.outerHTML}).join(''):'<span class="note">No destination found. Try a regional or global eSIM below.</span>';
    out.style.display='flex';
  });
})();
</script>
@endpush
@endsection

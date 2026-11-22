@extends('layouts.app')
@section('title', "Cheap Flights Worldwide — Search & Book Airline Tickets | {$site}")
@section('description', 'Search cheap flights to anywhere. Compare airlines, choose your seat, add bags and book round-trip, one-way or multi-city flights in your currency.')
@php
  $I = fn($n,$sw=1.9) => view('partials.icon',['n'=>$n,'sw'=>$sw])->render();
  $cur = \App\Services\Currency::current();
@endphp
@section('content')
<section class="ph-hero sv-hero">
  <div class="ph-bg"><img src="{{ asset('img/home/wing.jpg') }}" class="on" alt=""></div>
  <div class="wrap ph-hero-in">
    @include('partials.crumbs', ['items' => ['Flights' => null]])
    <h1>Cheap flights to <em>anywhere</em> in the world</h1>
    <p class="sub">Compare fares across airlines, then book in a few steps — with seats, bags and meals added your way.</p>
    <div class="sv-pills">
      <span>{!! $I('check',2.4) !!}Round trip, one way &amp; multi-city</span>
      <span>{!! $I('check',2.4) !!}Economy to First</span>
      <span>{!! $I('check',2.4) !!}Prices in {{ $cur }}</span>
    </div>
  </div>
</section>

<div class="wrap ph-search">
  @include('partials.search', ['show' => ['flights']])

  <div class="sv-feats">
    @if($inSite)
      <div><span class="ic">{!! $I('shield') !!}</span><b>Secure checkout</b><small>Pay on {{ $site }}, ticket issued by the airline</small></div>
      <div><span class="ic">{!! $I('ticket') !!}</span><b>Seats, bags &amp; meals</b><small>Add extras per passenger before you pay</small></div>
      <div><span class="ic">{!! $I('globe') !!}</span><b>Multi-city trips</b><small>Up to five flights in one booking</small></div>
      <div><span class="ic">{!! $I('bell') !!}</span><b>Instant confirmation</b><small>Booking reference and PNR by email</small></div>
    @else
      <div><span class="ic">{!! $I('search') !!}</span><b>One search</b><small>Fares from many airlines and agencies</small></div>
      <div><span class="ic">{!! $I('tag') !!}</span><b>Cheapest months</b><small>See when each route is cheapest</small></div>
      <div><span class="ic">{!! $I('bell') !!}</span><b>Price alerts</b><small>Get an email when fares drop</small></div>
      <div><span class="ic">{!! $I('shield') !!}</span><b>Trusted partners</b><small>Book with the airline or agency</small></div>
    @endif
  </div>
</div>

<main class="wrap ph-main">
  @if($popular->count())
  <section class="blk rv">
    <div class="sh"><div><h2>Popular flights from {{ $fromCity }}</h2><p>Tap a destination to see live fares for your dates</p></div><span class="sp"></span><a href="{{ route('travel.index') }}" class="more">All destinations {!! $I('arrow') !!}</a></div>
    <div class="sv-dest">
      @foreach($popular as $d)
        @php $pr = $prices[$d->iata] ?? null; @endphp
        <a class="sv-dc" href="{{ route('go.flights', ['from' => $from, 'to' => $d->iata]) }}" rel="nofollow">
          <div class="im"><img src="{{ $d->photo() }}" alt="{{ $d->city }}" loading="lazy"><span class="code">{{ $from }} {!! $I('plane',2) !!} {{ $d->iata }}</span></div>
          <div class="bd">
            <div><h3>{{ $d->city }}</h3><p>{{ $d->country }}</p></div>
            <div class="pr">@if($pr)<small>Round trip from</small><b>{{ \App\Services\Links::money($pr->min_price) }}</b>@else<small>Live fares</small><b class="see">Search {!! $I('arrow',2.2) !!}</b>@endif</div>
          </div>
        </a>
      @endforeach
    </div>
  </section>
  @endif

  <section class="blk rv">
    <div class="sh"><div><h2>Fly the way you like</h2><p>Pick a cabin, then search any route</p></div></div>
    <div class="sv-cabins">
      <button type="button" data-cabin="0" class="c0"><span class="ic">{!! $I('user') !!}</span><b>Economy</b><small>The best value for every trip, with bag and seat add-ons.</small><em>Search economy {!! $I('arrow',2.2) !!}</em></button>
      <button type="button" data-cabin="3" class="c3"><span class="ic">{!! $I('ticket') !!}</span><b>Premium Economy</b><small>More legroom, bigger seats and extra baggage allowance.</small><em>Search premium {!! $I('arrow',2.2) !!}</em></button>
      <button type="button" data-cabin="1" class="c1"><span class="ic">{!! $I('bed') !!}</span><b>Business</b><small>Lie-flat seats on long-haul, lounge access and priority boarding.</small><em>Search business {!! $I('arrow',2.2) !!}</em></button>
      <button type="button" data-cabin="2" class="c2"><span class="ic">{!! $I('key') !!}</span><b>First</b><small>Private suites and the most generous allowances in the sky.</small><em>Search first {!! $I('arrow',2.2) !!}</em></button>
    </div>
  </section>

  @if($routes->count())
  <section class="blk rv">
    <div class="sh"><div><h2>Popular flight routes</h2><p>Lowest recent fares travellers found. Prices change daily.</p></div></div>
    <div class="routes2">
      @foreach($routes->take(9) as $r)
        @php $dst = $r->destination(); @endphp
        <a class="route2" href="{{ $r->url() }}">
          <img src="{{ asset('img/home/'.(\App\Models\Destination::PHOTOS[$dst?->city_slug] ?? ['mountains','beach','road','santorini','wing'][$loop->index % 5]).'.jpg') }}" alt="" loading="lazy">
          <div class="t"><b>{{ $r->origin()?->city }} {!! $I('plane') !!} {{ $dst?->city }}</b><small>{{ $r->origin_iata }} – {{ $r->destination_iata }} · Round trip</small></div>
          <span class="go">{{ $r->min_price ? 'From '.\App\Services\Links::money($r->min_price) : 'See fares' }} →</span>
        </a>
      @endforeach
    </div>
  </section>
  @endif

  @if($origins->count())
  <section class="blk rv">
    <div class="sh"><div><h2>Flights from your city</h2><p>Routes, fares and the cheapest months to fly</p></div></div>
    <div class="sv-origins">
      @foreach($origins as $o)
        <a href="{{ route('flights.from', $o->city_slug) }}"><span class="code">{{ $o->iata }}</span><div><b>Flights from {{ $o->city }}</b><small>{{ $o->name ?? $o->city }}</small></div>{!! $I('right',2.2) !!}</a>
      @endforeach
    </div>
  </section>
  @endif

  <section class="blk rv">
    <div class="sv-band">
      <div>
        <small class="kick">SMART BOOKING</small>
        <h2>Tips for a cheaper flight</h2>
        <p>A few habits that save money on almost every route.</p>
      </div>
      <ol class="sv-tips">
        <li><b>Book 6–10 weeks ahead</b><span>The sweet spot for most international fares. Book holidays and Eid earlier.</span></li>
        <li><b>Be flexible by a day</b><span>Midweek departures are often cheaper than Friday or Sunday.</span></li>
        <li><b>Compare one-way and return</b><span>Two one-way tickets on different airlines can beat a return fare.</span></li>
        <li><b>Check what's included</b><span>A cheap fare without checked bags can end up costing more. Use the baggage filter.</span></li>
      </ol>
    </div>
  </section>

  @if($byCountry->count())
  <section class="blk rv">
    <div class="sh"><div><h2>Flights by country</h2><p>Every route we track, with recent lowest fares</p></div></div>
    <div class="sv-countries">
      @foreach($byCountry as $country => $rs)
        <div><h3>{{ $country }}</h3><ul>@foreach($rs as $r)<li><a href="{{ $r->url() }}">{{ $r->origin()?->city }} → {{ $r->destination()?->city }}</a>@if($r->min_price)<span>{{ \App\Services\Links::money($r->min_price) }}</span>@endif</li>@endforeach</ul></div>
      @endforeach
    </div>
  </section>
  @endif
</main>

<section class="ph-faq">
  <div class="wrap">
    <div class="sh" style="justify-content:center;text-align:center"><div><h2>Flight booking questions</h2></div></div>
    <div class="faq">@include('partials.faqs', ['hideHeading' => true, 'faqs' => $faqs])</div>
  </div>
</section>
@endsection

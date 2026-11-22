@extends('layouts.app')
@section('title', "Hotels Worldwide — Compare & Book Hotel Deals | {$site}")
@section('description', 'Search and book hotels, resorts and apartments worldwide. Filter by price, rating and location on a map, see free-cancellation rates and pay in your currency.')
@php
  $I = fn($n,$sw=1.9) => view('partials.icon',['n'=>$n,'sw'=>$sw])->render();
  $cur = \App\Services\Currency::current();
  $hUrl = fn($city) => route('go.hotels', ['city' => $city]);
  $trips = [
    ['Beach & islands', 'beach', 'Overwater villas, sea-view rooms and all-inclusive resorts.', ['Maldives', 'Bali', 'Phuket', 'Santorini']],
    ['City breaks', 'london', 'Central hotels close to sights, shopping and transport.', ['London', 'Paris', 'Istanbul', 'New York']],
    ['Family stays', 'dubai', 'Family rooms, pools and kids\' clubs near the attractions.', ['Dubai', 'Singapore', 'Antalya', 'Orlando']],
    ['Luxury escapes', 'santorini', 'Five-star hotels and resorts with spa and fine dining.', ['Dubai', 'Paris', 'Maldives', 'Tokyo']],
  ];
@endphp
@section('content')
<section class="ph-hero sv-hero">
  <div class="ph-bg"><img src="{{ asset('img/home/maldives.jpg') }}" class="on" alt=""></div>
  <div class="wrap ph-hero-in">
    @include('partials.crumbs', ['items' => ['Hotels' => null]])
    <h1>Hotels for every trip, <em>booked in minutes</em></h1>
    <p class="sub">From city rooms to beach resorts — compare real-time rates, see every hotel on the map and book the room that fits.</p>
    <div class="sv-pills">
      <span>{!! $I('check',2.4) !!}Free-cancellation rates</span>
      <span>{!! $I('check',2.4) !!}Map &amp; smart filters</span>
      <span>{!! $I('check',2.4) !!}Prices in {{ $cur }}</span>
    </div>
  </div>
</section>

<div class="wrap ph-search">
  @include('partials.search', ['tab' => 'hotels', 'show' => ['hotels']])

  <div class="sv-feats">
    @if($inSite)
      <div><span class="ic">{!! $I('shield') !!}</span><b>Secure checkout</b><small>Pay on {{ $site }} with instant confirmation</small></div>
      <div><span class="ic">{!! $I('pin') !!}</span><b>Map view</b><small>See exactly where you'll stay</small></div>
      <div><span class="ic">{!! $I('list') !!}</span><b>Detailed filters</b><small>Price, stars, score, meals and facilities</small></div>
      <div><span class="ic">{!! $I('clock') !!}</span><b>Flexible rates</b><small>Cancellation terms on every room</small></div>
    @else
      <div><span class="ic">{!! $I('search') !!}</span><b>Compare stays</b><small>Hotels, resorts and apartments</small></div>
      <div><span class="ic">{!! $I('pin') !!}</span><b>Area guides</b><small>Know where to stay before you book</small></div>
      <div><span class="ic">{!! $I('clock') !!}</span><b>Flexible rates</b><small>Many rooms with free cancellation</small></div>
      <div><span class="ic">{!! $I('shield') !!}</span><b>Trusted partners</b><small>Book with established providers</small></div>
    @endif
  </div>
</div>

<main class="wrap ph-main">
  @if($popular->count())
  <section class="blk rv">
    <div class="sh"><div><h2>Popular hotel destinations</h2><p>Live availability — pick your dates on the results page</p></div></div>
    <div class="sv-dest">
      @foreach($popular as $d)
        <a class="sv-dc" href="{{ $hUrl($d->city) }}" rel="nofollow">
          <div class="im"><img src="{{ $d->photo() }}" alt="Hotels in {{ $d->city }}" loading="lazy"><span class="code">{!! $I('bed',2) !!} Hotels</span></div>
          <div class="bd">
            <div><h3>{{ $d->city }}</h3><p>{{ $d->country }}</p></div>
            <div class="pr"><small>Live prices</small><b class="see">See hotels {!! $I('arrow',2.2) !!}</b></div>
          </div>
        </a>
      @endforeach
    </div>
  </section>
  @endif

  <section class="blk rv">
    <div class="sh"><div><h2>Stay your way</h2><p>Ideas for the kind of trip you're planning</p></div></div>
    <div class="sv-trips">
      @foreach($trips as [$t, $ph, $txt, $cities])
        <div class="sv-trip">
          <img src="{{ asset('img/home/'.$ph.'.jpg') }}" alt="" loading="lazy">
          <div class="in"><h3>{{ $t }}</h3><p>{{ $txt }}</p>
            <div class="cl">@foreach($cities as $c)<a href="{{ $hUrl($c) }}" rel="nofollow">{{ $c }}</a>@endforeach</div>
          </div>
        </div>
      @endforeach
    </div>
  </section>

  @if($destinations->count())
  <section class="blk rv">
    <div class="sh"><div><h2>Where to stay guides</h2><p>The best areas, when to book and what to expect</p></div></div>
    <div class="sv-guides">
      @foreach($destinations->take(12) as $d)
        <a href="{{ $d->hotelsUrl() }}"><img src="{{ $d->photo() }}" alt="" loading="lazy"><div><b>Where to stay in {{ $d->city }}</b><small>{{ $d->country }}</small></div>{!! $I('right',2.2) !!}</a>
      @endforeach
    </div>
  </section>
  @endif

  <section class="blk rv">
    <div class="sv-band orange">
      <div>
        <small class="kick">BOOK SMARTER</small>
        <h2>How to get a better room for less</h2>
        <p>Simple checks before you pay.</p>
      </div>
      <ol class="sv-tips">
        <li><b>Use the map</b><span>A cheaper hotel far from the centre can cost more in taxis and time.</span></li>
        <li><b>Lock in free cancellation</b><span>Book a flexible rate early, then keep an eye on prices.</span></li>
        <li><b>Compare board types</b><span>Breakfast included often beats paying for it at the hotel.</span></li>
        <li><b>Read the guest score</b><span>A score of 8+ from many reviews says more than star rating alone.</span></li>
      </ol>
    </div>
  </section>
</main>

<section class="ph-faq">
  <div class="wrap">
    <div class="sh" style="justify-content:center;text-align:center"><div><h2>Hotel booking questions</h2></div></div>
    <div class="faq">@include('partials.faqs', ['hideHeading' => true, 'faqs' => $faqs])</div>
  </div>
</section>
@endsection

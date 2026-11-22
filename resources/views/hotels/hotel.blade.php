@extends('layouts.app')
@section('title', ($h['name'] ?? 'Hotel').' — Rooms & prices | '.$site)
@section('noindex', '1')
@php
  $I = fn($n,$sw=1.9) => view('partials.icon',['n'=>$n,'sw'=>$sw])->render();
  $m = fn($a,$c) => $c.' '.number_format($a, 2);
  $imgs = collect($h['hotelImages'] ?? [])->sortBy('order')->pluck('url')->filter()->unique()->values();
  if ($imgs->isEmpty() && !empty($h['main_photo'])) $imgs = collect([$h['main_photo']]);
  $fac = collect($h['hotelFacilities'] ?? [])->take(16);
  $poi = collect($h['poi'] ?? [])->take(6);
  $times = $h['checkinCheckoutTimes'] ?? [];
  $stars = $h['starRating'] ?? ($h['stars'] ?? null);
  $sent = $h['sentiment_analysis'] ?? null;
@endphp
@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
@endpush
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>(function(){var el=document.getElementById('hotelMap');if(!el||!window.L)return;var ll=[+el.dataset.lat,+el.dataset.lng];var m=L.map(el,{scrollWheelZoom:false}).setView(ll,15);L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(m);L.marker(ll,{icon:L.divIcon({className:'pmk on',html:'<span>Hotel</span>',iconSize:null})}).addTo(m);})();</script>
@endpush
@section('content')
<section class="hs-top">
  <div class="wrap">
    <div class="search hs-search">@include('hotels.partials.form', ['p' => $p + ['q' => $p['q'] ?: ($h['city'] ?? '')]])</div>
  </div>
</section>

<div class="wrap hs-wrap">
  @if($sandbox)<div class="hs-test">Test mode: prices are real but bookings are not sent to hotels and no card is charged.</div>@endif
  <p class="crumbs2"><a href="{{ route('hotels.search', $qs) }}">← Back to results</a></p>

  <div class="hd-head">
    <div>
      <h1>{{ $h['name'] }}</h1>
      <p class="hcard-addr">@if($stars)<span class="stars">{{ str_repeat('★', (int) round($stars)) }}</span>@endif {!! $I('pin') !!}{{ trim(($h['address'] ?? '').', '.($h['city'] ?? ''), ', ') }}</p>
    </div>
    @if(!empty($h['rating']))<div class="rbadge big"><b>{{ number_format($h['rating'],1) }}</b><span>Guest rating<small>{{ number_format($h['reviewCount'] ?? 0) }} reviews</small></span></div>@endif
  </div>

  @if($imgs->count())
  <div class="gal gal-{{ min(5, $imgs->count()) }}">
    @foreach($imgs->take(5) as $i => $src)<div class="gi gi{{ $i }}"><img src="{{ $src }}" alt="{{ $h['name'] }}" loading="{{ $i ? 'lazy' : 'eager' }}">@if($i===4 && $imgs->count()>5)<span class="more">+{{ $imgs->count()-5 }} photos</span>@endif</div>@endforeach
  </div>
  @endif

  <div class="hd-grid">
    <div>
      <section class="hd-sec" id="rooms">
        <h2>Choose your room</h2>
        <p class="note">{{ \Carbon\Carbon::parse($p['checkin'])->format('D j M') }} – {{ \Carbon\Carbon::parse($p['checkout'])->format('D j M Y') }} · {{ $p['nights'] }} night{{ $p['nights']>1?'s':'' }} · {{ $p['adults'] }} adult{{ $p['adults']>1?'s':'' }} · {{ $p['rooms'] }} room{{ $p['rooms']>1?'s':'' }}</p>
        @if(session('room_error'))<div class="hs-alert">{{ session('room_error') }}</div>@endif
        @if($error)<div class="hs-alert">{{ $error }}</div>@endif
        @if(empty($rooms) && ! $error)<div class="hs-empty"><h3>No rooms left for these dates</h3><p>Try other dates or <a href="{{ route('hotels.search', $qs) }}">see other hotels</a>.</p></div>@endif
        <div class="rooms">
          @foreach($rooms as $r)
            <form class="room" method="post" action="{{ route('hotels.prebook', ['hotelId' => $hotelId] + $qs) }}">
              @csrf
              <input type="hidden" name="offerId" value="{{ $r['offerId'] }}">
              <div class="room-info">
                <h3>{{ $r['name'] }}</h3>
                <ul>
                  <li class="{{ str_contains(strtolower($r['board']), 'breakfast') ? 'g' : '' }}">{!! $I('check',2.2) !!}{{ $r['board'] }}</li>
                  @if($r['refundable'])<li class="g">{!! $I('check',2.2) !!}Free cancellation{{ $r['cancelUntil'] ? ' until '.\Carbon\Carbon::parse($r['cancelUntil'])->format('j M Y, H:i') : '' }}</li>
                  @else<li class="r">Non-refundable</li>@endif
                  @if($r['maxOccupancy'])<li>{!! $I('user') !!}Sleeps {{ $r['maxOccupancy'] }}</li>@endif
                  @foreach($r['payAtHotel'] as $t)<li class="note">Pay at hotel: {{ $t['desc'] }}@if($t['amount']) ({{ $t['currency'] }} {{ number_format($t['amount'],2) }})@endif</li>@endforeach
                </ul>
              </div>
              <div class="room-price">
                @if($r['public'] && $r['public'] > $r['amount'] * 1.03)<s>{{ $m($r['public'], $r['currency']) }}</s>@endif
                <b>{{ $m($r['amount'], $r['currency']) }}</b>
                <small>total for {{ $p['nights'] }} night{{ $p['nights']>1?'s':'' }}, taxes included</small>
                <button class="hbtn hbtn-orange" type="submit">Reserve</button>
              </div>
            </form>
          @endforeach
        </div>
      </section>

      @if(!empty($h['hotelDescription']))
      <section class="hd-sec"><h2>About this property</h2><div class="prose hd-desc">{!! strip_tags($h['hotelDescription'], '<p><br><strong><b><ul><li><em>') !!}</div></section>
      @endif

      @if($fac->count())
      <section class="hd-sec"><h2>Popular facilities</h2><div class="facs">@foreach($fac as $f)<span>{!! $I('check',2.2) !!}{{ $f }}</span>@endforeach</div></section>
      @endif

      @if($sent && (!empty($sent['pros']) || !empty($sent['cons'])))
      <section class="hd-sec"><h2>What guests say</h2>
        <div class="pc">
          @if(!empty($sent['pros']))<div><h4>Liked</h4><ul>@foreach(array_slice($sent['pros'],0,5) as $x)<li>{{ $x }}</li>@endforeach</ul></div>@endif
          @if(!empty($sent['cons']))<div><h4>Could be better</h4><ul>@foreach(array_slice($sent['cons'],0,5) as $x)<li>{{ $x }}</li>@endforeach</ul></div>@endif
        </div>
      </section>
      @endif

      @if(!empty($h['hotelImportantInformation']))
      <section class="hd-sec"><h2>Good to know</h2><div class="note" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($h['hotelImportantInformation'], 1500) }}</div></section>
      @endif
    </div>

    <aside class="hd-side">
      @if(!empty($h['location']['latitude']))
      <div class="hs-box hd-map"><div id="hotelMap" data-lat="{{ $h['location']['latitude'] }}" data-lng="{{ $h['location']['longitude'] }}"></div>
        <a class="note" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query={{ $h['location']['latitude'] }},{{ $h['location']['longitude'] }}">Open in Google Maps →</a></div>
      @endif
      <div class="hs-box">
        <h4>Check-in &amp; check-out</h4>
        <p class="note">Check-in from <b>{{ $times['checkin_start'] ?? '—' }}</b><br>Check-out until <b>{{ $times['checkout'] ?? '—' }}</b></p>
        @if(!empty($h['securityDeposit']['amount']))<p class="note">Security deposit at the hotel: {{ $h['securityDeposit']['currency'] ?? '' }} {{ number_format($h['securityDeposit']['amount']) }}</p>@endif
      </div>
      @if($poi->count())
      <div class="hs-box"><h4>Nearby</h4>@foreach($poi as $x)<p class="note poi"><span>{{ $x['name'] }}</span><b>{{ $x['distanceKm'] ?? '' }} km</b></p>@endforeach</div>
      @endif
      <a href="#rooms" class="hbtn hbtn-orange" style="width:100%">See available rooms</a>
    </aside>
  </div>
</div>
@endsection

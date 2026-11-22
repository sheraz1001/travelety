@extends('layouts.app')
@section('title', ($p['q'] ? 'Hotels in '.$p['q'] : 'Hotel search').' — '.\Carbon\Carbon::parse($p['checkin'])->format('j M').' to '.\Carbon\Carbon::parse($p['checkout'])->format('j M').' | '.$site)
@section('noindex', '1')
@php
  $I = fn($n,$sw=1.9) => view('partials.icon',['n'=>$n,'sw'=>$sw])->render();
  $m = fn($a,$c) => $c.' '.number_format($a, $a >= 1000 ? 0 : 2);
  $link = fn($extra = []) => route('hotels.search', array_merge($qs, ['sort' => $sort], $extra));
@endphp
@section('content')
<section class="hs-top">
  <div class="wrap">
    <div class="search hs-search">@include('hotels.partials.form', ['p' => $p])</div>
  </div>
</section>

<div class="wrap hs-wrap">
  @if($sandbox)<div class="hs-test">Test mode: prices are real but bookings are not sent to hotels and no card is charged.</div>@endif

  <div class="hs-grid">
    <aside class="hs-side">
      <div class="hs-box">
        <h4>Your stay</h4>
        <p><b>{{ $p['q'] ?: 'Choose a destination' }}</b></p>
        <p class="note">{{ \Carbon\Carbon::parse($p['checkin'])->format('D j M') }} – {{ \Carbon\Carbon::parse($p['checkout'])->format('D j M') }} · {{ $p['nights'] }} night{{ $p['nights']>1?'s':'' }}<br>{{ $p['adults'] }} adult{{ $p['adults']>1?'s':'' }} · {{ $p['rooms'] }} room{{ $p['rooms']>1?'s':'' }}</p>
      </div>
      <form class="hs-box" method="get" action="{{ route('hotels.search') }}">
        @foreach($qs as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        <input type="hidden" name="sort" value="{{ $sort }}">
        <h4>Star rating</h4>
        @foreach([5,4,3,2] as $s)
          <label class="hs-check"><input type="checkbox" name="stars[]" value="{{ $s }}" @checked(in_array($s,$stars)) onchange="this.form.submit()"> {{ str_repeat('★', $s) }}</label>
        @endforeach
        <h4 style="margin-top:14px">Booking options</h4>
        <label class="hs-check"><input type="checkbox" name="refundable" value="1" @checked(request()->boolean('refundable')) onchange="this.form.submit()"> Free cancellation</label>
      </form>
      <div class="hs-box hs-trust">
        <div>{!! $I('shield') !!}<span>Secure payment</span></div>
        <div>{!! $I('check',2.2) !!}<span>Instant confirmation email</span></div>
        <div>{!! $I('help') !!}<span>Support before and after your stay</span></div>
      </div>
    </aside>

    <main>
      <div class="hs-head">
        <div>
          <h1>{{ $p['q'] ? 'Hotels in '.$p['q'] : 'Find your hotel' }}</h1>
          @if($searched)<p class="note">{{ $hotels->count() }} properties with rooms available · prices for {{ $p['nights'] }} night{{ $p['nights']>1?'s':'' }}, taxes included</p>@endif
        </div>
        @if($hotels->count())
        <div class="hs-sort">
          @foreach(['recommended'=>'Recommended','price'=>'Lowest price','rating'=>'Guest rating','stars'=>'Stars'] as $k => $l)
            <a href="{{ $link(['sort'=>$k] + ($stars ? ['stars'=>$stars] : []) + (request()->boolean('refundable') ? ['refundable'=>1] : [])) }}" class="{{ $sort === $k ? 'on' : '' }}">{{ $l }}</a>
          @endforeach
        </div>
        @endif
      </div>

      @if($error)
        <div class="hs-empty"><h3>We couldn't load hotels right now</h3><p>{{ $error }}</p></div>
      @elseif(! $searched)
        <div class="hs-empty"><h3>Where are you going?</h3><p>Type a city, area or landmark above to see hotels with live prices.</p></div>
      @elseif($hotels->isEmpty())
        <div class="hs-empty"><h3>No rooms available</h3><p>Try different dates, fewer filters or a nearby area.</p></div>
      @endif

      <div class="hs-list">
        @foreach($hotels as $h)
          @php $url = route('hotels.stay', ['hotelId' => $h['id']] + $qs); $pr = $h['price']; @endphp
          <a class="hcard" href="{{ $url }}">
            <div class="hcard-img">@if($h['photo'])<img src="{{ $h['photo'] }}" alt="{{ $h['name'] }}" loading="lazy">@endif</div>
            <div class="hcard-bd">
              <div class="hcard-top">
                <div>
                  <h3>{{ $h['name'] }}</h3>
                  @if($h['stars'])<span class="stars">{{ str_repeat('★', (int) round($h['stars'])) }}</span>@endif
                  <p class="hcard-addr">{!! $I('pin') !!}{{ trim(($h['address'] ?? '').($h['city'] ? ', '.$h['city'] : ''), ', ') }}</p>
                </div>
                @if($h['rating'])
                  <div class="rbadge"><b>{{ number_format($h['rating'], 1) }}</b><span>{{ $h['rating'] >= 9 ? 'Exceptional' : ($h['rating'] >= 8 ? 'Excellent' : ($h['rating'] >= 7 ? 'Very good' : 'Good')) }}@if($h['reviews'])<small>{{ number_format($h['reviews']) }} reviews</small>@endif</span></div>
                @endif
              </div>
              <div class="hcard-foot">
                <div class="hcard-tags">
                  @if($pr['room'])<span class="t">{{ $pr['room'] }}</span>@endif
                  @if($pr['board'])<span class="t {{ str_contains(strtolower($pr['board']), 'breakfast') ? 'g' : '' }}">{{ $pr['board'] }}</span>@endif
                  <span class="t {{ $pr['refundable'] ? 'g' : '' }}">{{ $pr['refundable'] ? 'Free cancellation' : 'Non-refundable' }}</span>
                </div>
                <div class="hcard-price">
                  @if($pr['public'] && $pr['public'] > $pr['amount'] * 1.03)<s>{{ $m($pr['public'], $pr['currency']) }}</s>@endif
                  <b>{{ $m($pr['amount'], $pr['currency']) }}</b>
                  <small>{{ $m($pr['amount'] / max(1, $p['nights']), $pr['currency']) }} per night</small>
                  <span class="hbtn hbtn-blue">See rooms</span>
                </div>
              </div>
            </div>
          </a>
        @endforeach
      </div>
    </main>
  </div>
</div>
@endsection

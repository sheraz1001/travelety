@extends('layouts.app')
@php $place = $airport ? $airport->city : null; @endphp
@section('title', ($place ? "{$place} Airport Transfers — Fixed-Price Private Taxi" : "Airport Transfers Worldwide — Fixed-Price Private Rides")." | {$site}")
@section('description', $place ? "Book a private transfer from {$airport->name} ({$airport->iata}) to your hotel. Fixed price, driver waiting at arrivals, free waiting time." : 'Book fixed-price private airport transfers in 150+ countries. A driver meets you at arrivals with a name sign. Compare cars, vans and minibuses.')
@if(request()->filled('to')) @section('noindex', '1') @endif
@section('content')
<section class="page-hero">
  <div class="wrap">
    @include('partials.crumbs', ['items' => $place ? ['Transfers' => route('transfers.index'), $place.' Airport' => null] : ['Transfers' => null]])
    <h1>{{ $place ? "{$place} airport transfers" : 'Airport transfers, sorted before you land' }}</h1>
    <p class="lead">A private driver waiting at arrivals with your name, a fixed price agreed in advance and free waiting time if your flight is late.</p>
    <form class="search transfer-form" method="get" action="{{ route('transfers.index') }}">
      <div class="trip-type" style="padding:10px 10px 0">
        <label><input type="radio" name="dir" value="from" @checked($direction === 'from')> From the airport</label>
        <label><input type="radio" name="dir" value="to" @checked($direction === 'to')> To the airport</label>
      </div>
      <div class="fields" style="grid-template-columns:1.1fr 1.6fr 1.1fr .7fr auto;padding:14px">
        <div class="field"><label for="tAirport">Airport</label>
          <input id="tAirport" name="airport" list="airportList" value="{{ $airport?->iata }}" placeholder="e.g. DXB" required>
          <small>{{ $airport ? $airport->iata.' · '.$airport->name : 'City or airport code' }}</small>
          <datalist id="airportList"></datalist>
        </div>
        <div class="field"><label for="tTo">Hotel or address</label><input id="tTo" name="to" value="{{ $to }}" placeholder="e.g. Atlantis The Palm" required><small>&nbsp;</small></div>
        <div class="field"><label for="tDate">Pick-up</label><input id="tDate" type="datetime-local" name="date" value="{{ $date->format('Y-m-d\TH:i') }}" min="{{ now()->addHours(6)->format('Y-m-d\TH:i') }}"><small>&nbsp;</small></div>
        <div class="field"><label for="tPax">Passengers</label><select id="tPax" name="pax">@for($i=1;$i<=16;$i++)<option @selected($pax===$i)>{{ $i }}</option>@endfor</select><small>&nbsp;</small></div>
        <button class="btn" type="submit">See prices</button>
      </div>
    </form>
  </div>
</section>

<section>
  <div class="wrap">
    @if($error)
      <div class="box" style="border-color:#f5c2b2">{{ $error }}</div>
    @elseif($result && count($result['prices'] ?? []))
      <div class="sec-head"><div><span class="eyebrow">{{ $direction === 'from' ? 'From '.$airport->iata : 'To '.$airport->iata }}</span><h2>Prices for {{ $date->format('D j M, H:i') }}</h2></div>
        <p>{{ \Illuminate\Support\Str::limit($result['from'], 50) }} → {{ \Illuminate\Support\Str::limit($result['to'], 50) }}{{ $result['distance'] ? ' · '.round($result['distance']).' km' : '' }}</p></div>
      <div class="plans" style="grid-template-columns:repeat(3,1fr)">
        @foreach($result['prices'] as $p)
          @continue($p['seats'] && $p['seats'] < $pax)
          <div class="plan">
            <div class="plan-top"><div><b class="plan-data" style="font-size:22px">{{ $p['label'] }}</b>@if($p['seats'])<span class="plan-days">Up to {{ $p['seats'] }} passengers</span>@endif</div>@if($loop->first)<span class="badge">Lowest price</span>@endif</div>
            <div class="plan-meta"><span>Meet &amp; greet</span><span>Fixed price</span><span>Free waiting</span></div>
            <div class="plan-bottom"><div><b class="plan-price">US${{ number_format($p['price'], 2) }}</b><small>≈ AED {{ number_format($p['price'] * 3.6725) }}</small></div>
              <a class="btn" rel="nofollow sponsored" href="{{ route('transfers.book', ['airport' => $airport->iata, 'vehicle' => $p['type']]) }}">Book</a></div>
          </div>
        @endforeach
      </div>
      <p class="note" style="margin-top:14px">You'll confirm the route and pay with our transfer partner. Prices are per vehicle, not per person, and include taxes.</p>
    @elseif($airport && $to !== '')
      <div class="box" style="display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap">
        <div><h3 style="margin:0">Get your fixed price</h3><p class="note">Enter your route on our partner's site to see vehicles and prices for {{ $date->format('j M') }}.</p></div>
        <div class="chips">
          <a class="btn" rel="nofollow sponsored" href="{{ route('transfers.book', ['airport' => $airport->iata]) }}" style="min-height:50px">Compare transfers</a>
          @if(\App\Services\Links::partner('transfers'))<a class="chip" rel="nofollow sponsored" href="{{ route('transfers.book', ['airport' => $airport->iata, 'partner' => 'kiwitaxi']) }}">Also check Kiwitaxi →</a>@endif
        </div>
      </div>
    @endif

    <div class="sec-head" style="margin-top:{{ ($result || $error || $to) ? '70px' : '0' }}"><div><span class="eyebrow">Popular airports</span><h2>Transfers at airports UAE travellers use most</h2></div></div>
    <div class="esim-grid">
      @foreach($popular as $a)
        <a class="esim-dest" href="{{ route('transfers.city', $a->city_slug) }}"><b>{{ $a->city }}</b><span>{{ $a->name }} ({{ $a->iata }})</span><em>Private transfers →</em></a>
      @endforeach
    </div>
  </div>
</section>

<section class="how">
  <div class="wrap">
    <div class="sec-head"><div><span class="eyebrow">How it works</span><h2>No queues, no haggling</h2></div></div>
    <div class="steps">
      <div class="step"><h3>Choose your car</h3><p>Enter your airport, hotel and arrival time, then pick a sedan, SUV, minivan or minibus.</p></div>
      <div class="step"><h3>Pay a fixed price</h3><p>The price is agreed before you travel, so there are no meters or surprises.</p></div>
      <div class="step"><h3>Meet your driver</h3><p>Your driver tracks your flight and waits at arrivals with a name sign.</p></div>
    </div>
  </div>
</section>

<section id="faq"><div class="wrap faq">
  @include('partials.faqs', ['faqs' => [
    ['q' => 'What if my flight is delayed?', 'a' => 'Drivers track your flight number and adjust the pick-up time. Free waiting time is included after landing; the exact amount is shown when you book.'],
    ['q' => 'Is the price per person?', 'a' => 'No. Transfer prices are per vehicle, so a family or group shares the cost.'],
    ['q' => 'Can I cancel?', 'a' => 'Most transfers can be cancelled free of charge up to 24–48 hours before pick-up. The cancellation terms are shown before you pay.'],
    ['q' => 'Who do I book with?', 'a' => 'You book and pay with our transfer partner, who arranges your driver and provides support during the trip.'],
  ]])
</div></section>
@push('scripts')
<script>
(function(){var dl=document.getElementById('airportList');if(!window.TRV_AIRPORTS||!dl)return;
dl.innerHTML=window.TRV_AIRPORTS.map(function(a){return '<option value="'+a[0]+'">'+a[1]+' – '+a[2]+'</option>'}).join('');})();
</script>
@endpush
@endsection

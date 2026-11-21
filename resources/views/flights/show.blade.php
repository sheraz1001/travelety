@extends('layouts.app')
@php
  $from = $route->origin(); $to = $route->destination();
  $L = \App\Services\Links::class;
  $airlines = config('airlines');
  $monthly = $route->fare_data['monthly'] ?? [];
  $cheapest = $route->fare_data['cheapest'] ?? [];
  $maxP = collect($monthly)->max('price') ?: 1;
  $minP = collect($monthly)->min('price');
  $cheapMonthName = $route->cheapest_month ? \Carbon\Carbon::parse($route->cheapest_month.'-01')->format('F') : null;
  $airlineNames = collect($route->fare_data['airlines'] ?? [])->map(fn($c)=>$airlines[$c] ?? $c);
@endphp
@section('title', $route->meta_title ?: "Cheap Flights from {$from->city} to {$to->city} | {$site}")
@section('description', $route->meta_description ?: "Compare flights from {$from->city} ({$from->iata}) to {$to->city} ({$to->iata}) across hundreds of airlines and travel sites".($cheapMonthName ? ". Cheapest month recently: {$cheapMonthName}." : '.'))
@if(!$route->is_indexable) @section('noindex', '1') @endif

@section('content')
<section class="page-hero">
  <div class="wrap">
    @include('partials.crumbs', ['items' => ['Flights' => route('flights.index'), "From {$from->city}" => route('flights.from', $from->city_slug), "{$from->city} to {$to->city}" => null]])
    <h1>Cheap flights from {{ $from->city }} to {{ $to->city }}</h1>
    <p class="lead">
      @if($route->min_price)
        Recent fares from <b>{{ $L::money($route->min_price) }}</b> return{{ $cheapMonthName ? ", with ".$cheapMonthName." the cheapest month to fly" : '' }}. Compare hundreds of airlines and travel sites and book direct.
      @else
        Compare {{ $from->iata }} → {{ $to->iata }} fares from hundreds of airlines and travel sites, then book direct with the one you choose.
      @endif
    </p>
    @include('partials.search', ['from' => $from->iata, 'to' => $to->iata, 'city' => $to->city, 'show' => ['flights','hotels','transfers','esim']])
  </div>
</section>

<div class="wrap">
  <div class="facts">
    <div class="fact"><small>Lowest recent fare</small><b>{{ $L::money($route->min_price) }}</b><span>Return, per person</span></div>
    <div class="fact"><small>Cheapest month</small><b>{{ $cheapMonthName ?? '—' }}</b><span>Based on recent searches</span></div>
    <div class="fact"><small>Flight time</small><b>{{ $L::duration($route->duration_minutes) ?? '—' }}</b><span>{{ $route->is_direct ? 'Direct flights available' : ($route->is_direct === false ? 'Usually 1+ stop' : 'Varies by airline') }}</span></div>
    <div class="fact"><small>Airlines</small><b>{{ $airlineNames->count() ?: '—' }}</b><span>{{ $airlineNames->take(2)->implode(', ') ?: 'Compare all carriers' }}</span></div>
  </div>
</div>

<section style="padding-top:60px">
  <div class="wrap layout">
    <article>
      @if($monthly)
        <h2 style="font-size:30px">{{ $from->city }} to {{ $to->city }} fares by month</h2>
        <p class="note">Lowest return fare found per departure month. Tap a month to search it.</p>
        <div class="months">
          @foreach($monthly as $m => $row)
            @php $h = max(8, round($row['price'] / $maxP * 160)); $d = \Carbon\Carbon::parse($m.'-15'); if ($d->isPast()) $d = now()->addDays(7); @endphp
            <div class="bar {{ $row['price'] == $minP ? 'low' : '' }}" style="height:{{ $h }}px" title="{{ $d->format('M Y') }}: {{ $L::money((int)$row['price']) }}" data-href="{{ route('go.flights', ['from'=>$from->iata,'to'=>$to->iata,'depart'=>$d->toDateString(),'return'=>$d->copy()->addDays(7)->toDateString()]) }}"><span>{{ number_format($row['price']) }}</span></div>
          @endforeach
        </div>
        <div class="months-l">@foreach($monthly as $m => $row)<div>{{ \Carbon\Carbon::parse($m.'-01')->format('M') }}</div>@endforeach</div>
        <p class="note" style="margin-top:10px">Prices in {{ strtoupper(\App\Models\Setting::get('currency')) }}, updated {{ $route->fares_updated_at?->diffForHumans() }}. Fares change constantly; the live price is confirmed when you search.</p>
      @endif

      @if($cheapest)
        <h2 style="font-size:30px;margin-top:50px">Cheapest dates we've seen</h2>
        <div style="overflow-x:auto">
        <table class="fares">
          <thead><tr><th>Depart</th><th>Return</th><th>Airline</th><th>Stops</th><th>From</th><th></th></tr></thead>
          <tbody>
          @foreach(collect($cheapest)->filter(fn($c)=>!empty($c['departure_at']) && \Carbon\Carbon::parse($c['departure_at'])->isFuture())->take(8) as $c)
            @php $dep = \Carbon\Carbon::parse($c['departure_at']); $ret = !empty($c['return_at']) ? \Carbon\Carbon::parse($c['return_at']) : null; @endphp
            <tr>
              <td>{{ $dep->format('D j M') }}</td><td>{{ $ret?->format('D j M') ?? 'One way' }}</td>
              <td>{{ $airlines[$c['airline'] ?? ''] ?? ($c['airline'] ?? '—') }}</td>
              <td>{{ ($c['transfers'] ?? 0) == 0 ? 'Direct' : $c['transfers'].' stop'.(($c['transfers'] ?? 0) > 1 ? 's' : '') }}</td>
              <td><b>{{ $L::money((int) $c['price']) }}</b></td>
              <td><a href="{{ route('go.flights', ['from'=>$from->iata,'to'=>$to->iata,'depart'=>$dep->toDateString(),'return'=>$ret?->toDateString()]) }}" rel="nofollow">Check →</a></td>
            </tr>
          @endforeach
          </tbody>
        </table>
        </div>
      @endif

      <div class="prose" style="margin-top:20px">
        @if($route->content)
          {!! $route->content !!}
        @else
          <h2>About flights from {{ $from->city }} to {{ $to->city }}</h2>
          <p>Flights from {{ $from->name }} ({{ $from->iata }}) to {{ $to->name }} ({{ $to->iata }}) in {{ $to->country }} are offered by {{ $airlineNames->count() ? $airlineNames->take(4)->implode(', ') : 'several full-service and low-cost airlines' }}. {{ $route->is_direct ? 'Direct flights operate on this route' : 'Most itineraries include at least one connection' }}{{ $route->duration_minutes ? ', with a typical flight time of around '.$L::duration($route->duration_minutes) : '' }}.</p>
          <p>Fares rise sharply around Eid holidays, the summer school break and the December holidays, so booking 6–10 weeks ahead usually gets the best prices. Low-cost carriers often charge separately for checked baggage, so compare the total price including bags before you book.</p>
        @endif
        @include('partials.faqs', ['faqs' => $route->faqs ?? [], 'heading' => "{$from->city} to {$to->city} flights: FAQs"])
      </div>

      @if($related->count())
        <h2 style="font-size:28px;margin:50px 0 16px">More routes from {{ $from->city }}</h2>
        <div class="chips">@foreach($related as $r)<a class="chip" href="{{ $r->url() }}">{{ $from->city }} → {{ $r->destination()?->city }}</a>@endforeach</div>
      @endif
      @if($alternatives->count())
        <h2 style="font-size:28px;margin:36px 0 16px">Other ways to {{ $to->city }} from the UAE</h2>
        <div class="chips">@foreach($alternatives as $r)<a class="chip" href="{{ $r->url() }}">{{ $r->origin()?->city }} → {{ $to->city }}</a>@endforeach</div>
      @endif
    </article>

    <aside class="sidebar">
      <div class="box">
        <h3>Search {{ $from->iata }} → {{ $to->iata }}</h3>
        <p class="note">Live prices from hundreds of airlines and agencies.</p>
        <a class="btn" href="{{ route('go.flights', ['from'=>$from->iata,'to'=>$to->iata]) }}" rel="nofollow">See live fares</a>
      </div>
      <div class="box">
        <h3>Plan your {{ $to->city }} trip</h3>
        <a class="li" href="{{ $guide ? $guide->hotelsUrl() : route('go.hotels', ['city'=>$to->city]) }}">Hotels in {{ $to->city }} <span>→</span></a>
        @if($guide)<a class="li" href="{{ $guide->guideUrl() }}">{{ $to->city }} travel guide <span>→</span></a>@endif
        <a class="li" href="{{ route('go.partner','transfers') }}" rel="nofollow sponsored">Airport transfer <span>→</span></a>
        <a class="li" href="{{ route('go.partner','tours') }}" rel="nofollow sponsored">Tours & tickets <span>→</span></a>
        <a class="li" href="{{ route('go.partner','esim') }}" rel="nofollow sponsored">eSIM for {{ $to->country }} <span>→</span></a>
        <a class="li" href="{{ route('go.partner','insurance') }}" rel="nofollow sponsored">Travel insurance <span>→</span></a>
      </div>
      @if($return)
      <div class="box"><h3>Flying back?</h3><a class="li" href="{{ $return->url() }}">{{ $to->city }} → {{ $from->city }} <span>→</span></a></div>
      @endif
    </aside>
  </div>
</section>
@include('partials.alerts', ['route' => $route])
@endsection

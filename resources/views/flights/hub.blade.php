@extends('layouts.app')
@php $isFrom = $mode === 'from'; @endphp
@section('title', ($isFrom ? "Cheap Flights from {$city}" : "Cheap Flights to {$city} from the UAE")." | {$site}")
@section('description', $isFrom ? "Compare cheap flights from {$city} to {$routes->count()}+ destinations. Recent lowest fares, cheapest months and direct routes." : "Compare cheap flights to {$city} from Dubai, Abu Dhabi and Sharjah. Recent lowest fares and the best time to book.")
@section('content')
<section class="page-hero">
  <div class="wrap">
    @include('partials.crumbs', ['items' => ['Flights' => route('flights.index'), ($isFrom ? "From " : "To ").$city => null]])
    <h1>{{ $isFrom ? "Cheap flights from {$city}" : "Cheap flights to {$city}" }}</h1>
    <p class="lead">{{ $isFrom ? "Where can you fly from {$city}? Here are the routes travellers search most, with the lowest recent fares." : "Compare fares to {$city} from every UAE airport and book direct with the airline or agency you choose." }}</p>
    @include('partials.search', ['from' => $isFrom ? $iata : null, 'to' => $isFrom ? null : $iata, 'show' => ['flights','hotels']])
  </div>
</section>
<section>
  <div class="wrap">
    <div class="routes">
      @foreach($routes as $r)
        <a class="route" href="{{ $r->url() }}">
          <div class="codes">{{ $r->origin_iata }}@include('partials.icon',['n'=>'arrow','sw'=>2.4]){{ $r->destination_iata }}</div>
          <div class="cities">{{ $r->origin()?->city }} → {{ $r->destination()?->city }}</div>
          <div class="go">{{ $r->min_price ? 'From '.\App\Services\Links::money($r->min_price) : 'See fares' }}{{ $r->cheapest_month ? ' · best in '.\Carbon\Carbon::parse($r->cheapest_month.'-01')->format('M') : '' }} →</div>
        </a>
      @endforeach
    </div>
    @if($routes->isEmpty())<p class="note">Fares for this city are being collected. Use the search above for live prices.</p>@endif
    @if($guide)
      <div class="box" style="margin-top:40px;display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap">
        <div><h3 style="margin:0">Planning a trip to {{ $guide->city }}?</h3><p class="note">Where to stay, getting around, weather and visa tips.</p></div>
        <div class="chips"><a class="chip" href="{{ $guide->guideUrl() }}">{{ $guide->city }} travel guide →</a><a class="chip" href="{{ $guide->hotelsUrl() }}">Hotels in {{ $guide->city }} →</a></div>
      </div>
    @endif
  </div>
</section>
@endsection

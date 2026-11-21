@extends('layouts.app')
@section('title', $d->meta_title ?: "{$d->city} Travel Guide from the UAE: Flights, Hotels & Tips | {$site}")
@section('description', $d->meta_description ?: "Plan a trip to {$d->city}: flights from the UAE, where to stay, weather by month, visa tips and the best things to do.")
@if(!$d->is_indexable) @section('noindex', '1') @endif
@section('content')
<section class="page-hero">
  <div class="wrap">
    @include('partials.crumbs', ['items' => ['Destinations' => route('travel.index'), $d->city => null]])
    <h1>{{ $d->city }} travel guide</h1>
    <p class="lead">{{ $d->tagline ?: "Flights, hotels and practical tips for your trip to {$d->city}." }}</p>
    @include('partials.search', ['to' => $d->iata, 'city' => $d->city])
  </div>
</section>
@php $best = $routes->whereNotNull('min_price')->sortBy('min_price')->first(); @endphp
<div class="wrap">
  <div class="facts">
    <div class="fact"><small>Flights from the UAE</small><b>{{ $best ? \App\Services\Links::money($best->min_price) : '—' }}</b><span>{{ $best ? 'Lowest recent fare from '.$best->origin()?->city : 'Compare live fares' }}</span></div>
    <div class="fact"><small>Flight time</small><b>{{ \App\Services\Links::duration($best?->duration_minutes) ?? '—' }}</b><span>{{ $best?->is_direct ? 'Direct flights available' : 'Check connections' }}</span></div>
    <div class="fact"><small>Country</small><b>{{ $d->country }}</b><span>Airport: {{ $d->iata }}</span></div>
    <div class="fact"><small>Trip estimate</small><b>{{ $best ? \App\Services\Links::money($best->min_price) : '—' }} +</b><span>Flight, then hotels from the search above</span></div>
  </div>
</div>
<section style="padding-top:60px">
  <div class="wrap layout">
    <article class="prose">
      {!! $d->intro !!}
      @if($d->best_time)<h2>Best time to visit {{ $d->city }}</h2><p>{{ $d->best_time }}</p>@endif
      @if($d->weather)
        <div class="weather">@foreach($d->weather as $w)<div>{{ $w['month'] ?? '' }}<b>{{ $w['avg_high_c'] ?? '' }}°</b></div>@endforeach</div>
      @endif
      @if($d->visa_uae)<h2>Visa for UAE residents</h2><p>{{ $d->visa_uae }}</p>@endif
      @if($d->things_to_do)
        <h2>Top things to do in {{ $d->city }}</h2>
        <div class="areas">@foreach($d->things_to_do as $t)<div class="area"><b>{{ $t['name'] ?? '' }}</b><p>{{ $t['description'] ?? '' }}</p></div>@endforeach</div>
        <p><a href="{{ route('go.partner','tours') }}" rel="nofollow sponsored">Book tours and tickets in {{ $d->city }} →</a></p>
      @endif
      {!! $d->guide_content !!}
      <h2>Plan the rest of your {{ $d->city }} trip</h2>
    </article>
    <aside class="sidebar">
      <div class="box"><h3>Flights to {{ $d->city }}</h3>
        @forelse($routes as $r)<a class="li" href="{{ $r->url() }}">From {{ $r->origin()?->city }} <span>{{ $r->min_price ? \App\Services\Links::money($r->min_price) : '→' }}</span></a>@empty<a class="li" href="{{ route('go.flights',['to'=>$d->iata]) }}" rel="nofollow">Search flights <span>→</span></a>@endforelse
      </div>
      <div class="box"><h3>Stay</h3><a class="li" href="{{ $d->hotelsUrl() }}">Where to stay in {{ $d->city }} <span>→</span></a><a class="btn" href="{{ route('go.hotels',['city'=>$d->city]) }}" rel="nofollow">Compare hotels</a></div>
    </aside>
  </div>
  <div class="wrap" style="margin-top:20px">@include('partials.services', ['city' => $d->city])</div>
  <div class="wrap prose" style="max-width:820px;margin-top:40px">@include('partials.faqs', ['faqs' => $d->faqs ?? [], 'heading' => "{$d->city} travel FAQs"])</div>
  @if($posts->count())
    <div class="wrap" style="margin-top:50px"><h2 style="font-size:28px;margin-bottom:18px">{{ $d->city }} on the blog</h2><div class="grid-3">@foreach($posts as $post) @include('partials.post-card') @endforeach</div></div>
  @endif
</section>
@endsection

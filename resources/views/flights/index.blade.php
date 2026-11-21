@extends('layouts.app')
@section('title', "Cheap Flights from the UAE — Compare Airlines | {$site}")
@section('description', 'Compare cheap flights from Dubai, Abu Dhabi and Sharjah to hundreds of destinations. See the cheapest months and book direct with airlines and agencies.')
@section('content')
<section class="page-hero">
  <div class="wrap">
    @include('partials.crumbs', ['items' => ['Flights' => null]])
    <h1>Cheap flights from the UAE</h1>
    <p class="lead">Search hundreds of airlines and travel sites at once. Browse routes below for recent fares and the cheapest months to fly.</p>
    @include('partials.search', ['show' => ['flights','hotels']])
  </div>
</section>
<section>
  <div class="wrap">
    <div class="tabs-links">@foreach($origins as $o)<a href="{{ route('flights.from', $o->city_slug) }}">Flights from {{ $o->city }}</a>@endforeach</div>
    @foreach($byCountry as $country => $routes)
      <h2 style="font-size:26px;margin:34px 0 14px">Flights to {{ $country }}</h2>
      <div class="chips">@foreach($routes as $r)<a class="chip" href="{{ $r->url() }}">{{ $r->origin()?->city }} → {{ $r->destination()?->city }}@if($r->min_price) · <b>{{ \App\Services\Links::money($r->min_price) }}</b>@endif</a>@endforeach</div>
    @endforeach
    @if($byCountry->isEmpty())<p class="note">Route pages appear here as fares are collected.</p>@endif
  </div>
</section>
@endsection

@extends('layouts.app')
@section('title', "Travel Guides & Destinations from the UAE | {$site}")
@section('description', 'Destination guides for travellers from the UAE: getting there, where to stay, weather, visa tips and things to do.')
@section('content')
<section class="page-hero">
  <div class="wrap">
    @include('partials.crumbs', ['items' => ['Destinations' => null]])
    <h1>Where will you go next?</h1>
    <p class="lead">Practical guides for travellers from the UAE, with flights, hotels and everything in between.</p>
    @include('partials.search', ['show' => ['flights','hotels']])
  </div>
</section>
<section>
  <div class="wrap">
    @foreach($byCountry as $country => $items)
      <h2 style="font-size:26px;margin:30px 0 14px">{{ $country }}</h2>
      <div class="grid-4">@foreach($items as $d)<a class="dcard" href="{{ $d->guideUrl() }}"><h3>{{ $d->city }}</h3><p>{{ $d->tagline ?: $d->country }}</p></a>@endforeach</div>
    @endforeach
    @if($byCountry->isEmpty())<p class="note">Destination guides are being written.</p>@endif
  </div>
</section>
@endsection

@extends('layouts.app')
@section('title', "Compare Hotels Worldwide — Best Areas & Deals | {$site}")
@section('description', 'Compare hotels and apartments worldwide. Find the best areas to stay, when to book and the right hotel for your trip.')
@section('content')
<section class="page-hero">
  <div class="wrap">
    @include('partials.crumbs', ['items' => ['Hotels' => null]])
    <h1>Hotels for every kind of trip</h1>
    <p class="lead">Compare stays from budget rooms to five-star resorts, then book with the provider you choose.</p>
    @include('partials.search', ['tab' => 'hotels', 'show' => ['hotels','flights']])
  </div>
</section>
<section>
  <div class="wrap">
    <div class="sec-head"><div><span class="eyebrow">Hotel guides</span><h2>Where to stay</h2></div><p>Neighbourhood guides and booking advice for popular destinations.</p></div>
    <div class="grid-4">@foreach($destinations as $d)<a class="dcard" href="{{ $d->hotelsUrl() }}"><h3>{{ $d->city }}</h3><p>Hotels in {{ $d->city }}, {{ $d->country }}</p></a>@endforeach</div>
    @if($destinations->isEmpty())<p class="note">Hotel guides are being written. Use the search above to compare hotels now.</p>@endif
  </div>
</section>
@endsection

@extends('layouts.app')
@section('title', "Hotels in {$d->city}: Best Areas to Stay & Deals | {$site}")
@section('description', "Compare hotels in {$d->city}, {$d->country}. The best neighbourhoods to stay in, what each suits and when to book for the best rates.")
@if(!$d->is_indexable) @section('noindex', '1') @endif
@section('content')
<section class="page-hero">
  <div class="wrap">
    @include('partials.crumbs', ['items' => ['Hotels' => route('hotels.index'), $d->city => null]])
    <h1>Hotels in {{ $d->city }}</h1>
    <p class="lead">{{ $d->tagline ?: "Compare hotels and apartments in {$d->city} and find the right area for your stay." }}</p>
    @include('partials.search', ['tab' => 'hotels', 'city' => $d->city, 'to' => $d->iata, 'show' => ['hotels','flights','transfers','tours']])
  </div>
</section>
<section>
  <div class="wrap layout">
    <article class="prose">
      @if($d->areas)
        <h2>Best areas to stay in {{ $d->city }}</h2>
        <div class="areas">@foreach($d->areas as $a)<div class="area"><small>{{ ucfirst($a['budget'] ?? '') }}</small><b>{{ $a['name'] ?? '' }}</b><p>{{ $a['description'] ?? '' }}</p></div>@endforeach</div>
      @endif
      {!! $d->hotels_content !!}
      @if(\App\Services\Links::hotelsEnabled())<p><a class="btn" style="display:inline-flex;text-decoration:none;color:#fff" href="{{ route('go.hotels', ['city'=>$d->city]) }}" rel="nofollow">Compare hotels in {{ $d->city }}</a></p>@endif
      @include('partials.faqs', ['faqs' => collect($d->faqs ?? [])->take(4)->all(), 'heading' => "Hotels in {$d->city}: FAQs"])
    </article>
    <aside class="sidebar">
      <div class="box"><h3>Getting to {{ $d->city }}</h3>
        @forelse($routes as $r)<a class="li" href="{{ $r->url() }}">Flights from {{ $r->origin()?->city }} <span>{{ $r->min_price ? \App\Services\Links::money($r->min_price) : '→' }}</span></a>@empty<a class="li" href="{{ route('go.flights',['to'=>$d->iata]) }}" rel="nofollow">Search flights <span>→</span></a>@endforelse
      </div>
      <div class="box"><h3>More for your trip</h3>
        <a class="li" href="{{ $d->guideUrl() }}">{{ $d->city }} travel guide <span>→</span></a>
        @if(\App\Services\Links::partner('transfers')) <a class="li" href="{{ route('transfers.city', \Illuminate\Support\Str::slug($d->city)) }}">Airport transfer <span>→</span></a> @endif
        @if(\App\Services\Links::partner('tours')) <a class="li" href="{{ route('go.partner','tours') }}" rel="nofollow sponsored">Things to do <span>→</span></a> @endif
      </div>
    </aside>
  </div>
</section>
@endsection

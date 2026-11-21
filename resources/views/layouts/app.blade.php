@php
  $site = \App\Models\Setting::get('site_name');
  $title = trim($__env->yieldContent('title')) ?: $site.' — '.\App\Models\Setting::get('tagline');
  $desc = trim($__env->yieldContent('description')) ?: 'Compare flights from hundreds of airlines and travel sites, plus hotels, transfers, tours and eSIMs. Find the best fare and book direct.';
  $canonical = trim($__env->yieldContent('canonical')) ?: url()->current();
  $ga = \App\Models\Setting::get('google_analytics_id');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<meta name="description" content="{{ $desc }}">
<link rel="canonical" href="{{ $canonical }}">
@hasSection('noindex')<meta name="robots" content="noindex,follow">@endif
@if($v = \App\Models\Setting::get('google_site_verification'))<meta name="google-site-verification" content="{{ $v }}">@endif
<meta property="og:site_name" content="{{ $site }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $desc }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:type" content="@yield('og_type', 'website')">
@hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@endif
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="alternate" type="application/rss+xml" title="{{ $site }} blog" href="{{ route('blog.feed') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'Organization','name'=>$site,'url'=>url('/'),'logo'=>asset('favicon.svg')], JSON_UNESCAPED_SLASHES) !!}</script>
@stack('schema')
@if($ga)
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','{{ $ga }}');</script>
@endif
</head>
<body>
<header class="@yield('header_class')">
  <div class="wrap nav">
    @include('partials.logo')
    <nav class="nav-links" id="navLinks">
      <a href="{{ route('flights.index') }}">Flights</a>
      <a href="{{ route('hotels.index') }}">Hotels</a>
      <a href="{{ route('travel.index') }}">Destinations</a>
      <a href="{{ route('blog.index') }}">Travel blog</a>
      <a href="{{ route('home') }}#faq">Help</a>
    </nav>
    <div class="nav-cta">
      <a href="{{ route('home') }}#alerts" class="pill">Price alerts</a>
      <button class="burger" aria-label="Menu">☰</button>
    </div>
  </div>
</header>

@yield('content')

<footer>
  <div class="wrap">
    <div class="foot">
      <div>
        @include('partials.logo')
        <p>Compare flights, hotels and everything for your trip, then book direct.</p>
        <p style="margin-top:12px">{{ \App\Models\Setting::get('contact_email') }}</p>
      </div>
      <div><h4>Search</h4>
        <a href="{{ route('flights.index') }}">Flights</a>
        <a href="{{ route('hotels.index') }}">Hotels</a>
        <a href="{{ route('transfers.index') }}">Transfers</a>
        @if(\App\Services\Links::partner('cars'))<a href="{{ route('go.partner', 'cars') }}" rel="nofollow">Car rental</a>@endif
        <a href="{{ route('flights.from', 'dubai') }}">Flights from Dubai</a>
        <a href="{{ route('flights.from', 'abu-dhabi') }}">Flights from Abu Dhabi</a>
        <a href="{{ route('flights.from', 'sharjah') }}">Flights from Sharjah</a>
      </div>
      <div><h4>Trip extras</h4>
        @if(\App\Services\Links::partner('tours'))<a href="{{ route('go.partner', 'tours') }}" rel="nofollow">Tours & tickets</a>@endif
        <a href="{{ route('esim.index') }}">Travel eSIM</a>
        @if(\App\Services\Links::partner('insurance'))<a href="{{ route('go.partner', 'insurance') }}" rel="nofollow">Insurance</a>@endif
        @if(\App\Services\Links::partner('compensation'))<a href="{{ route('go.partner', 'compensation') }}" rel="nofollow">Flight compensation</a>@endif
        <a href="{{ route('blog.index') }}">Travel blog</a>
      </div>
      <div><h4>{{ $site }}</h4>
        <a href="{{ route('page', 'about') }}">About us</a>
        <a href="{{ route('page', 'contact') }}">Contact</a>
        <a href="{{ route('page', 'privacy') }}">Privacy policy</a>
        <a href="{{ route('page', 'terms') }}">Terms of use</a>
      </div>
    </div>
    <p class="disclosure">{{ $site }} is a travel search engine. We don't sell tickets or charge booking fees. Bookings are made directly with our airline, hotel and travel agency partners, who are responsible for fulfilment, payments and customer service. We may earn a commission on bookings at no extra cost to you. Prices shown on our pages are recent fares found by travellers and can change at any time.</p>
    <div class="legal"><span>© <span id="yr">{{ date('Y') }}</span> {{ $site }}. All rights reserved.</span><span>{{ parse_url(config('app.url'), PHP_URL_HOST) }}</span></div>
  </div>
</footer>
<script src="{{ route('airports.js') }}"></script>
<script src="{{ asset('js/site.js') }}?v={{ filemtime(public_path('js/site.js')) }}"></script>
@stack('scripts')
</body>
</html>

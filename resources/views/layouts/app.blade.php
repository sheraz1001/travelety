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
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'Organization','name'=>$site,'url'=>url('/'),'logo'=>asset('favicon.svg')], JSON_UNESCAPED_SLASHES) !!}</script>
@stack('schema')
@stack('head')
@if($ga)
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','{{ $ga }}');</script>
@endif
</head>
<body>
@php
  $I = fn($n,$sw=1.9) => view('partials.icon',['n'=>$n,'sw'=>$sw])->render();
  $nav = [
    ['flights.index','Flights','plane',null,'flights'],
    ['hotels.index','Hotels','bed',null,'hotels'],
    ['esim.index','eSIM','sim','NEW','esim'],
    ['transfers.index','Airport transfers','taxi',null,'transfers'],
  ];
  $cur = request()->route()?->getName() ?? '';
@endphp
<div class="util">
  <div class="wrap">
    <a href="{{ route('esim.index') }}" class="hide-m">{!! $I('sim') !!}Travel eSIM from $4</a>
    <a href="{{ route('blog.index') }}" class="hide-m">{!! $I('tag') !!}Deals &amp; guides</a>
    <span class="sp"></span>
    <span class="util-pill">{!! $I('globe') !!}English · USD</span>
    <a href="{{ route('page', 'contact') }}" class="hide-m">{!! $I('help') !!}Customer support</a>
    <a href="{{ route('home') }}#alerts">{!! $I('bell') !!}Price alerts</a>
  </div>
</div>
<header class="site-hd" id="hdr">
  <div class="wrap hd">
    @include('partials.logo')
    <span class="sp"></span>
    <div class="hd-actions">
      <a href="{{ route('home') }}#alerts" class="hbtn hbtn-ghost">{!! $I('bell') !!}Price alerts</a>
      <a href="{{ route('flights.index') }}" class="hbtn hbtn-blue">{!! $I('search') !!}Search flights</a>
      <button class="burger" aria-label="Menu">{!! $I('menu',2.2) !!}</button>
    </div>
  </div>
  <nav class="svc-nav mega-host" id="navLinks">
    <div class="wrap svc-row">
      @foreach($nav as [$r,$l,$ic,$tag,$k])
        <a href="{{ route($r) }}" class="{{ str_starts_with($cur, explode('.',$r)[0]) ? 'on' : '' }}">{!! $I($ic) !!}{{ $l }}@if($tag)<span class="ntag">{{ $tag }}</span>@endif</a>
      @endforeach
      @if(\App\Services\Links::partner('cars'))<a href="{{ route('go.partner','cars') }}" rel="nofollow sponsored">{!! $I('car') !!}Car rental</a>@endif
      @if(\App\Services\Links::partner('tours'))<a href="{{ route('go.partner','tours') }}" rel="nofollow sponsored">{!! $I('ticket') !!}Tours &amp; tickets</a>@endif
      <a href="{{ route('travel.index') }}" class="{{ str_starts_with($cur,'travel') ? 'on' : '' }}">{!! $I('pin') !!}Destinations</a>
      <a href="{{ route('blog.index') }}" class="{{ str_starts_with($cur,'blog') ? 'on' : '' }}">{!! $I('book') !!}Travel guides</a>
    </div>
    <div class="mega">
      <div class="wrap mega-in">
        @foreach(['Europe'=>['london'=>'London','paris'=>'Paris','istanbul'=>'Istanbul','rome'=>'Rome','barcelona'=>'Barcelona'],'Asia'=>['bangkok'=>'Bangkok','tokyo'=>'Tokyo','bali'=>'Bali','singapore'=>'Singapore','kuala-lumpur'=>'Kuala Lumpur'],'Middle East &amp; Africa'=>['cairo'=>'Cairo','jeddah'=>'Jeddah','doha'=>'Doha','cape-town'=>'Cape Town','baku'=>'Baku'],'Americas &amp; Oceania'=>['new-york'=>'New York','toronto'=>'Toronto','los-angeles'=>'Los Angeles','sydney'=>'Sydney','melbourne'=>'Melbourne']] as $h => $cities)
          <div><h6>{!! $h !!}</h6><ul>@foreach($cities as $slug => $c)<li><a href="{{ route('flights.to', $slug) }}">Flights to {{ $c }}</a></li>@endforeach</ul></div>
        @endforeach
        <a href="{{ route('esim.index') }}" class="mega-feat"><img src="{{ asset('img/home/phone.jpg') }}" alt="" loading="lazy"><div><small>NEW</small><b>Travel eSIM for 200+ destinations</b></div></a>
      </div>
    </div>
  </nav>
</header>

@yield('content')

<footer class="site-ft">
  <div class="wrap">
    <div class="fg">
      <div>
        @include('partials.logo')
        <p class="ft-about">Flights, hotels, eSIM, airport transfers, car rental and tours for travellers everywhere.</p>
        @if($ce = \App\Models\Setting::get('contact_email'))<p class="ft-about">{{ $ce }}</p>@endif
      </div>
      <div><h6>Book</h6>
        <a href="{{ route('flights.index') }}">Flights</a>
        <a href="{{ route('hotels.index') }}">Hotels</a>
        <a href="{{ route('esim.index') }}">Travel eSIM</a>
        <a href="{{ route('transfers.index') }}">Airport transfers</a>
        @if(\App\Services\Links::partner('cars'))<a href="{{ route('go.partner', 'cars') }}" rel="nofollow">Car rental</a>@endif
        @if(\App\Services\Links::partner('tours'))<a href="{{ route('go.partner', 'tours') }}" rel="nofollow">Tours &amp; tickets</a>@endif
      </div>
      <div><h6>Explore</h6>
        <a href="{{ route('travel.index') }}">Destinations</a>
        <a href="{{ route('flights.index') }}">Flight routes</a>
        <a href="{{ route('blog.index') }}">Travel guides</a>
        <a href="{{ route('home') }}#alerts">Price alerts</a>
      </div>
      <div><h6>Trip extras</h6>
        @if(\App\Services\Links::partner('insurance'))<a href="{{ route('go.partner', 'insurance') }}" rel="nofollow">Travel insurance</a>@endif
        @if(\App\Services\Links::partner('compensation'))<a href="{{ route('go.partner', 'compensation') }}" rel="nofollow">Flight compensation</a>@endif
        <a href="{{ route('esim.show', 'europe') }}">Europe eSIM</a>
        <a href="{{ route('esim.show', 'world') }}">Global eSIM</a>
      </div>
      <div><h6>Company</h6>
        <a href="{{ route('page', 'about') }}">About us</a>
        <a href="{{ route('page', 'contact') }}">Contact</a>
        <a href="{{ route('page', 'privacy') }}">Privacy policy</a>
        <a href="{{ route('page', 'terms') }}">Terms of use</a>
      </div>
    </div>
    <p class="disclosure">{{ $site }} is a travel search engine. We don't sell tickets or charge booking fees. Bookings are made directly with our airline, hotel and travel partners, who are responsible for fulfilment, payments and customer service. We may earn a commission on bookings at no extra cost to you. Prices shown on our pages are recent fares found by travellers and can change at any time.</p>
    <div class="legal"><span>© {{ date('Y') }} {{ $site }}. All rights reserved.</span><span>English · USD · Worldwide</span></div>
  </div>
</footer>
<script src="{{ route('airports.js') }}"></script>
<script src="{{ asset('js/site.js') }}?v={{ filemtime(public_path('js/site.js')) }}"></script>
@stack('scripts')
</body>
</html>

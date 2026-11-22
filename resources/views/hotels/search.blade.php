@extends('layouts.app')
@section('title', ($p['q'] ? 'Hotels in '.$p['q'] : 'Hotel search').' — '.\Carbon\Carbon::parse($p['checkin'])->format('j M').' to '.\Carbon\Carbon::parse($p['checkout'])->format('j M').' | '.$site)
@section('noindex', '1')
@php $I = fn($n,$sw=1.9) => view('partials.icon',['n'=>$n,'sw'=>$sw])->render(); @endphp
@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
@endpush
@section('content')
<section class="hs-top">
  <div class="wrap"><div class="search hs-search">@include('hotels.partials.form', ['p' => $p])</div></div>
</section>

<div class="wrap hs-wrap" id="hsApp"
     data-endpoint="{{ route('hotels.search.data') }}"
     data-qs='@json($qs)'
     data-nights="{{ $p['nights'] }}"
     data-fac='@json($selectedFac)'>
  @if($sandbox)<div class="hs-test">Test mode: prices are real but bookings are not sent to hotels and no card is charged.</div>@endif

  <div class="hs-grid">
    <aside class="hs-side" id="hsSide">
      <div class="hs-mapcard" id="openMap">
        <div id="miniMap" class="minimap"></div>
        <button type="button" class="hbtn hbtn-blue">{!! $I('pin') !!}Show on map</button>
      </div>

      <div class="hs-box hs-filters">
        <div class="hs-fhead"><h4>Filter by</h4><button type="button" class="linkbtn" id="fReset">Reset all</button></div>

        <div class="fgrp">
          <h5>Property name</h5>
          <input type="search" id="fName" class="finput" placeholder="e.g. Marriott">
        </div>

        <div class="fgrp">
          <h5>Your budget <span class="note">(per night)</span></h5>
          <div class="hist" id="fHist"></div>
          <div class="range2">
            <input type="range" id="fMin" min="0" max="100" value="0">
            <input type="range" id="fMax" min="0" max="100" value="100">
          </div>
          <div class="rlabels"><span id="fMinL">–</span><span id="fMaxL">–</span></div>
        </div>

        <div class="fgrp">
          <h5>Popular filters</h5>
          <label class="fchk"><input type="checkbox" data-f="refundable"> Free cancellation <em data-c="refundable"></em></label>
          <label class="fchk"><input type="checkbox" data-f="meal" value="bb"> Breakfast included <em data-c="meal:bb"></em></label>
          <label class="fchk"><input type="checkbox" data-f="score" value="8"> Very good: 8+ <em data-c="score:8"></em></label>
          <label class="fchk"><input type="checkbox" data-f="stars" value="5"> 5 stars <em data-c="stars:5"></em></label>
        </div>

        <div class="fgrp">
          <h5>Guest review score</h5>
          @foreach([9=>'Wonderful: 9+',8=>'Very good: 8+',7=>'Good: 7+',6=>'Pleasant: 6+'] as $v=>$l)
            <label class="fchk"><input type="radio" name="fScore" data-f="scoreR" value="{{ $v }}"> {{ $l }} <em data-c="score:{{ $v }}"></em></label>
          @endforeach
        </div>

        <div class="fgrp">
          <h5>Star rating</h5>
          @foreach([5,4,3,2,1] as $s)
            <label class="fchk"><input type="checkbox" data-f="stars" value="{{ $s }}"> <span class="stars">{{ str_repeat('★',$s) }}</span> <em data-c="stars:{{ $s }}"></em></label>
          @endforeach
        </div>

        <div class="fgrp">
          <h5>Meals</h5>
          @foreach(['bb'=>'Breakfast included','hb'=>'Half board','fb'=>'Full board','ai'=>'All inclusive','ro'=>'Room only'] as $v=>$l)
            <label class="fchk"><input type="checkbox" data-f="meal" value="{{ $v }}"> {{ $l }} <em data-c="meal:{{ $v }}"></em></label>
          @endforeach
        </div>

        <div class="fgrp">
          <h5>Distance from centre</h5>
          @foreach([1=>'Less than 1 km',3=>'Less than 3 km',5=>'Less than 5 km',10=>'Less than 10 km'] as $v=>$l)
            <label class="fchk"><input type="radio" name="fDist" data-f="dist" value="{{ $v }}"> {{ $l }} <em data-c="dist:{{ $v }}"></em></label>
          @endforeach
        </div>

        <div class="fgrp">
          <h5>Facilities <span class="note">(updates results)</span></h5>
          @foreach($facilities as $id=>$l)
            <label class="fchk"><input type="checkbox" class="ffac" value="{{ $id }}" @checked(in_array($id,$selectedFac))> {{ $l }}</label>
          @endforeach
        </div>
      </div>
    </aside>

    <main>
      <div class="hs-head">
        <div>
          <h1>{{ $p['q'] ? 'Hotels in '.$p['q'] : 'Find your hotel' }}</h1>
          <p class="note" id="hsCount">Searching the best prices for {{ \Carbon\Carbon::parse($p['checkin'])->format('j M') }} – {{ \Carbon\Carbon::parse($p['checkout'])->format('j M') }}…</p>
        </div>
        <div class="hs-tools">
          <button type="button" class="hbtn hbtn-ghost hs-mobile-filter" id="mFilter">Filters</button>
          <button type="button" class="hbtn hbtn-ghost hs-mobile-filter" id="mMap">{!! $I('pin') !!}Map</button>
          <select id="hsSort" class="hs-sortsel" aria-label="Sort by">
            <option value="rec">Our top picks</option>
            <option value="price">Price (lowest first)</option>
            <option value="price_d">Price (highest first)</option>
            <option value="rating">Guest rating</option>
            <option value="stars">Stars (highest first)</option>
            <option value="dist">Distance from centre</option>
          </select>
        </div>
      </div>
      <div class="chips-on" id="chipsOn"></div>

      <div class="hs-list" id="hsList">
        @for($i=0;$i<4;$i++)
          <div class="hcard skel"><div class="hcard-img"></div><div class="hcard-bd"><i></i><i></i><i class="s"></i></div></div>
        @endfor
      </div>
      <div class="hs-more"><button type="button" class="hbtn hbtn-ghost" id="hsMore" hidden>Show more hotels</button></div>
    </main>
  </div>
</div>

<div class="mapov" id="mapOv" hidden>
  <div class="mapov-bar">
    <b id="mapTitle">{{ $p['q'] ? 'Hotels in '.$p['q'] : 'Hotels' }}</b>
    <span class="note" id="mapCount"></span>
    <button type="button" class="mapov-x" id="mapClose" aria-label="Close map">✕</button>
  </div>
  <div class="mapov-body">
    <div class="mapov-list" id="mapList"></div>
    <div id="bigMap" class="bigmap"></div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="{{ asset('js/hotels.js') }}?v={{ filemtime(public_path('js/hotels.js')) }}"></script>
@endpush

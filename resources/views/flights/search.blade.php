@extends('layouts.app')
@php
  $I = fn($n,$sw=1.9) => view('partials.icon',['n'=>$n,'sw'=>$sw])->render();
  $fromCity = $fromA?->city ?? $p['from']; $toCity = $toA?->city ?? $p['to'];
  $pax = $p['adults'] + $p['children'] + $p['infants'];
@endphp
@section('title', 'Flights '.$fromCity.' to '.$toCity.' — '.\Carbon\Carbon::parse($p['depart'])->format('j M').' | '.$site)
@section('noindex', '1')
@section('content')
<section class="hs-top">
  <div class="wrap">@include('partials.search', ['show' => ['flights'], 'from' => $p['from'], 'to' => $p['to'], 'depart' => $p['depart'], 'return' => $p['ret']])</div>
</section>

<div class="wrap hs-wrap" id="flApp"
  data-endpoint="{{ route('flights.search.data') }}" data-qs='@json($qs)' data-select="{{ route('flights.select') }}"
  data-csrf="{{ csrf_token() }}" data-pax="{{ $pax }}" data-back="{{ request()->fullUrl() }}">
  @if($sandbox)<div class="hs-test">Test mode: fares are real but no tickets are issued and no card is charged.</div>@endif
  @if(session('fl_error'))<div class="hs-alert">{{ session('fl_error') }}</div>@endif

  <div class="fl-route">
    <div><b>{{ $fromCity }} ({{ $p['from'] }})</b> {!! $I($p['ret'] ? 'swap' : 'arrow') !!} <b>{{ $toCity }} ({{ $p['to'] }})</b></div>
    <span class="note">{{ \Carbon\Carbon::parse($p['depart'])->format('D j M') }}@if($p['ret']) – {{ \Carbon\Carbon::parse($p['ret'])->format('D j M') }}@endif · {{ $pax }} traveller{{ $pax>1?'s':'' }} · {{ ucwords(strtolower(str_replace('_',' ',$p['cabin']))) }}</span>
  </div>

  <div class="hs-grid">
    <aside class="hs-side" id="flSide">
      <div class="hs-box hs-filters">
        <div class="hs-fhead"><h4>Filter by</h4><button type="button" class="linkbtn" id="fReset">Reset all</button></div>
        <div class="fgrp"><h5>Stops</h5>
          <label class="fchk"><input type="checkbox" data-f="stops" value="0"> Direct <em data-c="stops:0"></em></label>
          <label class="fchk"><input type="checkbox" data-f="stops" value="1"> 1 stop <em data-c="stops:1"></em></label>
          <label class="fchk"><input type="checkbox" data-f="stops" value="2"> 2+ stops <em data-c="stops:2"></em></label>
        </div>
        <div class="fgrp"><h5>Total price</h5>
          <div class="range2 single"><input type="range" id="fPrice" min="0" max="100" value="100"></div>
          <div class="rlabels"><span>Up to</span><span id="fPriceL">–</span></div>
        </div>
        <div class="fgrp"><h5>Fare options</h5>
          <label class="fchk"><input type="checkbox" data-f="bag"> Checked bag included <em data-c="bag"></em></label>
          <label class="fchk"><input type="checkbox" data-f="refund"> Refundable fare <em data-c="refund"></em></label>
          <label class="fchk"><input type="checkbox" data-f="change"> Changes allowed <em data-c="change"></em></label>
        </div>
        <div class="fgrp"><h5>Departure from {{ $p['from'] }}</h5>
          <div class="tslots" data-leg="0">
            <button type="button" data-t="0">🌙<b>00–06</b></button><button type="button" data-t="1">🌅<b>06–12</b></button><button type="button" data-t="2">☀️<b>12–18</b></button><button type="button" data-t="3">🌆<b>18–24</b></button>
          </div>
        </div>
        @if($p['ret'])
        <div class="fgrp"><h5>Departure from {{ $p['to'] }}</h5>
          <div class="tslots" data-leg="1">
            <button type="button" data-t="0">🌙<b>00–06</b></button><button type="button" data-t="1">🌅<b>06–12</b></button><button type="button" data-t="2">☀️<b>12–18</b></button><button type="button" data-t="3">🌆<b>18–24</b></button>
          </div>
        </div>
        @endif
        <div class="fgrp"><h5>Max journey time</h5>
          <div class="range2 single"><input type="range" id="fDur" min="0" max="100" value="100"></div>
          <div class="rlabels"><span>Up to</span><span id="fDurL">–</span></div>
        </div>
        <div class="fgrp"><h5>Airlines</h5><div id="fAir"></div></div>
      </div>
    </aside>

    <main>
      <div class="fl-sorts" id="flSorts">
        <button type="button" data-s="best" class="on"><b>Best</b><span>–</span></button>
        <button type="button" data-s="cheap"><b>Cheapest</b><span>–</span></button>
        <button type="button" data-s="fast"><b>Fastest</b><span>–</span></button>
      </div>
      <div class="hs-head">
        <p class="note" id="flCount">Searching hundreds of airlines for {{ $fromCity }} → {{ $toCity }}…</p>
        <div class="hs-tools"><button type="button" class="hbtn hbtn-ghost hs-mobile-filter" id="mFilter">Filters</button></div>
      </div>
      <div class="chips-on" id="chipsOn"></div>
      <div class="fl-progress" id="flProg"><i></i></div>
      <div id="flList">
        @for($i=0;$i<4;$i++)<div class="fcard skel"><i></i><i></i><i class="s"></i></div>@endfor
      </div>
      <div class="hs-more"><button type="button" class="hbtn hbtn-ghost" id="flMore" hidden>Show more flights</button></div>
    </main>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/flights.js') }}?v={{ filemtime(public_path('js/flights.js')) }}"></script>
@endpush

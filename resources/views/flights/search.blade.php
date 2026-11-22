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
  <div class="wrap">@include('partials.search', ['show' => ['flights'], 'from' => $p['from'], 'to' => $p['to'], 'depart' => $p['depart'], 'return' => $p['ret'], 'mc' => $p['mc'], 'adults' => $p['adults'], 'children' => $p['children'], 'infants' => $p['infants'], 'cabin' => $p['cabin']])</div>
</section>

<div class="wrap hs-wrap" id="flApp"
  data-endpoint="{{ route('flights.search.data') }}" data-qs='@json($qs)' data-query="{{ http_build_query($qs) }}" data-select="{{ route('flights.select') }}"
  data-csrf="{{ csrf_token() }}" data-pax="{{ $pax }}" data-back="{{ request()->fullUrl() }}">
  @if($sandbox)<div class="hs-test">Test mode: fares are real but no tickets are issued and no card is charged.</div>@endif
  @if(session('fl_error'))<div class="hs-alert">{{ session('fl_error') }}</div>@endif

  <div class="fl-route">
    @if($p['mc'])
      <div class="mc-chain">@foreach($p['mc'] as $i => $l)<span><b>{{ $names[$l['from']] ?? $l['from'] }}</b> → <b>{{ $names[$l['to']] ?? $l['to'] }}</b> <em>{{ \Carbon\Carbon::parse($l['date'])->format('j M') }}</em></span>@endforeach</div>
      <span class="note">Multi-city · {{ $pax }} traveller{{ $pax>1?'s':'' }} · {{ ucwords(strtolower(str_replace('_',' ',$p['cabin']))) }}</span>
    @else
      <div><b>{{ $fromCity }} ({{ $p['from'] }})</b> {!! $I($p['ret'] ? 'swap' : 'arrow') !!} <b>{{ $toCity }} ({{ $p['to'] }})</b></div>
      <span class="note">{{ \Carbon\Carbon::parse($p['depart'])->format('D j M') }}@if($p['ret']) – {{ \Carbon\Carbon::parse($p['ret'])->format('D j M') }}@endif · {{ $pax }} traveller{{ $pax>1?'s':'' }} · {{ ucwords(strtolower(str_replace('_',' ',$p['cabin']))) }}</span>
    @endif
  </div>

  <div class="hs-grid">
    <aside class="hs-side" id="flSide">
      @php $legList = $p['mc'] ? array_map(fn($l)=>[$l['from'],$l['to']], $p['mc']) : array_values(array_filter([[$p['from'],$p['to']], $p['ret'] ? [$p['to'],$p['from']] : null])); @endphp
      <div class="hs-box hs-filters">
        <div class="hs-fhead"><h4>Filter by</h4><button type="button" class="linkbtn" id="fReset">Reset all</button></div>
        <div class="fgrp"><h5>Stops</h5>
          <label class="fchk"><input type="checkbox" data-f="stops" value="0"> Direct only <em data-c="stops:0"></em></label>
          <label class="fchk"><input type="checkbox" data-f="stops" value="1"> 1 stop <em data-c="stops:1"></em></label>
          <label class="fchk"><input type="checkbox" data-f="stops" value="2"> 2+ stops <em data-c="stops:2"></em></label>
        </div>
        <div class="fgrp"><h5>Total price</h5>
          <div class="range2 single"><input type="range" id="fPrice" min="0" max="100" value="100"></div>
          <div class="rlabels"><span>Up to</span><span id="fPriceL">–</span></div>
        </div>
        <div class="fgrp"><h5>Baggage &amp; fare</h5>
          <label class="fchk"><input type="checkbox" data-f="bag"> Checked bag included <em data-c="bag"></em></label>
          <label class="fchk"><input type="checkbox" data-f="cabinbag"> Cabin bag included <em data-c="cabinbag"></em></label>
          <label class="fchk"><input type="checkbox" data-f="refund"> Refundable fare <em data-c="refund"></em></label>
          <label class="fchk"><input type="checkbox" data-f="change"> Changes allowed <em data-c="change"></em></label>
          <label class="fchk"><input type="checkbox" data-f="seat"> Seat selection available <em data-c="seat"></em></label>
        </div>
        @foreach($legList as $li => [$lf, $lt])
        <div class="fgrp"><h5>{{ $p['mc'] ? 'Flight '.($li+1).': ' : '' }}{{ $lf }} → {{ $lt }}</h5>
          <p class="fsub">Departure</p>
          <div class="tslots" data-leg="{{ $li }}" data-kind="dep">
            <button type="button" data-t="0">🌙<b>00–06</b></button><button type="button" data-t="1">🌅<b>06–12</b></button><button type="button" data-t="2">☀️<b>12–18</b></button><button type="button" data-t="3">🌆<b>18–24</b></button>
          </div>
          <p class="fsub">Arrival</p>
          <div class="tslots" data-leg="{{ $li }}" data-kind="arr">
            <button type="button" data-t="0">🌙<b>00–06</b></button><button type="button" data-t="1">🌅<b>06–12</b></button><button type="button" data-t="2">☀️<b>12–18</b></button><button type="button" data-t="3">🌆<b>18–24</b></button>
          </div>
        </div>
        @endforeach
        <div class="fgrp"><h5>Journey time</h5>
          <div class="range2 single"><input type="range" id="fDur" min="0" max="100" value="100"></div>
          <div class="rlabels"><span>Up to</span><span id="fDurL">–</span></div>
        </div>
        <div class="fgrp"><h5>Layovers</h5>
          <div class="range2 single"><input type="range" id="fLay" min="0" max="100" value="100"></div>
          <div class="rlabels"><span>Max layover</span><span id="fLayL">–</span></div>
          <label class="fchk"><input type="checkbox" data-f="noOvernight"> No overnight layovers <em data-c="noOvernight"></em></label>
          <label class="fchk"><input type="checkbox" data-f="noChange"> No airport changes <em data-c="noChange"></em></label>
          <div id="fVia"></div>
        </div>
        <div class="fgrp" id="fAirportsG"><h5>Airports</h5><div id="fAirports"></div></div>
        <div class="fgrp" id="fAllG"><h5>Alliances</h5><div id="fAll"></div></div>
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
        <p class="note" id="flCount">Searching hundreds of airlines for {{ $p['mc'] ? 'your '.count($p['mc']).'-flight trip' : $fromCity.' → '.$toCity }}…</p>
        <div class="hs-tools"><button type="button" class="hbtn hbtn-ghost hs-mobile-filter" id="mFilter">Filters</button>
          <select id="flSort2" class="hs-sortsel" aria-label="More sorting"><option value="">More sorting…</option><option value="dep">Departure time (earliest)</option><option value="dep_d">Departure time (latest)</option><option value="arr">Arrival time (earliest)</option><option value="stops">Fewest stops</option><option value="lay">Shortest layover</option></select></div>
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

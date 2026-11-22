@extends('layouts.app')
@section('title', 'Booking '.$o->number.' | '.$site)
@section('noindex', '1')
@php
  $I = fn($n,$sw=1.9) => view('partials.icon',['n'=>$n,'sw'=>$sw])->render();
  $legs = $d['journey']['legs'] ?? []; $off = $d['offer'] ?? [];
  $hm = fn($m) => $m ? floor($m/60).'h '.($m%60 ? ($m%60).'m' : '') : '';
  $ok = $o->status === 'fulfilled';
@endphp
@section('content')
<div class="wrap hs-wrap co">
  <div class="bk-hero {{ $ok ? 'ok' : ($o->status === 'needs_attention' ? 'warn' : 'wait') }}">
    @if($ok)<span class="bk-ic">{!! $I('check',2.6) !!}</span><div><h1>Your flight is booked</h1><p>Confirmation sent to <b>{{ $o->email }}</b>. Your e-ticket follows from the airline once issued.</p></div>
    @elseif($o->status === 'needs_attention')<span class="bk-ic">!</span><div><h1>We're finalising your ticket</h1><p>Your payment went through but the airline hasn't confirmed yet. Our team is on it and will email <b>{{ $o->email }}</b>. You won't be charged twice.</p></div>
    @elseif($o->status === 'pending_payment')<span class="bk-ic">…</span><div><h1>Payment not completed</h1><p><a href="{{ route('flights.checkout', $o) }}">Return to checkout</a>.</p></div>
    @else<span class="bk-ic spin">…</span><div><h1>Confirming with the airline</h1><p>Your payment is complete and your seats are reserved. The airline usually confirms within a few minutes. This page updates by itself, and we'll email <b>{{ $o->email }}</b> as soon as it's confirmed.</p></div>@endif
  </div>
  <div class="co-grid">
    <main>
      <div class="co-card bk-det">
        <h2>Itinerary</h2>
        @foreach($legs as $l)
          <div class="sum-leg big">
            <p class="note">{{ $l['dir']==='INBOUND' ? 'Return' : 'Outbound' }} · {{ \Carbon\Carbon::parse($l['dep'])->format('l j F Y') }} · {{ $hm($l['min']) }}</p>
            @foreach($l['segments'] as $k => $s)
              <div class="sl-row">@if(!empty($s['logo']))<img src="{{ $s['logo'] }}" alt="">@endif
                <div><b>{{ substr($s['dep'],11,5) }} {{ $s['fromName'] }} ({{ $s['from'] }})</b><span class="note">{{ $s['airline'] }} {{ $s['flight'] }} · {{ $hm($s['min']) }}</span><b>{{ substr($s['arr'],11,5) }} {{ $s['toName'] }} ({{ $s['to'] }})</b></div></div>
              @if(!empty($l['layovers'][$k]))<p class="note lay">Layover {{ $hm($l['layovers'][$k]['min']) }} in {{ $l['layovers'][$k]['name'] ?: $l['layovers'][$k]['code'] }}</p>@endif
            @endforeach
          </div>
        @endforeach
        <dl class="bk-dl">
          <dt>Booking reference</dt><dd>{{ $o->number }}</dd>
          @if(!empty($f['booking_ref']))<dt>Supplier reference</dt><dd>{{ $f['booking_ref'] }}</dd>@endif
          @foreach($f['ticket_numbers'] ?? [] as $tn)<dt>E-ticket number</dt><dd>{{ $tn }}</dd>@endforeach
          @foreach($f['pnrs'] ?? [] as $pnr)<dt>Airline reference (PNR)</dt><dd><b>{{ $pnr }}</b></dd>@endforeach
          @if(!empty($f['booking_id']))<dt>Booking ID</dt><dd>{{ $f['booking_id'] }}</dd>@endif
          <dt>Passengers</dt><dd>@foreach($d['passengers'] ?? [] as $p){{ $p['title'] }} {{ $p['firstName'] }} {{ $p['lastName'] }}<br>@endforeach</dd>
          <dt>Fare</dt><dd>{{ $off['family'] ?? '' }} · {{ !empty($off['checked']) ? $off['checked']['pieces'].' checked bag' : 'no checked bag' }} · {{ !empty($off['refundable']) ? 'refundable' : 'non-refundable' }}</dd>
          <dt>Total paid</dt><dd><b>{{ $o->money() }}</b></dd>
        </dl>
      </div>
    </main>
    <aside class="co-sum">
      <div class="co-card"><h3>Before you fly</h3><p class="note">Check in online with the airline 24–48 hours before departure using your airline reference. Check visa and passport rules for your destination.</p><a class="hbtn hbtn-ghost" style="width:100%;margin-top:10px" href="{{ route('page','contact') }}">Contact support</a></div>
      <div class="co-card"><h3>Complete your trip</h3>
        <a class="co-x" href="{{ route('hotels.search', ['q' => $legs[0]['toName'] ?? '', 'checkin' => substr($legs[0]['arr'] ?? '',0,10), 'checkout' => isset($legs[1]) ? substr($legs[1]['dep'],0,10) : null]) }}">{!! $I('bed') !!}Hotels at your destination</a>
        <a class="co-x" href="{{ route('esim.index') }}">{!! $I('sim') !!}Travel eSIM</a>
        <a class="co-x" href="{{ route('transfers.index') }}">{!! $I('taxi') !!}Airport transfer</a></div>
    </aside>
  </div>
</div>
@endsection
@if($o->status === 'processing')
@push('scripts')
<script>(function(){var n=0;function poll(){fetch(@json(route('flights.status',$o))).then(function(r){return r.json()}).then(function(d){if(d.status!=='processing'){location.reload()}else if(++n<40){setTimeout(poll,15000)}}).catch(function(){setTimeout(poll,20000)})}setTimeout(poll,8000)})();</script>
@endpush
@endif

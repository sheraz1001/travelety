@extends('layouts.app')
@section('title', 'Booking '.$o->number.' | '.$site)
@section('noindex', '1')
@php
  $I = fn($n,$sw=1.9) => view('partials.icon',['n'=>$n,'sw'=>$sw])->render();
  $g = $d['guest'] ?? [];
  $in = \Carbon\Carbon::parse($d['checkin']); $out = \Carbon\Carbon::parse($d['checkout']);
  $ok = $o->status === 'fulfilled';
@endphp
@section('content')
<div class="wrap hs-wrap co">
  <div class="bk-hero {{ $ok ? 'ok' : ($o->status === 'needs_attention' ? 'warn' : 'wait') }}">
    @if($ok)
      <span class="bk-ic">{!! $I('check',2.6) !!}</span>
      <div><h1>Your booking is confirmed</h1><p>We've emailed the confirmation to <b>{{ $o->email }}</b>. Show it at check-in with your ID.</p></div>
    @elseif($o->status === 'needs_attention')
      <span class="bk-ic">!</span>
      <div><h1>We're finalising your booking</h1><p>Your payment went through, but the hotel hasn't confirmed yet. Our team is on it and will email <b>{{ $o->email }}</b> shortly. You won't be charged twice.</p></div>
    @elseif($o->status === 'pending_payment')
      <span class="bk-ic">…</span>
      <div><h1>Payment not completed</h1><p>This booking hasn't been paid. <a href="{{ route('hotels.checkout', $o) }}">Return to checkout</a>.</p></div>
    @else
      <span class="bk-ic">…</span>
      <div><h1>Booking in progress</h1><p>The hotel is confirming your reservation. Refresh this page in a minute.</p></div>
    @endif
  </div>

  <div class="co-grid">
    <main>
      <div class="co-card bk-det">
        <h2>{{ $d['hotel_name'] }}</h2>
        <p class="note">{{ $d['hotel_address'] }}</p>
        <div class="co-dates">
          <div><small>Check-in</small><b>{{ $in->format('D j M Y') }}</b></div>
          <div><small>Check-out</small><b>{{ $out->format('D j M Y') }}</b></div>
          <div><small>Guests</small><b>{{ $d['adults'] }} adult{{ $d['adults']>1?'s':'' }}, {{ $d['rooms'] }} room{{ $d['rooms']>1?'s':'' }}</b></div>
        </div>
        <dl class="bk-dl">
          <dt>Booking reference</dt><dd>{{ $o->number }}</dd>
          @if(!empty($f['hotel_confirmation']))<dt>Hotel confirmation</dt><dd>{{ $f['hotel_confirmation'] }}</dd>@endif
          @if(!empty($f['booking_id']))<dt>Booking ID</dt><dd>{{ $f['booking_id'] }}</dd>@endif
          <dt>Lead guest</dt><dd>{{ $g['first_name'] ?? '' }} {{ $g['last_name'] ?? '' }}</dd>
          <dt>Room</dt><dd>{{ $d['room_name'] }} · {{ $d['board'] }}</dd>
          <dt>Cancellation</dt><dd>@if($d['refundable']) @php $cp = collect($d['cancel_policies'] ?? [])->sortBy('cancelTime')->first(); @endphp Free cancellation{{ $cp ? ' until '.\Carbon\Carbon::parse($cp['cancelTime'])->format('j M Y, H:i') : '' }} @else Non-refundable @endif</dd>
          <dt>Total paid</dt><dd><b>{{ $o->money() }}</b></dd>
        </dl>
      </div>
    </main>
    <aside class="co-sum">
      <div class="co-card">
        <h3>Need help?</h3>
        <p class="note">To change or cancel, contact us with your booking reference <b>{{ $o->number }}</b>.</p>
        <a class="hbtn hbtn-ghost" style="width:100%" href="{{ route('page','contact') }}">Contact support</a>
      </div>
      <div class="co-card">
        <h3>Complete your trip</h3>
        <a class="co-x" href="{{ route('esim.index') }}">{!! $I('sim') !!}Travel eSIM for your trip</a>
        <a class="co-x" href="{{ route('transfers.index') }}">{!! $I('taxi') !!}Airport transfer to your hotel</a>
      </div>
    </aside>
  </div>
</div>
@endsection

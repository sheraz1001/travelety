@extends('layouts.app')
@section('title', 'Checkout — '.($d['hotel_name'] ?? 'Hotel').' | '.$site)
@section('noindex', '1')
@php
  $I = fn($n,$sw=1.9) => view('partials.icon',['n'=>$n,'sw'=>$sw])->render();
  $g = $d['guest'] ?? [];
  $pay = request('step') === 'pay' && ! empty($g) && ! empty($d['secret_key']);
  $in = \Carbon\Carbon::parse($d['checkin']); $out = \Carbon\Carbon::parse($d['checkout']);
@endphp
@section('content')
<div class="wrap hs-wrap co">
  @if($sandbox)<div class="hs-test">Test mode: use card 4242 4242 4242 4242, any future date and any CVC. No real booking is made.</div>@endif
  <div class="co-steps"><span class="on">1 · Your details</span><span class="{{ $pay ? 'on' : '' }}">2 · Payment</span><span>3 · Confirmation</span></div>

  <div class="co-grid">
    <main>
      @if(! $pay)
      <form class="co-card" method="post" action="{{ route('hotels.guest', $o) }}">
        @csrf
        <h2>Who's checking in?</h2>
        <p class="note">Enter the lead guest's name exactly as on their passport or ID.</p>
        @if($errors->any())<div class="hs-alert">{{ $errors->first() }}</div>@endif
        <div class="co-row">
          <label>First name<input name="first_name" value="{{ old('first_name', $g['first_name'] ?? '') }}" required maxlength="60" autocomplete="given-name"></label>
          <label>Last name<input name="last_name" value="{{ old('last_name', $g['last_name'] ?? '') }}" required maxlength="60" autocomplete="family-name"></label>
        </div>
        <div class="co-row">
          <label>Email<input type="email" name="email" value="{{ old('email', $g['email'] ?? '') }}" required autocomplete="email"><small>Your confirmation is sent here</small></label>
          <label>Mobile number<input name="phone" value="{{ old('phone', $g['phone'] ?? '') }}" maxlength="30" autocomplete="tel" placeholder="+971 50 123 4567"></label>
        </div>
        <label>Special requests <span class="note">(optional, not guaranteed)</span><textarea name="requests" rows="3" maxlength="500" placeholder="e.g. late arrival, high floor">{{ old('requests', $g['requests'] ?? '') }}</textarea></label>
        <label class="co-agree"><input type="checkbox" name="agree" value="1" required> I agree to the <a href="{{ route('page','terms') }}" target="_blank">terms of use</a>, the <a href="{{ route('page','privacy') }}" target="_blank">privacy policy</a> and this room's cancellation policy.</label>
        <button class="hbtn hbtn-orange co-go" type="submit">Continue to payment {!! $I('arrow') !!}</button>
      </form>
      @else
      <div class="co-card">
        <h2>Payment</h2>
        <p class="note">Booking for <b>{{ $g['first_name'] }} {{ $g['last_name'] }}</b> · {{ $g['email'] }} · <a href="{{ route('hotels.checkout', $o) }}">edit</a></p>
        <div id="payment-element" class="co-pay"><p class="note">Loading secure payment…</p></div>
        <p class="note co-secure">{!! $I('shield') !!} Your card details are encrypted and processed by our certified payment partner. We never see or store your card number.</p>
      </div>
      @endif
    </main>

    <aside class="co-sum">
      <div class="co-card">
        @if(!empty($d['hotel_photo']))<img src="{{ $d['hotel_photo'] }}" alt="" class="co-img">@endif
        <h3>{{ $d['hotel_name'] }}</h3>
        @if(!empty($d['stars']))<span class="stars">{{ str_repeat('★', (int) $d['stars']) }}</span>@endif
        <p class="note">{{ $d['hotel_address'] }}</p>
        <div class="co-dates">
          <div><small>Check-in</small><b>{{ $in->format('D j M Y') }}</b></div>
          <div><small>Check-out</small><b>{{ $out->format('D j M Y') }}</b></div>
        </div>
        <p class="note">{{ $d['nights'] }} night{{ $d['nights']>1?'s':'' }} · {{ $d['adults'] }} adult{{ $d['adults']>1?'s':'' }} · {{ $d['rooms'] }} room{{ $d['rooms']>1?'s':'' }}</p>
        <p><b>{{ $d['room_name'] }}</b><br><span class="note">{{ $d['board'] }}</span></p>
        <p class="{{ $d['refundable'] ? 'okc' : 'badc' }}">
          @if($d['refundable'])
            @php $cp = collect($d['cancel_policies'] ?? [])->sortBy('cancelTime')->first(); @endphp
            Free cancellation{{ $cp ? ' until '.\Carbon\Carbon::parse($cp['cancelTime'])->format('j M Y, H:i') : '' }}
          @else Non-refundable: no refund if you cancel or change @endif
        </p>
        @if(!empty($d['price_changed']))<div class="hs-alert">The hotel updated this price since your search. The total below is final.</div>@endif
        <div class="co-total"><span>Total</span><b>{{ $o->money() }}</b></div>
        <p class="note">Includes taxes and fees unless shown as payable at the hotel.</p>
      </div>
    </aside>
  </div>
</div>
@endsection

@if($pay)
@push('scripts')
<script src="https://payment-wrapper.liteapi.travel/dist/liteAPIPayment.js?v=a1"></script>
<script>
(function(){
  var cfg = {
    publicKey: @json(($d['mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox'),
    appearance: { theme: 'flat' },
    options: { business: { name: @json($site) } },
    targetElement: '#payment-element',
    secretKey: @json($d['secret_key']),
    returnUrl: @json(route('hotels.complete', $o))
  };
  function start(){ try { document.querySelector('#payment-element').innerHTML=''; new LiteAPIPayment(cfg).handlePayment(); } catch(e){ document.querySelector('#payment-element').innerHTML='<p class="hs-alert">Payment form could not load. Please refresh the page.</p>'; } }
  if (window.LiteAPIPayment) start(); else window.addEventListener('load', start);
})();
</script>
@endpush
@endif

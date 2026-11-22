@extends('layouts.app')
@section('title', 'Checkout — Flight | '.$site)
@section('noindex', '1')
@php
  $I = fn($n,$sw=1.9) => view('partials.icon',['n'=>$n,'sw'=>$sw])->render();
  $pay = request('step') === 'pay' && ! empty($d['prebook_id']) && ! empty($d['secret_key']);
  $legs = $d['journey']['legs'] ?? [];
  $off = $d['offer'] ?? [];
  $types = array_merge(array_fill(0, $d['adults'] ?? 1, 0), array_fill(0, $d['children'] ?? 0, 1), array_fill(0, $d['infants'] ?? 0, 2));
  $tLabel = [0 => 'Adult', 1 => 'Child (2–11)', 2 => 'Infant (under 2)'];
  $hm = fn($m) => $m ? floor($m/60).'h '.($m%60 ? ($m%60).'m' : '') : '';
  $geo = strtoupper(request()->header('CF-IPCountry') ?: 'AE');
  $selDialCc = isset($dial[$geo]) ? $geo : 'AE';
  if (old('contact.dial')) { $selDialCc = array_search(old('contact.dial'), $dial) ?: $selDialCc; }
@endphp
@section('content')
<div class="wrap hs-wrap co">
  @if($sandbox)<div class="hs-test">Test mode: use card 4242 4242 4242 4242, any future date and any CVC. No ticket is issued.</div>@endif
  <div class="co-steps"><span class="on">1 · Passengers</span><span class="{{ $pay ? 'on' : '' }}">2 · Payment</span><span>3 · Ticket</span></div>

  <div class="co-grid">
    <main>
      @if(! $pay)
      <form class="co-card" method="post" action="{{ route('flights.passengers', $o) }}" id="paxForm">
        @csrf
        <h2>Who's travelling?</h2>
        <p class="note">Enter names exactly as they appear on each passport. Airlines can refuse boarding if they don't match.</p>
        @if(session('fl_error'))<div class="hs-alert">{{ session('fl_error') }}</div>@endif
        @if($errors->any())<div class="hs-alert">{{ $errors->first() }}</div>@endif

        @foreach($types as $i => $t)
          <fieldset class="pax">
            <legend>Passenger {{ $i + 1 }} · {{ $tLabel[$t] }}</legend>
            <input type="hidden" name="pax[{{ $i }}][type]" value="{{ $t }}">
            <div class="co-row3">
              <label>Title<select name="pax[{{ $i }}][title]" required>@foreach(($t ? ['MISS'=>'Miss','MR'=>'Master/Mr'] : ['MR'=>'Mr','MS'=>'Ms','MRS'=>'Mrs','MISS'=>'Miss']) as $k=>$l)<option value="{{ $k }}" @selected(old("pax.$i.title")===$k)>{{ $l }}</option>@endforeach</select></label>
              <label>First &amp; middle names<input name="pax[{{ $i }}][first_name]" value="{{ old("pax.$i.first_name") }}" required maxlength="50"></label>
              <label>Last name<input name="pax[{{ $i }}][last_name]" value="{{ old("pax.$i.last_name") }}" required maxlength="50"></label>
            </div>
            <div class="co-row3">
              <label>Date of birth<input type="date" name="pax[{{ $i }}][birthday]" value="{{ old("pax.$i.birthday") }}" max="{{ now()->subDay()->toDateString() }}" required></label>
              <label>Gender<select name="pax[{{ $i }}][gender]" required><option value="">Select</option><option value="M" @selected(old("pax.$i.gender")==='M')>Male</option><option value="F" @selected(old("pax.$i.gender")==='F')>Female</option></select></label>
              <label>Nationality<select name="pax[{{ $i }}][nationality]" required>@foreach($countries as $cc=>$cn)<option value="{{ $cc }}" @selected(old("pax.$i.nationality", $geo)===$cc)>{{ $cn }}</option>@endforeach</select></label>
            </div>
            <div class="co-row3">
              <label>Passport number<input name="pax[{{ $i }}][doc_number]" value="{{ old("pax.$i.doc_number") }}" required maxlength="20" autocomplete="off"></label>
              <label>Issuing country<select name="pax[{{ $i }}][doc_country]" required>@foreach($countries as $cc=>$cn)<option value="{{ $cc }}" @selected(old("pax.$i.doc_country", $geo)===$cc)>{{ $cn }}</option>@endforeach</select></label>
              <label>Passport expiry<input type="date" name="pax[{{ $i }}][doc_expiry]" value="{{ old("pax.$i.doc_expiry") }}" min="{{ now()->toDateString() }}" required></label>
            </div>
          </fieldset>
        @endforeach

        <fieldset class="pax">
          <legend>Contact details</legend>
          <p class="note">We'll send the e-ticket and any schedule changes here.</p>
          <div class="co-row">
            <label>First name<input name="contact[first_name]" value="{{ old('contact.first_name') }}" required maxlength="50" autocomplete="given-name"></label>
            <label>Last name<input name="contact[last_name]" value="{{ old('contact.last_name') }}" required maxlength="50" autocomplete="family-name"></label>
          </div>
          <div class="co-row">
            <label>Email<input type="email" name="contact[email]" value="{{ old('contact.email') }}" required autocomplete="email"></label>
            <label>Mobile number
              <span class="phone-row"><select name="contact[dial]" required>@foreach($dial as $cc=>$code)<option value="{{ $code }}" @selected($cc===$selDialCc)>{{ $cc }} +{{ $code }}</option>@endforeach</select><input name="contact[phone]" value="{{ old('contact.phone') }}" required maxlength="20" autocomplete="tel-national" placeholder="50 123 4567"></span>
            </label>
          </div>
        </fieldset>

        <label class="co-agree"><input type="checkbox" name="agree" value="1" required> I confirm the passenger details match their passports, and I agree to the <a href="{{ route('page','terms') }}" target="_blank">terms</a>, <a href="{{ route('page','privacy') }}" target="_blank">privacy policy</a> and the fare rules shown.</label>
        <button class="hbtn hbtn-orange co-go" type="submit" id="paxGo">Continue to payment {!! $I('arrow') !!}</button>
        <p class="note" style="margin-top:8px">We'll confirm the seats and final price with the airline before you pay. This can take up to a minute.</p>
      </form>
      @else
      <div class="co-card">
        <h2>Payment</h2>
        <p class="note">Booking for {{ count($d['passengers'] ?? []) }} passenger{{ count($d['passengers'] ?? [])>1?'s':'' }} · {{ $o->email }}</p>
        @if(($d['price_before'] ?? $o->amount) != $o->amount)<div class="hs-alert">The airline updated the fare from {{ $o->currency }} {{ number_format($d['price_before'],2) }} to <b>{{ $o->money() }}</b>. This is the final price.</div>@endif
        <div id="payment-element" class="co-pay"><p class="note">Loading secure payment…</p></div>
        <p class="note co-secure">{!! $I('shield') !!} Your card details are encrypted and processed by our certified payment partner. We never see or store your card number.</p>
      </div>
      @endif
    </main>

    <aside class="co-sum">
      <div class="co-card">
        <h3>Your trip</h3>
        @foreach($legs as $l)
          <div class="sum-leg">
            <p class="note">{{ $l['dir']==='INBOUND' ? 'Return' : 'Outbound' }} · {{ \Carbon\Carbon::parse($l['dep'])->format('D j M') }}</p>
            <div class="sl-row">
              @if(!empty($l['carriers'][0]['logo']))<img src="{{ $l['carriers'][0]['logo'] }}" alt="">@endif
              <div><b>{{ substr($l['dep'],11,5) }} {{ $l['from'] }} → {{ substr($l['arr'],11,5) }} {{ $l['to'] }}</b>@if($l['dayChange'])<sup>+{{ $l['dayChange'] }}</sup>@endif
              <span class="note">{{ $hm($l['min']) }} · {{ $l['stops'] ? $l['stops'].' stop'.($l['stops']>1?'s':'') : 'Direct' }} · {{ collect($l['carriers'])->pluck('name')->implode(', ') }}</span></div>
            </div>
          </div>
        @endforeach
        <div class="sum-fare">
          <b>{{ $off['family'] ?? 'Fare' }}</b>
          <ul>
            <li>{{ !empty($off['cabinBag']) ? '✓ Cabin bag'.(!empty($off['cabinBag']['kg']) ? ' '.$off['cabinBag']['kg'].' kg' : '') : '✕ No cabin bag' }}</li>
            <li>{{ !empty($off['checked']) ? '✓ '.$off['checked']['pieces'].' checked bag'.(!empty($off['checked']['kg']) ? ' '.$off['checked']['kg'].' kg' : '') : '✕ No checked bag' }}</li>
            <li>{{ !empty($off['changeable']) ? '✓ Changes allowed (fees may apply)' : '✕ No changes' }}</li>
            <li>{{ !empty($off['refundable']) ? '✓ Refundable (fees may apply)' : '✕ Non-refundable' }}</li>
          </ul>
        </div>
        <div class="co-total"><span>Total ({{ count($types) }} traveller{{ count($types)>1?'s':'' }})</span><b>{{ $o->money() }}</b></div>
        <p class="note">Taxes and fees included.</p>
      </div>
    </aside>
  </div>
</div>
@endsection

@push('scripts')
@if($pay)
<script src="https://payment-wrapper.liteapi.travel/dist/liteAPIPayment.js?v=a1"></script>
<script>
(function(){
  var cfg = { publicKey: @json(($d['mode'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox'), appearance: { theme: 'flat' },
    options: { business: { name: @json($site) } }, targetElement: '#payment-element', secretKey: @json($d['secret_key']), returnUrl: @json(route('flights.complete', $o)) };
  function start(){ try { document.querySelector('#payment-element').innerHTML=''; new LiteAPIPayment(cfg).handlePayment(); } catch(e){ document.querySelector('#payment-element').innerHTML='<p class="hs-alert">Payment form could not load. Please refresh the page.</p>'; } }
  if (window.LiteAPIPayment) start(); else window.addEventListener('load', start);
})();
</script>
@else
<script>document.getElementById('paxForm')?.addEventListener('submit',function(){var b=document.getElementById('paxGo');b.disabled=true;b.textContent='Confirming with the airline…';});</script>
@endif
@endpush

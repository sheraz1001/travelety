@extends('layouts.app')
@section('title', 'Checkout — Flight | '.$site)
@section('noindex', '1')
@php
  $I = fn($n,$sw=1.9) => view('partials.icon',['n'=>$n,'sw'=>$sw])->render();
  $pay = request('step') === 'pay' && ! empty($d['prebook_id']) && ! empty($d['secret_key']);
  $extrasStep = request('step') === 'extras' && ! empty($d['prebook_id']);
  $sv = $d['services'] ?? [];
  $segInfo = [];
  foreach (($d['journey']['legs'] ?? []) as $li => $l) foreach ($l['segments'] as $sg) if (!empty($sg['key'])) $segInfo[$sg['key']] = ['from' => $sg['from'], 'to' => $sg['to'], 'flight' => $sg['flight'], 'dep' => $sg['dep'], 'leg' => $li];
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
  <div class="co-steps"><span class="on">1 · Passengers</span><span class="{{ $extrasStep || $pay ? 'on' : '' }}">2 · Seats &amp; bags</span><span class="{{ $pay ? 'on' : '' }}">3 · Payment</span><span>4 · Ticket</span></div>

  <div class="co-grid">
    <main>
      @if($extrasStep)
      <form class="co-card" method="post" action="{{ route('flights.extras', $o) }}" id="xForm">
        @csrf
        <h2>Seats, bags &amp; meals</h2>
        <p class="note">Optional. Choose now or skip — you can usually add these later with the airline.</p>
        @if(session('fl_error'))<div class="hs-alert">{{ session('fl_error') }}</div>@endif
        <div class="xpax" id="xPax">@foreach($d['passengers'] as $pi => $pp)<button type="button" data-p="{{ $pi }}" class="{{ $pi ? '' : 'on' }}" @if($pp['passengerType']===2) data-infant="1" @endif>{{ $pp['firstName'] }} {{ substr($pp['lastName'],0,1) }}.<small>{{ ['Adult','Child','Infant'][$pp['passengerType']] }}</small></button>@endforeach</div>

        @if(!empty($sv['seats']))
        <section class="xsec"><h3>{!! $I('user') !!} Choose seats</h3>
          <div class="xsegs" id="xSegs">@foreach(array_keys($sv['seats']) as $k => $seg)<button type="button" data-s="{{ $seg }}" class="{{ $k ? '' : 'on' }}">{{ $segInfo[$seg]['from'] ?? '' }} → {{ $segInfo[$seg]['to'] ?? '' }}<small>{{ $segInfo[$seg]['flight'] ?? '' }}</small></button>@endforeach</div>
          <div class="seatmap-wrap"><div class="seat-legend" id="xLegend"></div><div class="seatmap" id="xMap"></div></div>
          <p class="note" id="xSeatNote"></p>
        </section>
        @endif

        @if(!empty($sv['bags']))
        <section class="xsec"><h3>{!! $I('wallet') !!} Extra baggage</h3>
          <div id="xBags"></div>
        </section>
        @endif

        @if(!empty($sv['meals']))
        <section class="xsec"><h3>Meals &amp; drinks</h3>
          <div id="xMeals"></div>
        </section>
        @endif

        <div id="xInputs"></div>
        <div class="xfoot">
          <div><span class="note">Extras total</span><b id="xTotal">{{ $o->currency }} 0.00</b></div>
          <a class="hbtn hbtn-ghost" href="{{ route('flights.checkout', [$o, 'step' => 'pay']) }}">Skip</a>
          <button class="hbtn hbtn-orange" type="submit" id="xGo">Continue to payment {!! $I('arrow') !!}</button>
        </div>
      </form>
      @elseif(! $pay)
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
        @if(abs(($d['price_before'] ?? 0) - ($d['base_price'] ?? $d['price_before'] ?? 0)) > 0.01)<div class="hs-alert">The airline updated the fare from {{ $o->currency }} {{ number_format($d['price_before'],2) }} to {{ $o->currency }} {{ number_format($d['base_price'],2) }} before extras. The total below is final.</div>@endif
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
            <p class="note">{{ $l['dir']==='INBOUND' ? 'Return' : (str_starts_with($l['dir'],'LEG') ? 'Flight '.substr($l['dir'],3) : 'Outbound') }} · {{ \Carbon\Carbon::parse($l['dep'])->format('D j M') }}</p>
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
        @if(!empty($d['extras']))
          <div class="sum-fare"><b>Extras</b><ul>@foreach($d['extras'] as $x)<li>{{ $d['passengers'][$x['pax']]['firstName'] ?? '' }} · {{ $x['label'] }}{{ isset($segInfo[$x['seg']]) ? ' ('.$segInfo[$x['seg']]['from'].'–'.$segInfo[$x['seg']]['to'].')' : '' }} · {{ $o->currency }} {{ number_format($x['price'],2) }}</li>@endforeach</ul></div>
        @endif
        <div class="co-total"><span>Total ({{ count($types) }} traveller{{ count($types)>1?'s':'' }})</span><b>{{ $o->money() }}</b></div>
        <p class="note">Taxes and fees included.</p>
      </div>
    </aside>
  </div>
</div>
@endsection

@push('scripts')
@if($extrasStep)
@php
  $flx = ['seats' => $sv['seats'] ?? new \stdClass, 'bags' => $sv['bags'] ?? [], 'meals' => $sv['meals'] ?? [], 'segs' => $segInfo, 'cur' => $o->currency, 'pax' => []];
  foreach ($d['passengers'] ?? [] as $pp) { $flx['pax'][] = ['n' => $pp['firstName'].' '.$pp['lastName'], 't' => $pp['passengerType']]; }
@endphp
<script>window.FL_X = {!! json_encode($flx, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) !!};</script>
<script src="{{ asset('js/seatmap.js') }}?v={{ filemtime(public_path('js/seatmap.js')) }}"></script>
@endif
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

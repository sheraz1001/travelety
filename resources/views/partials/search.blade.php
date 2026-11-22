@php
  $tab = $tab ?? 'flights';
  $from = strtoupper($from ?? \App\Models\Setting::get('default_origin'));
  $to = strtoupper($to ?? '');
  $city = $city ?? '';
  $show = array_values(array_filter($show ?? ['flights','hotels','esim','transfers','tours','cars'], fn($t) => $t === 'flights' || ($t === 'hotels' ? \App\Services\Links::hotelsEnabled() : \App\Services\Links::partner($t))));
  $tab = in_array($tab, $show) ? $tab : 'flights';
  $labels = ['flights'=>['Flights','plane'],'hotels'=>['Hotels','hotel'],'transfers'=>['Transfers','car'],'tours'=>['Tours','compass'],'esim'=>['eSIM','sim'],'cars'=>['Car rental','key']];
  $fromA = \App\Models\Airport::byCode($from); $toA = $to ? \App\Models\Airport::byCode($to) : null;
@endphp
<div class="search" id="search">
  <div class="tabs" role="tablist">
    @foreach($show as $t)
      <button type="button" class="tab {{ $tab === $t ? 'active' : '' }}" data-panel="{{ $t }}">@include('partials.icon',['n'=>$labels[$t][1]]){{ $labels[$t][0] }}</button>
    @endforeach
  </div>

  @if(in_array('flights',$show))
  <div class="panel {{ $tab === 'flights' ? 'active' : '' }}" data-name="flights">
    @php
      $mcOn = app(\App\Services\LiteApi::class)->enabled() && \App\Models\Setting::get('flight_engine') !== 'whitelabel';
      $mcLegs = $mc ?? [];
      $tripNow = $mcLegs ? 'mc' : ((isset($depart) && empty($return) && request()->routeIs('flights.search')) ? 'ow' : 'rt');
      $cabinNow = strtoupper((string) ($cabin ?? 'ECONOMY'));
      $cabinVal = ['ECONOMY' => '0', 'BUSINESS' => '1', 'FIRST' => '2', 'PREMIUM_ECONOMY' => '3'][$cabinNow] ?? '0';
    @endphp
    <div class="trip-type">
      <label><input type="radio" name="trip" value="rt" @checked($tripNow==='rt')> Round trip</label>
      <label><input type="radio" name="trip" value="ow" @checked($tripNow==='ow')> One way</label>
      @if($mcOn)<label><input type="radio" name="trip" value="mc" @checked($tripNow==='mc')> Multi-city</label>@endif
    </div>
    <form class="fields flight-form" action="{{ route('go.flights') }}" method="get" autocomplete="off" rel="nofollow">
      <input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}">
      <input type="hidden" name="adults" value="{{ $adults ?? 1 }}"><input type="hidden" name="children" value="{{ $children ?? 0 }}"><input type="hidden" name="infants" value="{{ $infants ?? 0 }}">
      <div class="field" data-ac="from">
        <label>From</label>
        <input placeholder="City or airport" value="{{ $fromA?->city }}" aria-label="From">
        <small>{{ $fromA ? $fromA->iata.' · '.$fromA->name : 'City or airport' }}</small>
        <div class="ac"></div>
        <button type="button" class="swap" aria-label="Swap">⇄</button>
      </div>
      <div class="field" data-ac="to">
        <label>To</label>
        <input placeholder="Where to?" value="{{ $toA?->city }}" aria-label="To">
        <small>{{ $toA ? $toA->iata.' · '.$toA->name : 'Anywhere in the world' }}</small>
        <div class="ac"></div>
      </div>
      <div class="field"><label>Depart</label><input type="date" name="depart" value="{{ $depart ?? '' }}" aria-label="Depart"><small>&nbsp;</small></div>
      <div class="field"><label>Return</label><input type="date" name="return" value="{{ $return ?? '' }}" aria-label="Return"><small>&nbsp;</small></div>
      <div class="field">
        <label>Travellers</label>
        <input class="pax-label" readonly value="1 traveller, Economy" style="cursor:pointer" aria-label="Travellers">
        <small>&nbsp;</small>
        <div class="pax-pop">
          <div class="pax-row"><div>Adults<small>12+ years</small></div><div class="stepper" data-k="adults"><button type="button">−</button><b>1</b><button type="button">+</button></div></div>
          <div class="pax-row"><div>Children<small>2–11 years</small></div><div class="stepper" data-k="children"><button type="button">−</button><b>0</b><button type="button">+</button></div></div>
          <div class="pax-row"><div>Infants<small>Under 2</small></div><div class="stepper" data-k="infants"><button type="button">−</button><b>0</b><button type="button">+</button></div></div>
          <div class="pax-row"><div>Cabin</div><select name="cabin" class="cabin-sel"><option value="0" @selected($cabinVal==='0')>Economy</option>@if($mcOn)<option value="3" @selected($cabinVal==='3')>Premium economy</option>@endif<option value="1" @selected($cabinVal==='1')>Business</option>@if($mcOn)<option value="2" @selected($cabinVal==='2')>First</option>@endif</select></div>
        </div>
      </div>
      <button class="btn" type="submit">Search @include('partials.icon',['n'=>'search','sw'=>2.4])</button>
    </form>
    @if($mcOn)
    <form class="mc-form" action="{{ route('flights.search') }}" method="get" autocomplete="off" @if($tripNow!=='mc') hidden @endif>
      <div class="mc-rows">
        @foreach((count($mcLegs) >= 2 ? $mcLegs : [['from'=>$from ?? '','to'=>$to ?? '','date'=>''],['from'=>$to ?? '','to'=>'','date'=>'']]) as $i => $l)
          @php $fa = !empty($l['from']) ? \App\Models\Airport::byCode($l['from']) : null; $ta = !empty($l['to']) ? \App\Models\Airport::byCode($l['to']) : null; @endphp
          <div class="mc-row">
            <span class="mc-n">Flight {{ $i + 1 }}</span>
            <div class="field" data-ac2><label>From</label><input placeholder="City or airport" value="{{ $fa?->city ?? ($l['from'] ?? '') }}"><input type="hidden" data-code name="legs[{{ $i }}][from]" value="{{ $l['from'] ?? '' }}"><small>{{ $fa ? $fa->iata.' · '.$fa->name : 'City or airport' }}</small><div class="ac"></div></div>
            <div class="field" data-ac2><label>To</label><input placeholder="Where to?" value="{{ $ta?->city ?? ($l['to'] ?? '') }}"><input type="hidden" data-code name="legs[{{ $i }}][to]" value="{{ $l['to'] ?? '' }}"><small>{{ $ta ? $ta->iata.' · '.$ta->name : 'City or airport' }}</small><div class="ac"></div></div>
            <div class="field"><label>Date</label><input type="date" name="legs[{{ $i }}][date]" value="{{ $l['date'] ?? '' }}" required><small>&nbsp;</small></div>
            <button type="button" class="mc-del" aria-label="Remove flight">✕</button>
          </div>
        @endforeach
      </div>
      <div class="mc-foot">
        <button type="button" class="hbtn hbtn-ghost mc-add">+ Add another flight</button>
        <div class="mc-paxsel">
          <label>Adults<select name="adults">@for($i=1;$i<=9;$i++)<option @selected(($adults ?? 1)==$i)>{{ $i }}</option>@endfor</select></label>
          <label>Children<select name="children">@for($i=0;$i<=8;$i++)<option @selected(($children ?? 0)==$i)>{{ $i }}</option>@endfor</select></label>
          <label>Infants<select name="infants">@for($i=0;$i<=4;$i++)<option @selected(($infants ?? 0)==$i)>{{ $i }}</option>@endfor</select></label>
          <label>Cabin<select name="cabin"><option value="0" @selected($cabinVal==='0')>Economy</option><option value="3" @selected($cabinVal==='3')>Premium economy</option><option value="1" @selected($cabinVal==='1')>Business</option><option value="2" @selected($cabinVal==='2')>First</option></select></label>
        </div>
        <button class="btn" type="submit">Search @include('partials.icon',['n'=>'search','sw'=>2.4])</button>
      </div>
    </form>
    @endif
  </div>
  @endif

  @if(in_array('hotels',$show))
  <div class="panel {{ $tab === 'hotels' ? 'active' : '' }}" data-name="hotels">
    @if(app(\App\Services\LiteApi::class)->enabled())
      @include('hotels.partials.form', ['p' => $hp ?? null])
    @else
    <form class="fields hotels hotel-form" action="{{ route('go.hotels') }}" method="get" autocomplete="off">
      <div class="field"><label>Destination</label><input name="city" placeholder="City, area or hotel name" value="{{ $city }}" aria-label="Destination"><small>&nbsp;</small></div>
      <div class="field"><label>Check-in</label><input type="date" name="checkin" aria-label="Check-in"><small>&nbsp;</small></div>
      <div class="field"><label>Check-out</label><input type="date" name="checkout" aria-label="Check-out"><small>&nbsp;</small></div>
      <div class="field"><label>Guests</label><select name="adults" aria-label="Guests">@for($i=1;$i<=6;$i++)<option value="{{ $i }}" @selected($i===2)>{{ $i }} guest{{ $i>1?'s':'' }}</option>@endfor</select><small>&nbsp;</small></div>
      <button class="btn" type="submit">Find hotels</button>
    </form>
    @endif
  </div>
  @endif

  @php $svc = [
    'transfers' => ['Airport transfers, booked ahead.','Fixed price, a driver waiting at arrivals and free waiting time. No haggling with taxis when you land.','Book a transfer'],
    'tours' => ['Tours, tickets & experiences.','Skip-the-line tickets, desert safaris, city tours and day trips, with free cancellation on most.','Explore experiences'],
    'esim' => ['Stay connected in 190+ countries.','Install a travel eSIM before you fly and skip roaming charges.','Get an eSIM'],
    'cars' => ['Compare car rental worldwide.','Local and international suppliers, clear deposits and no hidden extras.','Compare cars'],
  ]; @endphp
  @foreach($svc as $k => [$h,$p,$b])
    @if(in_array($k,$show))
    <div class="panel {{ $tab === $k ? 'active' : '' }}" data-name="{{ $k }}"><div class="service-cta"><p><b>{{ $h }}</b> {{ $p }}</p><a class="btn" href="{{ $k === 'esim' ? route('esim.index') : ($k === 'transfers' ? route('transfers.index') : route('go.partner', $k)) }}" @if(!in_array($k,['esim','transfers'])) rel="nofollow sponsored" @endif>{{ $b }}</a></div></div>
    @endif
  @endforeach
</div>

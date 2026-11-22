@php $p = $p ?? ['q'=>($city ?? ''),'placeId'=>'','checkin'=>now()->addDays(14)->toDateString(),'checkout'=>now()->addDays(16)->toDateString(),'adults'=>2,'rooms'=>1,'children'=>[]]; @endphp
<form class="fields hotels hotel-form hs-form" action="{{ route('hotels.search') }}" method="get" autocomplete="off">
  <input type="hidden" name="placeId" value="{{ $p['placeId'] }}">
  <div class="field" data-hac>
    <label>Destination or hotel</label>
    <input name="q" placeholder="City, area or landmark" value="{{ $p['q'] }}" aria-label="Destination" required>
    <small>{{ $p['q'] ? 'Change destination' : 'Anywhere in the world' }}</small>
    <div class="ac"></div>
  </div>
  <div class="field"><label>Check-in</label><input type="date" name="checkin" value="{{ $p['checkin'] }}" min="{{ now()->toDateString() }}" aria-label="Check-in" required><small>&nbsp;</small></div>
  <div class="field"><label>Check-out</label><input type="date" name="checkout" value="{{ $p['checkout'] }}" min="{{ now()->addDay()->toDateString() }}" aria-label="Check-out" required><small>&nbsp;</small></div>
  <div class="field"><label>Guests &amp; rooms</label>
    <select name="adults" aria-label="Adults">@for($i=1;$i<=8;$i++)<option value="{{ $i }}" @selected($i==$p['adults'])>{{ $i }} adult{{ $i>1?'s':'' }}</option>@endfor</select>
    <small><select name="rooms" aria-label="Rooms" class="mini-sel">@for($i=1;$i<=4;$i++)<option value="{{ $i }}" @selected($i==$p['rooms'])>{{ $i }} room{{ $i>1?'s':'' }}</option>@endfor</select></small>
  </div>
  <button class="btn" type="submit">Search hotels @include('partials.icon',['n'=>'search','sw'=>2.4])</button>
</form>

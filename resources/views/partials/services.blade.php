@php $items = [
 ['hotels','hotel','Hotels & apartments','Compare stays from budget rooms to five-star resorts, many with free cancellation.','Find a stay'],
 ['transfers','car','Airport transfers','A private driver at arrivals for a fixed price, booked before you fly.','Book a ride'],
 ['tours','compass','Tours & attractions','Tickets for landmarks, theme parks, safaris and guided city tours.','See experiences'],
 ['esim','sim','Travel eSIM','Data the moment you land. No SIM swaps, no roaming bill surprises.','Choose a plan'],
 ['insurance','shield','Travel insurance','Medical cover, trip cancellation and lost luggage. Many visas require it.','Get covered'],
 ['compensation','clock','Flight delayed?','You may be owed compensation for delayed or cancelled flights. Check in two minutes.','Check my flight'],
]; @endphp
<div class="services">
 @foreach($items as [$k,$ic,$h,$p,$m])
  <a class="svc" href="{{ $k === 'hotels' && isset($city) ? route('go.hotels', ['city'=>$city]) : route('go.partner', $k) }}" rel="nofollow sponsored"><div class="ic">@include('partials.icon',['n'=>$ic])</div><h3>{{ $h }}{{ isset($city) && in_array($k,['hotels','transfers','tours']) ? ' in '.$city : '' }}</h3><p>{{ $p }}</p><span class="more">{{ $m }} →</span></a>
 @endforeach
</div>

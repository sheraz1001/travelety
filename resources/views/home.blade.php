@extends('layouts.app')
@php
  $I = fn($n,$sw=1.9) => view('partials.icon',['n'=>$n,'sw'=>$sw])->render();
  $img = fn($f) => asset('img/home/'.$f.'.jpg');
  $photo = ['istanbul'=>'istanbul','tokyo'=>'tokyo','paris'=>'paris','bali'=>'bali','london'=>'london','bangkok'=>'bangkok','sydney'=>'sydney','new-york'=>'newyork','rome'=>'rome','dubai'=>'dubai','singapore'=>'singapore','cape-town'=>'capetown','male'=>'maldives','maldives'=>'maldives','santorini'=>'santorini'];
  $fallback = ['mountains','beach','road','santorini','wing'];
  $cc = ['turkey'=>'TR','united-kingdom'=>'GB','united-states'=>'US','saudi-arabia'=>'SA','egypt'=>'EG','france'=>'FR','europe'=>'EU','asia'=>'AS','middle-east-and-north-africa'=>'ME','latin-america'=>'LA','africa'=>'AF','world'=>'GL','japan'=>'JP','thailand'=>'TH','indonesia'=>'ID','india'=>'IN','singapore'=>'SG','malaysia'=>'MY'];
  $money = fn($v) => '$'.str_replace('.00', '', number_format((float)$v, 2));
@endphp
@section('content')
<section class="ph-hero">
  <div class="ph-bg" id="heroBg">
    <img src="{{ $img('santorini') }}" class="on" data-c="Santorini, Greece" alt="">
    <img src="{{ $img('tokyo') }}" data-c="Tokyo, Japan" alt="" loading="lazy">
    <img src="{{ $img('newyork') }}" data-c="New York, USA" alt="" loading="lazy">
    <img src="{{ $img('maldives') }}" data-c="Maldives" alt="" loading="lazy">
    <img src="{{ $img('capetown') }}" data-c="Cape Town, South Africa" alt="" loading="lazy">
  </div>
  <div class="wrap ph-hero-in">
    <h1>One trip. <em>Everything</em> booked.</h1>
    <p class="sub">Compare flights and hotels from hundreds of airlines and booking sites, then add an eSIM, an airport transfer and tours — in one place.</p>
    <div class="ph-credit">{!! $I('pin') !!}<span id="heroCap">Santorini, Greece</span></div>
  </div>
</section>

<div class="wrap ph-search">
  @include('partials.search')

  <div class="qs">
    <a href="{{ route('flights.index') }}"><span class="ic" style="background:#eef4ff;color:#1463ff">{!! $I('plane') !!}</span>Flights</a>
    <a href="{{ route('hotels.index') }}"><span class="ic" style="background:#fff1ea;color:#ff6b2c">{!! $I('bed') !!}</span>Hotels</a>
    <a href="{{ route('esim.index') }}"><span class="ic" style="background:#f1ecff;color:#7a4dff">{!! $I('sim') !!}</span>eSIM data</a>
    <a href="{{ route('transfers.index') }}"><span class="ic" style="background:#e7f8f1;color:#00a870">{!! $I('taxi') !!}</span>Transfers</a>
    @if(\App\Services\Links::partner('cars'))<a href="{{ route('go.partner','cars') }}" rel="nofollow sponsored"><span class="ic" style="background:#fff6e0;color:#e5a000">{!! $I('car') !!}</span>Car rental</a>@endif
    @if(\App\Services\Links::partner('tours'))<a href="{{ route('go.partner','tours') }}" rel="nofollow sponsored"><span class="ic" style="background:#ffeef3;color:#e5326f">{!! $I('ticket') !!}</span>Tours</a>@endif
    <a href="{{ route('blog.index') }}"><span class="ic" style="background:#e9f6ff;color:#0a8bd6">{!! $I('book') !!}</span>Guides</a>
    <a href="#alerts"><span class="ic" style="background:#eef0f5;color:#3b4660">{!! $I('bell') !!}</span>Price alerts</a>
  </div>
</div>

<main class="wrap ph-main">

  <section class="blk rv">
    <div class="sh"><div><h2>Offers for your next trip</h2><p>Add-ons that make arriving easy</p></div></div>
    <div class="promo">
      <button class="parr l" id="pl" aria-label="Previous">{!! $I('left',2.2) !!}</button>
      <div class="ptrack" id="ptrack">
        <a class="pcard c1" href="{{ route('esim.index') }}"><img src="{{ $img('phone') }}" alt="" loading="lazy"><div><small>eSIM · {{ $esimCount }} destinations</small><h3>Land connected. Data plans from $4</h3><span class="cta">Find your eSIM {!! $I('arrow') !!}</span></div></a>
        <a class="pcard c2" href="{{ route('transfers.index') }}"><img src="{{ $img('car') }}" alt="" loading="lazy"><div><small>Airport transfers</small><h3>Fixed price, driver waiting at arrivals</h3><span class="cta">See prices {!! $I('arrow') !!}</span></div></a>
        <a class="pcard c3" href="#alerts"><img src="{{ $img('wing') }}" alt="" loading="lazy"><div><small>Price alerts</small><h3>We'll email you when fares drop</h3><span class="cta">Create alert {!! $I('arrow') !!}</span></div></a>
        @if(\App\Services\Links::partner('cars'))<a class="pcard c4" href="{{ route('go.partner','cars') }}" rel="nofollow sponsored"><img src="{{ $img('road') }}" alt="" loading="lazy"><div><small>Car rental</small><h3>Compare cars from local and global brands</h3><span class="cta">Search cars {!! $I('arrow') !!}</span></div></a>@endif
        @if(\App\Services\Links::partner('tours'))<a class="pcard c1" href="{{ route('go.partner','tours') }}" rel="nofollow sponsored"><img src="{{ $img('rome') }}" alt="" loading="lazy"><div><small>Tours &amp; tickets</small><h3>Skip-the-line tickets for top attractions</h3><span class="cta">Explore {!! $I('arrow') !!}</span></div></a>@endif
      </div>
      <button class="parr r" id="pr" aria-label="Next">{!! $I('right',2.2) !!}</button>
      <div class="dots" id="pdots"></div>
    </div>
  </section>

  @if($featured->count())
  <section class="blk rv" id="destinations">
    <div class="sh"><div><h2>Trending destinations</h2><p>Where travellers are heading this season</p></div><span class="sp"></span><a href="{{ route('travel.index') }}" class="more">All destinations {!! $I('arrow') !!}</a></div>
    <div class="bento">
      @foreach($featured as $i => $d)
        @php $cls = $i === 0 ? 'big' : ($i === 5 ? 'wide' : ''); $ph = $photo[$d->slug] ?? $fallback[$i % 5]; @endphp
        <a class="dcard2 {{ $cls }}" href="{{ $d->is_published ? $d->guideUrl() : route('flights.to', $d->slug) }}">
          <img src="{{ $img($ph) }}" alt="{{ $d->city }}" loading="lazy">
          @if($i === 0)<span class="badge2">TRENDING</span>@endif
          <div class="lbl"><h3>{{ $d->city }}</h3><p>{{ $d->country }}@if($i === 0 && $d->tagline) · {{ $d->tagline }}@endif</p>
            <div class="ql"><span>Flights</span><span>Hotels</span>@if($d->is_published)<span>Guide</span>@endif</div></div>
        </a>
      @endforeach
    </div>
  </section>
  @endif

  @if($routes->count())
  <section class="blk rv">
    <div class="sh"><div><h2>Popular flight routes</h2><p>Recent lowest fares travellers found. Prices change daily.</p></div><span class="sp"></span><a href="{{ route('flights.index') }}" class="more">All routes {!! $I('arrow') !!}</a></div>
    <div class="routes2">
      @foreach($routes as $i => $r)
        @php $dst = $r->destination(); $ph = $photo[$dst?->city_slug] ?? $fallback[$i % 5]; @endphp
        <a class="route2" href="{{ $r->url() }}">
          <img src="{{ $img($ph) }}" alt="" loading="lazy">
          <div class="t"><b>{{ $r->origin()?->city }} {!! $I('plane') !!} {{ $dst?->city }}</b><small>{{ $r->origin_iata }} – {{ $r->destination_iata }} · Round trip</small></div>
          <span class="go">{{ $r->min_price ? 'From '.\App\Services\Links::money($r->min_price) : 'See fares' }} →</span>
        </a>
      @endforeach
    </div>
  </section>
  @endif

  <section class="blk rv">
    <div class="esim2">
      <div>
        <small class="kick">TRAVELETY eSIM</small>
        <h2>Mobile data in {{ $esimCount }} destinations, installed in two minutes</h2>
        <p>Buy before you fly, scan the QR code, and you're online the moment you land. No roaming bills, no SIM swapping.</p>
        <div class="feats">
          <div>{!! $I('check',2.2) !!}Country, regional and global plans</div>
          <div>{!! $I('check',2.2) !!}Unlimited and fixed-data options</div>
          <div>{!! $I('check',2.2) !!}Keep your WhatsApp number</div>
        </div>
        <a href="{{ route('esim.index') }}" class="hbtn hbtn-orange">Browse all eSIMs {!! $I('arrow') !!}</a>
      </div>
      <div class="phone3" aria-hidden="true"><div class="ph"><div class="notch"></div>
        <div class="card">Türkiye · Active<b>8.4 GB left</b>of 15 GB · 21 days<div class="bar"><i></i></div></div>
        <div class="row"><span>Network</span><b>5G</b></div>
        <div class="row"><span>Valid until</span><b>3 Jan</b></div>
        <div class="row"><span>Hotspot</span><b>On</b></div>
      </div></div>
      <div class="etable">
        <div class="eh" id="etabs">@foreach(array_keys($esimGroups) as $j => $label)<button type="button" class="{{ $j ? '' : 'on' }}" data-e="{{ $j }}">{{ $label }}</button>@endforeach</div>
        @foreach(array_values($esimGroups) as $j => $rows)
          <div class="erows" data-e="{{ $j }}" @if($j) hidden @endif>
            @foreach($rows as $e)
              <a class="erow" href="{{ route('esim.show', $e->slug) }}"><span class="cc">{{ $cc[$e->slug] ?? strtoupper(substr($e->slug,0,2)) }}</span><div><b>{{ $e->name }}</b><small>{{ $e->plans }} plans{{ $e->unlimited ? ' · unlimited available' : '' }}</small></div><div class="pr">from<strong>{{ $money($e->from_price) }}</strong></div><span class="bb">View</span></a>
            @endforeach
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <section class="blk rv">
    <div class="sh"><div><h2>Complete your trip</h2><p>Everything after the flight</p></div></div>
    <div class="trio">
      <a class="tcard" href="{{ route('transfers.index') }}"><div class="im"><div class="imw"><img src="{{ $img('car') }}" alt="" loading="lazy"></div><span class="ic" style="background:#00a870">{!! $I('taxi') !!}</span></div><div class="bd"><h3>Airport transfers</h3><p>Private cars and vans at a fixed price, booked in advance.</p><ul><li>{!! $I('check',2.2) !!}Meet &amp; greet at arrivals</li><li>{!! $I('check',2.2) !!}Flight tracking for delays</li><li>{!! $I('check',2.2) !!}Sedan, minivan or luxury</li></ul><span class="hbtn hbtn-ghost">Book a transfer</span></div></a>
      @if(\App\Services\Links::partner('cars'))
      <a class="tcard" href="{{ route('go.partner','cars') }}" rel="nofollow sponsored"><div class="im"><div class="imw"><img src="{{ $img('road') }}" alt="" loading="lazy"></div><span class="ic" style="background:#e5a000">{!! $I('car') !!}</span></div><div class="bd"><h3>Car rental</h3><p>Compare rental companies at airports and city locations.</p><ul><li>{!! $I('check',2.2) !!}Global and local suppliers</li><li>{!! $I('check',2.2) !!}Clear fuel and mileage terms</li><li>{!! $I('check',2.2) !!}One-way rentals</li></ul><span class="hbtn hbtn-ghost">Search cars</span></div></a>
      @endif
      @if(\App\Services\Links::partner('tours'))
      <a class="tcard" href="{{ route('go.partner','tours') }}" rel="nofollow sponsored"><div class="im"><div class="imw"><img src="{{ $img('rome') }}" alt="" loading="lazy"></div><span class="ic" style="background:#e5326f">{!! $I('ticket') !!}</span></div><div class="bd"><h3>Tours &amp; attractions</h3><p>Tickets, day trips and experiences in thousands of cities.</p><ul><li>{!! $I('check',2.2) !!}Skip-the-line entry</li><li>{!! $I('check',2.2) !!}Mobile tickets</li><li>{!! $I('check',2.2) !!}Free cancellation on many</li></ul><span class="hbtn hbtn-ghost">Explore experiences</span></div></a>
      @endif
    </div>
  </section>

  <section class="blk rv">
    <div class="why2">
      <div><span class="ic">{!! $I('search') !!}</span><h4>Compare in one search</h4><p>Fares and room rates from many airlines and booking sites side by side.</p></div>
      <div><span class="ic">{!! $I('wallet') !!}</span><h4>No hidden markup</h4><p>The price you see is the provider's price. We don't add booking fees.</p></div>
      <div><span class="ic">{!! $I('bell') !!}</span><h4>Fare alerts</h4><p>Pick a route and get an email when the price drops.</p></div>
      <div><span class="ic">{!! $I('shield') !!}</span><h4>Book with trusted partners</h4><p>You pay the airline, hotel or provider directly, on their secure checkout.</p></div>
    </div>
  </section>

  @if($posts->count())
  <section class="blk rv">
    <div class="sh"><div><h2>Travel guides</h2><p>Plan smarter with local tips</p></div><span class="sp"></span><a href="{{ route('blog.index') }}" class="more">All guides {!! $I('arrow') !!}</a></div>
    <div class="grid-3">@foreach($posts as $post) @include('partials.post-card') @endforeach</div>
  </section>
  @endif
</main>

@include('partials.alerts')

<div class="wrap">
  <section class="blk rv">
    <div class="seo">
      <h2>Explore more on {{ \App\Models\Setting::get('site_name') }}</h2>
      <div class="cols">
        @if($routes->count())<div><h5>Top flight routes</h5>@foreach($routes->take(6) as $r)<a href="{{ $r->url() }}">{{ $r->origin()?->city }} to {{ $r->destination()?->city }}</a>@endforeach</div>@else<div><h5>More destinations</h5>@foreach(['rome'=>'Rome','bali'=>'Bali','singapore'=>'Singapore','sydney'=>'Sydney','cairo'=>'Cairo','toronto'=>'Toronto'] as $sl=>$c)<a href="{{ route('flights.to',$sl) }}">Flights to {{ $c }}</a>@endforeach</div>@endif
        <div><h5>Flights to</h5>@foreach(['london'=>'London','paris'=>'Paris','istanbul'=>'Istanbul','bangkok'=>'Bangkok','tokyo'=>'Tokyo','new-york'=>'New York'] as $sl=>$c)<a href="{{ route('flights.to',$sl) }}">Flights to {{ $c }}</a>@endforeach</div>
        <div><h5>eSIM by country</h5>@foreach(['turkey'=>'Turkey','japan'=>'Japan','united-states'=>'USA','united-kingdom'=>'UK','thailand'=>'Thailand','saudi-arabia'=>'Saudi Arabia'] as $sl=>$c)<a href="{{ route('esim.show',$sl) }}">{{ $c }} eSIM</a>@endforeach</div>
        <div><h5>Regional eSIM</h5>@foreach(['europe'=>'Europe','asia'=>'Asia','middle-east-and-north-africa'=>'Middle East','latin-america'=>'Latin America','africa'=>'Africa','world'=>'Global'] as $sl=>$c)<a href="{{ route('esim.show',$sl) }}">{{ $c }} eSIM</a>@endforeach</div>
        <div><h5>Travel</h5><a href="{{ route('travel.index') }}">Destination guides</a><a href="{{ route('blog.index') }}">Travel blog</a><a href="{{ route('transfers.index') }}">Airport transfers</a><a href="{{ route('hotels.index') }}">Hotels</a><a href="{{ route('flights.index') }}">All flight routes</a></div>
      </div>
    </div>
  </section>
</div>

<section id="faq" class="ph-faq">
  <div class="wrap">
    <div class="sh" style="justify-content:center;text-align:center"><div><h2>Questions, answered</h2></div></div>
    <div class="faq">@include('partials.faqs', ['hideHeading' => true, 'faqs' => $faqs])</div>
  </div>
</section>
@endsection

@push('scripts')
<script>
(()=>{
  const hi=[...document.querySelectorAll('#heroBg img')];let hx=0;const cap=document.getElementById('heroCap');
  if(hi.length>1)setInterval(()=>{hi[hx].classList.remove('on');hx=(hx+1)%hi.length;hi[hx].classList.add('on');cap.textContent=hi[hx].dataset.c},6000);
  const pt=document.getElementById('ptrack');
  if(pt){const cards=[...pt.children],pd=document.getElementById('pdots');cards.forEach(()=>pd.appendChild(document.createElement('i')));
    const step=()=>cards[0].offsetWidth+16,upd=()=>{const i=Math.round(pt.scrollLeft/step());[...pd.children].forEach((d,j)=>d.classList.toggle('on',j===i))};
    pt.addEventListener('scroll',upd);upd();
    document.getElementById('pr').onclick=()=>pt.scrollBy({left:step()});document.getElementById('pl').onclick=()=>pt.scrollBy({left:-step()});
    setInterval(()=>{if(pt.matches(':hover'))return;if(pt.scrollLeft+pt.clientWidth>=pt.scrollWidth-5)pt.scrollTo({left:0});else pt.scrollBy({left:step()})},6500);}
  document.querySelectorAll('#etabs button').forEach(b=>b.onclick=()=>{document.querySelectorAll('#etabs button').forEach(x=>x.classList.toggle('on',x===b));document.querySelectorAll('.erows').forEach(r=>r.hidden=r.dataset.e!==b.dataset.e)});
})();
</script>
@endpush

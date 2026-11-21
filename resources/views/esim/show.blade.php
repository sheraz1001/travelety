@extends('layouts.app')
@php
  $min = $plans->min('price_usd');
  $isMulti = $first->group_type !== 'country';
  $daysList = $plans->pluck('days')->unique()->sort()->values();
  $faqs = [
    ['q' => "How much does an eSIM for {$name} cost?", 'a' => "Plans for {$name} start from US$".number_format($min, 2)." (about AED ".number_format($min * \App\Models\EsimPlan::USD_TO_AED).") for ".$plans->first()->data_label." valid for ".$plans->first()->days." days. There are ".$plans->count()." plans to choose from".($plans->contains(fn($p)=>$p->isUnlimited()) ? ', including unlimited data.' : '.')],
    ['q' => "Which network will I use in {$name}?", 'a' => $info['networks'] ? "Plans connect to {$info['networks']}." : "The plan connects to local partner networks in {$name}."],
    ['q' => 'Can I top up?', 'a' => $info['top_ups'] ? 'Yes, top-ups are available, so you can add more data without installing a new eSIM.' : 'Top-ups may not be available for every plan. Check the plan details before you buy.'],
    ['q' => 'Does the eSIM include calls and SMS?', 'a' => $info['voice'] ? 'Some plans include calls and texts as well as data. Look for the "Calls & SMS" label.' : 'These plans are data-only. You can still call and message using WhatsApp, FaceTime and other apps, and your home SIM stays active for SMS codes.'],
    ['q' => 'When should I install it?', 'a' => 'Install the eSIM while you are still on Wi-Fi before you travel, then switch it on for data when you land.'],
  ];
@endphp
@section('title', "eSIM for {$name} — {$plans->count()} Data Plans from US$".number_format($min, 2)." | {$site}")
@section('description', "Compare {$plans->count()} travel eSIM plans for {$name} from US$".number_format($min, 2).". ".($info['networks'] ? "Networks: ".\Illuminate\Support\Str::limit($info['networks'], 60).". " : '')."Install before you fly, no roaming fees.")
@section('content')
<section class="page-hero">
  <div class="wrap">
    @include('partials.crumbs', ['items' => ['eSIM' => route('esim.index'), $name => null]])
    <h1>eSIM for {{ $name }}</h1>
    <p class="lead">{{ $plans->count() }} data plans from <b>US${{ number_format($min, 2) }}</b> (≈ AED {{ number_format($min * \App\Models\EsimPlan::USD_TO_AED) }}).{{ $isMulti ? ' One eSIM for every country on your trip.' : ' Install before you fly and get online the moment you land in '.$name.'.' }}</p>
  </div>
</section>

<div class="wrap">
  <div class="facts">
    <div class="fact"><small>From</small><b>US${{ number_format($min, 2) }}</b><span>≈ AED {{ number_format($min * \App\Models\EsimPlan::USD_TO_AED) }}</span></div>
    <div class="fact"><small>Best value</small><b>{{ $bestValue ? 'US$'.number_format($bestValue->pricePerGb(), 2).'/GB' : '—' }}</b><span>{{ $bestValue ? $bestValue->data_label.' · '.$bestValue->days.' days' : 'Compare plans below' }}</span></div>
    <div class="fact"><small>Network</small><b style="font-size:16px;line-height:1.3">{{ \Illuminate\Support\Str::limit($info['networks'] ?? 'Local partner networks', 40) }}</b><span>{{ str_contains((string) $info['networks'], '5G') ? '5G where available' : '4G/LTE' }}</span></div>
    <div class="fact"><small>Top-ups</small><b>{{ $info['top_ups'] ? 'Available' : 'Check plan' }}</b><span>{{ $info['voice'] ? 'Data + calls plans too' : 'Data-only plans' }}</span></div>
  </div>
</div>

<section style="padding-top:60px">
  <div class="wrap layout">
    <div>
      <div class="esim-filters">
        <div class="tabs-links" data-filter="days">
          <a href="#" class="on" data-v="all">Any length</a>
          @foreach($daysList as $d)<a href="#" data-v="{{ $d }}">{{ $d }} day{{ $d > 1 ? 's' : '' }}</a>@endforeach
        </div>
        <div class="tabs-links" data-filter="data">
          <a href="#" class="on" data-v="all">Any data</a><a href="#" data-v="small">Up to 3 GB</a><a href="#" data-v="mid">5–10 GB</a><a href="#" data-v="big">20 GB+</a><a href="#" data-v="unl">Unlimited</a>
        </div>
      </div>
      <div class="plans" id="plans">
        @foreach($plans as $p)
          @php $band = $p->isUnlimited() ? 'unl' : ($p->data_gb <= 3 ? 'small' : ($p->data_gb <= 10 ? 'mid' : 'big')); @endphp
          <div class="plan" data-days="{{ $p->days }}" data-data="{{ $band }}">
            <div class="plan-top">
              <div><b class="plan-data">{{ $p->data_label }}</b><span class="plan-days">{{ $p->days }} days</span></div>
              @if($bestValue && $bestValue->id === $p->id)<span class="badge">Best value</span>@elseif($loop->first)<span class="badge" style="background:#fff1eb;color:#d8501f">Cheapest</span>@endif
            </div>
            <div class="plan-meta">
              @if($p->operator)<span>{{ $p->operator }}</span>@endif
              @if(str_contains($p->plan_type, 'voice'))<span>Calls &amp; SMS</span>@endif
              @if($p->pricePerGb())<span>US${{ number_format($p->pricePerGb(), 2) }}/GB</span>@endif
            </div>
            <div class="plan-bottom">
              <div><b class="plan-price">US${{ number_format($p->price_usd, 2) }}</b><small>≈ {{ $p->priceAed() }}</small></div>
              <a class="btn" href="{{ route('esim.go', $p) }}" rel="nofollow sponsored">Get eSIM</a>
            </div>
          </div>
        @endforeach
      </div>
      <p class="note" id="noPlans" style="display:none">No plans match these filters.</p>
      <p class="note" style="margin-top:14px">Prices in US dollars from our eSIM partner, updated daily. AED amounts are approximate. You complete your purchase with the partner, who delivers the eSIM and provides support.</p>

      <div class="prose" style="margin-top:40px">
        @if($info['coverage'])<h2>Countries covered</h2><p>{{ $info['coverage'] }}</p>@endif
        <h2>Good to know</h2>
        <ul>
          <li><strong>Network:</strong> {{ $info['networks'] ?? 'Local partner networks' }}.</li>
          <li><strong>Validity:</strong> on most plans the validity period starts when the eSIM first connects to a network in its coverage area, not when you buy it.</li>
          <li><strong>Your home number:</strong> keep your normal SIM active for calls, SMS and bank verification codes; use the eSIM for data.</li>
          @if($info['top_ups'])<li><strong>Top-ups:</strong> available, so you can add data without a new eSIM.</li>@endif
        </ul>
        @if($info['other'])<p>{{ $info['other'] }}</p>@endif
        <h2>How to install your {{ $name }} eSIM</h2>
        <ol>
          <li>Check that your phone supports eSIM and is carrier-unlocked.</li>
          <li>Choose a plan above and complete your purchase with our partner.</li>
          <li>While on Wi-Fi, scan the QR code or install it in the partner's app (Settings → Mobile data → Add eSIM).</li>
          <li>When you land, turn on the eSIM line for mobile data and enable data roaming for it.</li>
        </ol>
        @include('partials.faqs', ['faqs' => $faqs, 'heading' => "{$name} eSIM: FAQs"])
      </div>
    </div>

    <aside class="sidebar">
      @if($route || $destination)
      <div class="box"><h3>Travelling to {{ $name }}?</h3>
        @if($route)<a class="li" href="{{ $route->url() }}">Flights from {{ $route->origin()?->city }} <span>{{ $route->min_price ? \App\Services\Links::money($route->min_price) : '→' }}</span></a>@endif
        @if($destination)<a class="li" href="{{ $destination->guideUrl() }}">{{ $destination->city }} travel guide <span>→</span></a>@endif
      </div>
      @endif
      @if($regional->count())
      <div class="box"><h3>Visiting more countries?</h3>
        @foreach($regional as $r)<a class="li" href="{{ route('esim.show', $r->slug) }}">{{ $r->name }} eSIM <span>from US${{ number_format($r->from_usd, 2) }}</span></a>@endforeach
      </div>
      @endif
      @if($related->count())
      <div class="box"><h3>Nearby destinations</h3>
        @foreach($related as $r)<a class="li" href="{{ route('esim.show', $r->slug) }}">{{ $r->name }} <span>US${{ number_format($r->from_usd, 2) }}</span></a>@endforeach
      </div>
      @endif
    </aside>
  </div>
</section>
@push('schema')
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'Product','name'=>"Travel eSIM for {$name}",'description'=>"Prepaid travel data eSIM plans for {$name}",'offers'=>['@type'=>'AggregateOffer','priceCurrency'=>'USD','lowPrice'=>number_format($min,2,'.',''),'highPrice'=>number_format($plans->max('price_usd'),2,'.',''),'offerCount'=>$plans->count()]], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
@push('scripts')
<script>
(function(){
  var f={days:'all',data:'all'};
  document.querySelectorAll('.esim-filters [data-filter]').forEach(function(g){
    g.addEventListener('click',function(e){var a=e.target.closest('a');if(!a)return;e.preventDefault();
      g.querySelectorAll('a').forEach(function(x){x.classList.toggle('on',x===a)});f[g.dataset.filter]=a.dataset.v;apply();});
  });
  function apply(){var n=0;document.querySelectorAll('#plans .plan').forEach(function(p){
    var ok=(f.days==='all'||p.dataset.days===f.days)&&(f.data==='all'||p.dataset.data===f.data);p.style.display=ok?'':'none';if(ok)n++;});
    document.getElementById('noPlans').style.display=n?'none':'block';}
})();
</script>
@endpush
@endsection

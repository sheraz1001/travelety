@php $es = \App\Http\Controllers\EsimController::forCountry($country ?? null); @endphp
@if($es)
<div class="esim-box">
  <div><b>Stay online in {{ $es->name }}</b><span>{{ $es->plans }} eSIM data plans from US${{ number_format($es->from_usd, 2) }} (≈ AED {{ number_format($es->from_usd * \App\Models\EsimPlan::USD_TO_AED) }}). No roaming bills.</span></div>
  <a class="btn" href="{{ route('esim.show', $es->slug) }}">Compare eSIMs</a>
</div>
@endif

@php
  $all = ['Home' => route('home')] + $items;
  $ld = []; $i = 1;
  foreach ($all as $label => $url) { $ld[] = ['@type' => 'ListItem', 'position' => $i++, 'name' => $label, 'item' => $url ?: url()->current()]; }
@endphp
<nav class="crumbs" aria-label="Breadcrumb">
  @foreach($all as $label => $url)
    @if(!$loop->first)<span>›</span>@endif
    @if($url && !$loop->last)<a href="{{ $url }}">{{ $label }}</a>@else<span>{{ $label }}</span>@endif
  @endforeach
</nav>
@push('schema')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $ld], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

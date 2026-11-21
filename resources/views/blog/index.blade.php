@extends('layouts.app')
@section('title', ($category ? "{$category->name} — " : '')."Travel Blog: Guides, Deals & Tips | {$site}")
@section('description', $category?->description ?: 'Travel guides, flight deals, visa explainers and practical tips for travellers from the UAE.')
@if($posts->currentPage() > 1) @section('noindex', '1') @endif
@section('content')
<section class="page-hero">
  <div class="wrap">
    @include('partials.crumbs', ['items' => $category ? ['Blog' => route('blog.index'), $category->name => null] : ['Blog' => null]])
    <h1>{{ $category?->name ?? 'The '.$site.' travel blog' }}</h1>
    <p class="lead">{{ $category?->description ?? 'Guides, flight deals and honest advice for travellers from the UAE.' }}</p>
  </div>
</section>
<section>
  <div class="wrap">
    <div class="tabs-links"><a href="{{ route('blog.index') }}" class="{{ $category ? '' : 'on' }}">All</a>@foreach($categories as $c)<a href="{{ route('blog.category', $c->slug) }}" class="{{ $category?->id === $c->id ? 'on' : '' }}">{{ $c->name }}</a>@endforeach</div>
    <div class="grid-3">@foreach($posts as $post) @include('partials.post-card') @endforeach</div>
    @if($posts->isEmpty())<p class="note">New articles are published daily. Check back soon.</p>@endif
    @if($posts->hasPages())
      <div class="pager">
        @if($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}">← Newer</a>@endif
        <span class="on">Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
        @if($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}">Older →</a>@endif
      </div>
    @endif
  </div>
</section>
@endsection

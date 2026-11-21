@extends('layouts.app')
@section('title', ($post->meta_title ?: $post->title)." | {$site}")
@section('description', $post->meta_description ?: $post->excerpt)
@section('canonical', $post->canonical_url ?: $post->url())
@section('og_type', 'article')
@if($post->coverUrl()) @section('og_image', $post->coverUrl()) @endif
@if($post->noindex) @section('noindex', '1') @endif
@push('schema')
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'BlogPosting','headline'=>$post->title,'description'=>$post->meta_description ?: $post->excerpt,'image'=>$post->coverUrl(),'datePublished'=>$post->published_at?->toIso8601String(),'dateModified'=>$post->updated_at->toIso8601String(),'author'=>['@type'=>'Organization','name'=>$post->author_name],'publisher'=>['@type'=>'Organization','name'=>$site,'logo'=>['@type'=>'ImageObject','url'=>asset('favicon.svg')]],'mainEntityOfPage'=>$post->url()], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
@php
  // Build a table of contents from <h2> tags and give them ids
  $toc = []; $body = preg_replace_callback('/<h2>(.*?)<\/h2>/i', function ($m) use (&$toc) { $id = \Illuminate\Support\Str::slug(strip_tags($m[1])); $toc[$id] = strip_tags($m[1]); return '<h2 id="'.$id.'">'.$m[1].'</h2>'; }, (string) $post->body);
@endphp
@section('content')
<section class="page-hero" style="padding-bottom:100px">
  <div class="wrap article-head">
    @include('partials.crumbs', ['items' => ['Blog' => route('blog.index')] + ($post->category ? [$post->category->name => route('blog.category', $post->category->slug)] : []) + [\Illuminate\Support\Str::limit($post->title, 40) => null]])
    <h1>{{ $post->title }}</h1>
    <div class="article-meta"><span>{{ $post->author_name }}</span><span>{{ $post->published_at?->format('j F Y') }}</span><span>{{ $post->readingMinutes() }} min read</span>@if($post->updated_at->gt($post->published_at?->copy()->addDay() ?? now()))<span>Updated {{ $post->updated_at->format('j M Y') }}</span>@endif</div>
  </div>
</section>
<div class="wrap"><div class="cover" @if($post->coverUrl()) style="background-image:url('{{ $post->coverUrl() }}')" @endif role="img" aria-label="{{ $post->cover_alt }}"></div></div>
<div class="wrap layout" style="padding-bottom:80px">
  <article class="prose">
    @if($post->excerpt)<p style="font-size:20px;color:var(--ink)">{{ $post->excerpt }}</p>@endif
    {!! $body !!}
    @include('partials.faqs', ['faqs' => $post->faqs ?? []])
  </article>
  <aside class="sidebar">
    @if(count($toc) > 2)<div class="box toc"><h3>In this article</h3>@foreach($toc as $id => $t)<a href="#{{ $id }}">{{ $t }}</a>@endforeach</div>@endif
    <div class="box"><h3>Find your flight</h3><p class="note">Compare hundreds of airlines and agencies.</p><a class="btn" href="{{ $post->destination?->iata ? route('go.flights',['to'=>$post->destination->iata]) : route('flights.index') }}" rel="nofollow">Search flights{{ $post->destination ? ' to '.$post->destination->city : '' }}</a></div>
    @if($post->destination)<div class="box"><h3>{{ $post->destination->city }}</h3><a class="li" href="{{ $post->destination->guideUrl() }}">Travel guide <span>→</span></a><a class="li" href="{{ $post->destination->hotelsUrl() }}">Where to stay <span>→</span></a></div>@endif
  </aside>
</div>
@if($related->count())
<section style="background:var(--sand)"><div class="wrap"><h2 style="font-size:30px;margin-bottom:20px">Keep reading</h2><div class="grid-3">@foreach($related as $post) @include('partials.post-card') @endforeach</div></div></section>
@endif
@endsection

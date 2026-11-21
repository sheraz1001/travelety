@extends('layouts.app')
@section('title', 'Page not found | '.\App\Models\Setting::get('site_name'))
@section('noindex', '1')
@section('content')
@php $site = \App\Models\Setting::get('site_name'); @endphp
<section class="page-hero"><div class="wrap"><h1>This page took a different flight</h1><p class="lead">We couldn't find that page. Try a new search instead.</p>@include('partials.search', ['show'=>['flights','hotels']])</div></section>
@endsection

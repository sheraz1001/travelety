@extends('layouts.app')
@section('title', "{$title} | {$site}")
@section('description', $description)
@section('content')
<section class="page-hero"><div class="wrap">@include('partials.crumbs', ['items' => [$title => null]])<h1>{{ $title }}</h1></div></section>
<section><div class="wrap prose" style="max-width:820px">@include('pages.'.$slug)</div></section>
@endsection

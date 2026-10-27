@extends('site.layout')
@section('title', 'My downloads')
@section('body-bg', '#F6F9F9')
@include('site.partials.listing-styles')

@section('content')
@php
    $items = $downloads->getCollection()->pluck('resource')->filter();
    $paged = new \Illuminate\Pagination\LengthAwarePaginator($items->values(), $downloads->total(), $downloads->perPage(), $downloads->currentPage(), ['path' => url()->current(), 'query' => request()->query()]);
@endphp
@include('site.partials.listing', [
    'eyebrow' => 'Your library',
    'title' => 'My downloads',
    'lead' => 'Resources you have downloaded.',
    'crumbs' => [['Resources', route('resources.index')], ['Downloads', null]],
    'resources' => $paged,
    'cards' => \App\Services\Site\SiteCards::make($items),
    'emptyTitle' => 'You have not downloaded any resources yet',
])
@endsection

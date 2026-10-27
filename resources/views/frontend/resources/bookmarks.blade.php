@extends('site.layout')
@section('title', 'My bookmarks')
@section('body-bg', '#F6F9F9')
@include('site.partials.listing-styles')

@section('content')
@include('site.partials.listing', [
    'eyebrow' => 'Your library',
    'title' => 'My bookmarks',
    'lead' => 'Resources you saved to come back to.',
    'crumbs' => [['Resources', route('resources.index')], ['Bookmarks', null]],
    'resources' => $resources,
    'cards' => \App\Services\Site\SiteCards::make($resources),
    'sort' => ['value' => request('sort', 'latest'), 'options' => ['latest' => 'Newest', 'popular' => 'Popular', 'title' => 'A–Z', 'views' => 'Most viewed']],
    'emptyTitle' => 'You have not saved any resources yet',
    'emptyText' => 'Use the Save button on a resource to keep it here.',
])
@endsection

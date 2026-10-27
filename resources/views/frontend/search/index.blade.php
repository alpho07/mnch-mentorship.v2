@extends('site.layout')
@section('title', $searchTerm !== '' ? 'Search: '.$searchTerm : 'Search')
@section('body-bg', '#F6F9F9')
@include('site.partials.listing-styles')

@section('content')
@php
    $chips = collect($suggestedCategories)->map(fn ($c) => ['label' => $c->name, 'href' => route('resources.category', $c->slug), 'count' => $c->resources_count])
        ->merge(collect($suggestedTags)->map(fn ($t) => ['label' => '#'.$t->name, 'href' => route('resources.tag', $t->slug), 'count' => $t->resources_count]))->all();
    $panels = [];
    if (collect($resourceTypes)->isNotEmpty() && $searchTerm !== '') {
        $panels[] = ['title' => 'Resource type', 'items' => collect($resourceTypes)->map(fn ($t) => [
            'label' => $t->name, 'count' => $t->resources_count, 'on' => request('type') === $t->slug,
            'href' => request()->fullUrlWithQuery(['type' => request('type') === $t->slug ? null : $t->slug, 'page' => null]),
        ])->all()];
    }
    $isPaginator = $resources instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
@endphp
@include('site.partials.listing', [
    'eyebrow' => 'Search',
    'title' => $searchTerm !== '' ? 'Results for “'.$searchTerm.'”' : 'Search the library',
    'lead' => strlen($searchTerm) > 0 && strlen($searchTerm) < 2 ? 'Type at least two characters.' : 'Manuals, protocols, slides and guidelines.',
    'crumbs' => [['Resources', route('resources.index')], ['Search', null]],
    'chips' => $chips,
    'panels' => $panels,
    'resources' => $resources,
    'cards' => $isPaginator ? \App\Services\Site\SiteCards::make($resources) : [],
    'form' => ['action' => route('resources.search'), 'name' => 'q', 'value' => $searchTerm, 'placeholder' => 'Search resources'],
    'sort' => $isPaginator ? ['value' => request('sort', 'relevance'), 'options' => ['relevance' => 'Most relevant', 'latest' => 'Newest', 'popular' => 'Popular', 'title' => 'A–Z']] : null,
    'emptyTitle' => $searchTerm !== '' ? 'No results for “'.$searchTerm.'”' : 'Start typing to search',
    'emptyText' => $searchTerm !== '' ? 'Check the spelling, try fewer words, or browse the categories.' : 'Search by title, topic or keyword.',
])
@endsection

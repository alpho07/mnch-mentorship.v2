@extends('site.layout')
@section('title', '#'.$tag->name)
@section('body-bg', '#F6F9F9')
@include('site.partials.listing-styles')

@section('content')
@include('site.partials.listing', [
    'eyebrow' => 'Tag',
    'title' => '#'.$tag->name,
    'lead' => $tag->description ?? null,
    'crumbs' => [['Resources', route('resources.index')], ['#'.$tag->name, null]],
    'chips' => collect($relatedTags)->map(fn ($t) => ['label' => '#'.$t->name, 'href' => route('resources.tag', $t->slug), 'count' => $t->resources_count ?? null])->all(),
    'resources' => $resources,
    'cards' => \App\Services\Site\SiteCards::make($resources),
    'emptyTitle' => 'Nothing tagged yet',
])
@endsection

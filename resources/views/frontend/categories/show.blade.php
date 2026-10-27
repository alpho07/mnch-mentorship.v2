@extends('site.layout')
@section('title', $category->name)
@section('description', $category->description ?: 'Resources in '.$category->name)
@section('body-bg', '#F6F9F9')
@include('site.partials.listing-styles')

@section('content')
@php
    $crumbs = [['Categories', route('categories.index')]];
    if ($category->parent) { $crumbs[] = [$category->parent->name, route('categories.show', $category->parent->slug)]; }
    $crumbs[] = [$category->name, null];
    $chips = $category->children->map(fn ($c) => ['label' => $c->name, 'href' => route('categories.show', $c->slug)])->all();
@endphp
@include('site.partials.listing', [
    'eyebrow' => $category->parent ? $category->parent->name : 'Category',
    'title' => $category->name,
    'lead' => $category->description,
    'crumbs' => $crumbs,
    'chips' => $chips,
    'resources' => $resources,
    'cards' => \App\Services\Site\SiteCards::make($resources),
    'form' => ['action' => url()->current(), 'name' => 'search', 'value' => request('search'), 'placeholder' => 'Search in '.$category->name],
    'sort' => ['value' => request('sort', 'latest'), 'options' => ['latest' => 'Newest', 'popular' => 'Popular', 'views' => 'Most viewed', 'downloads' => 'Most downloaded', 'title' => 'A–Z', 'oldest' => 'Oldest']],
    'emptyTitle' => 'Nothing here yet',
    'emptyText' => 'No resources have been added to '.$category->name.' yet.',
])
@endsection

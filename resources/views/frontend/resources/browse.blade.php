@extends('site.layout')
@section('title', 'Browse resources')
@section('body-bg', '#F6F9F9')
@include('site.partials.listing-styles')

@section('content')
@php
    // Multi-select facets (ids): clicking a facet adds/removes it from the query string.
    $toggle = function (string $key, $val) {
        $cur = array_map('strval', (array) request($key, []));
        $val = (string) $val;
        $new = in_array($val, $cur, true) ? array_values(array_diff($cur, [$val])) : array_merge($cur, [$val]);
        return request()->fullUrlWithQuery([$key => $new ?: null, 'page' => null]);
    };
    $on = fn (string $key, $val) => in_array((string) $val, array_map('strval', (array) request($key, [])), true);

    $catItems = [];
    foreach ($categories as $c) {
        $catItems[] = ['label' => $c->name, 'count' => $c->resources_count, 'on' => $on('categories', $c->id), 'href' => $toggle('categories', $c->id)];
        foreach ($c->children as $ch) {
            if ($ch->resources_count > 0) {
                $catItems[] = ['label' => '— '.$ch->name, 'count' => $ch->resources_count, 'on' => $on('categories', $ch->id), 'href' => $toggle('categories', $ch->id)];
            }
        }
    }
    $panels = [
        ['title' => 'Category', 'items' => $catItems],
        ['title' => 'Resource type', 'items' => collect($resourceTypes)->map(fn ($t) => ['label' => $t->name, 'count' => $t->resources_count, 'on' => $on('types', $t->id), 'href' => $toggle('types', $t->id)])->all()],
        ['title' => 'Level', 'items' => collect(['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'])->map(fn ($l, $k) => ['label' => $l, 'on' => $on('difficulty_levels', $k), 'href' => $toggle('difficulty_levels', $k)])->values()->all()],
    ];
    $tagItems = collect($tags ?? $popularTags ?? [])->take(20)->map(fn ($t) => ['label' => '#'.$t->name, 'count' => $t->resources_count, 'on' => $on('tags', $t->id), 'href' => $toggle('tags', $t->id)])->all();
    if ($tagItems) { $panels[] = ['title' => 'Tags', 'items' => $tagItems]; }
    $filtered = request()->hasAny(['categories', 'types', 'tags', 'difficulty_levels', 'category', 'type', 'search']);
@endphp
@include('site.partials.listing', [
    'eyebrow' => 'Library',
    'title' => 'Browse resources',
    'lead' => 'Combine categories, types, levels and tags to narrow the library.',
    'crumbs' => [['Resources', route('resources.index')], ['Browse', null]],
    'chips' => $filtered ? [['label' => 'Clear all filters ×', 'href' => route('resources.browse')]] : [],
    'panels' => $panels,
    'resources' => $resources,
    'cards' => \App\Services\Site\SiteCards::make($resources),
    'sort' => ['value' => request('sort', 'latest'), 'options' => ['latest' => 'Newest', 'popular' => 'Popular', 'views' => 'Most viewed', 'downloads' => 'Most downloaded', 'title' => 'A–Z', 'oldest' => 'Oldest']],
    'emptyTitle' => 'No resources match these filters',
    'emptyText' => 'Remove a filter or clear them all.',
])
@endsection

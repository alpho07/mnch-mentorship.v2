@extends('site.layout')
@section('title', $type->name.' resources')
@section('body-bg', '#F6F9F9')
@include('site.partials.listing-styles')

@section('content')
@include('site.partials.listing', [
    'eyebrow' => 'Resource type',
    'title' => $type->name,
    'lead' => $type->description,
    'crumbs' => [['Resources', route('resources.index')], [$type->name, null]],
    'resources' => $resources,
    'cards' => \App\Services\Site\SiteCards::make($resources),
    'emptyTitle' => 'Nothing here yet',
    'emptyText' => 'No '.strtolower($type->name).' resources are available yet.',
])
@endsection

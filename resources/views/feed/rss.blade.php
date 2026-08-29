<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<rss version="2.0">
    <channel>
        <title>{{ config('app.name') }} — Resource Library</title>
        <link>{{ route('resources.index') }}</link>
        <description>Latest published resources from {{ config('app.name') }}.</description>
        <language>en</language>
        <lastBuildDate>{{ now()->toRfc2822String() }}</lastBuildDate>
@foreach($resources as $resource)
        <item>
            <title>{{ $resource->title }}</title>
            <link>{{ route('resources.show', $resource->slug) }}</link>
            <guid isPermaLink="true">{{ route('resources.show', $resource->slug) }}</guid>
            <pubDate>{{ ($resource->published_at ?? $resource->updated_at)?->toRfc2822String() ?? now()->toRfc2822String() }}</pubDate>
@if($resource->category)
            <category>{{ $resource->category->name }}</category>
@endif
            <description>{{ \Illuminate\Support\Str::limit(strip_tags((string) $resource->description), 300) }}</description>
        </item>
@endforeach
    </channel>
</rss>

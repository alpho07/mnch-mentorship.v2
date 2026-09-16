@props(['resource'])

<div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden group border border-gray-200 hover:border-primary-300">
    <!-- Resource Image/Icon -->
    <div class="relative h-32 bg-gradient-to-br from-primary-50 to-primary-100 overflow-hidden">
        @if($resource->featured_image)
            <img src="{{ Storage::disk('thumbnails')->url($resource->featured_image) }}"
                 alt="{{ $resource->title }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
        @else
            <x-resource-placeholder :resource="$resource" icon-size="text-2xl" :show-label="false" />
        @endif

        <!-- Type Badge -->
        @if($resource->resourceType)
            <div class="absolute top-2 left-2">
                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-white bg-opacity-90 text-gray-800">
                    {{ $resource->resourceType->name }}
                </span>
            </div>
        @endif
    </div>

    <!-- Card Content -->
    <div class="p-4">
        <!-- Category -->
        @if($resource->category)
            <div class="mb-2">
                <a href="{{ route('resources.category', $resource->category->slug) }}"
                   class="text-xs text-primary-600 hover:text-primary-700 font-medium">
                    {{ $resource->category->name }}
                </a>
            </div>
        @endif

        <!-- Title -->
        <h3 class="text-sm font-semibold text-gray-900 mb-2 line-clamp-2 group-hover:text-primary-600 transition-colors">
            <a href="{{ route('resources.show', $resource->slug) }}">
                {{ $resource->title }}
            </a>
        </h3>

        <!-- Meta -->
        <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
            @if($resource->author)
                <span>{{ $resource->author->full_name }}</span>
            @else
                <span>Unknown Author</span>
            @endif
            @if($resource->published_at)
                <time datetime="{{ $resource->published_at->toISOString() }}">
                    {{ $resource->published_at->diffForHumans() }}
                </time>
            @endif
        </div>

        <!-- Stats -->
        <div class="flex items-center justify-between text-xs text-gray-500">
            <div class="flex items-center space-x-3">
                <span class="flex items-center">
                    <i class="fas fa-eye mr-1"></i>
                    {{ number_format($resource->view_count) }}
                </span>

                @if($resource->is_downloadable)
                    <span class="flex items-center">
                        <i class="fas fa-download mr-1"></i>
                        {{ number_format($resource->download_count) }}
                    </span>
                @endif
            </div>

            <!-- Action Button -->
            <a href="{{ route('resources.show', $resource->slug) }}"
               class="text-primary-600 hover:text-primary-700 font-medium">
                View <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
</div>

@once
@push('styles')
<style>
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>
@endpush
@endonce

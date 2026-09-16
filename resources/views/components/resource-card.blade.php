@props(['resource', 'featured' => false])

<div
    class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden group border border-gray-200 hover:border-primary-300">
    <!-- Featured Badge -->
    @if ($featured)
        <div class="absolute top-4 left-4 z-10">
            <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                <i class="fas fa-star mr-1"></i> Featured
            </span>
        </div>
    @endif

    <!-- Resource Image/Thumbnail -->
    <div class="relative h-48 bg-gradient-to-br from-primary-50 to-primary-100 overflow-hidden">
        @if ($resource->featured_image)
            <img src="{{ Storage::disk('thumbnails')->url($resource->featured_image) }}"
                 alt="{{ $resource->title }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
        @else
            <x-resource-placeholder :resource="$resource" icon-size="text-4xl" />
        @endif

        <!-- Quick Actions Overlay -->
        <div
            class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-300 flex items-center justify-center opacity-0 group-hover:opacity-100">
            <div class="flex space-x-3">
                <a href="{{ route('resources.show', $resource->slug) }}"
                    class="bg-white text-gray-900 p-2 rounded-full hover:bg-gray-100 transition-colors">
                    <i class="fas fa-eye"></i>
                </a>

                @if ($resource->is_downloadable && ($resource->file_path || $resource->hasPrimaryFile()))
                    <a href="{{ route('resources.download', $resource->slug) }}"
                        class="bg-primary-600 text-white p-2 rounded-full hover:bg-primary-700 transition-colors">
                        <i class="fas fa-download"></i>
                    </a>
                @endif

                @auth
                    <button onclick="toggleBookmark({{ $resource->id }})"
                        class="bg-yellow-500 text-white p-2 rounded-full hover:bg-yellow-600 transition-colors">
                        <i class="fas fa-bookmark"></i>
                    </button>
                @endauth
            </div>
        </div>
    </div>

    <!-- Card Content -->
    <div class="p-6">
        <!-- Category & Type -->
        <div class="flex items-center justify-between mb-3">
            @if ($resource->category)
                <a href="{{ route('resources.category', $resource->category->slug) }}"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 hover:bg-gray-200 transition-colors">
                    {{ $resource->category->name }}
                </a>
            @else
                <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    Uncategorized
                </span>
            @endif

            @if ($resource->difficulty_level)
                <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    @if ($resource->difficulty_level === 'beginner') bg-green-100 text-green-800
                    @elseif($resource->difficulty_level === 'intermediate') bg-yellow-100 text-yellow-800
                    @else bg-red-100 text-red-800 @endif">
                    {{ ucfirst($resource->difficulty_level) }}
                </span>
            @endif
        </div>

        <!-- Title -->
        <h3
            class="text-lg font-semibold text-gray-900 mb-2 group-hover:text-primary-600 transition-colors line-clamp-2">
            <a href="{{ route('resources.show', $resource->slug) }}">
                {{ $resource->title }}
            </a>
        </h3>

        <!-- Excerpt -->
        @if ($resource->excerpt)
            <p class="text-gray-600 text-sm mb-4 line-clamp-3">
                {{ $resource->excerpt }}
            </p>
        @endif

        <!-- Tags -->
        @if ($resource->tags && $resource->tags->count() > 0)
            <div class="flex flex-wrap gap-1 mb-4">
                @foreach ($resource->tags->take(3) as $tag)
                    <a href="{{ route('resources.tag', $tag->slug) }}"
                        class="inline-flex items-center px-2 py-1 rounded text-xs bg-gray-100 text-gray-700 hover:bg-primary-100 hover:text-primary-700 transition-colors">
                        #{{ $tag->name }}
                    </a>
                @endforeach
                @if ($resource->tags->count() > 3)
                    <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-gray-100 text-gray-700">
                        +{{ $resource->tags->count() - 3 }}
                    </span>
                @endif
            </div>
        @endif

        <!-- Meta Information -->
        <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
            <div class="flex items-center">
                @if ($resource->author)
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($resource->author->full_name) }}&size=24&background=3b82f6&color=ffffff"
                        alt="{{ $resource->author->full_name }}" class="w-6 h-6 rounded-full mr-2">
                    <span>{{ $resource->author->full_name }}</span>
                @else
                    <span>Unknown Author</span>
                @endif
            </div>
            @if ($resource->published_at)
                <time datetime="{{ $resource->published_at->toISOString() }}">
                    {{ $resource->published_at->diffForHumans() }}
                </time>
            @endif
        </div>

        <!-- Stats -->
        <div class="flex items-center justify-between text-sm text-gray-500 pt-4 border-t border-gray-100">
            <div class="flex items-center space-x-4">
                <span class="flex items-center">
                    <i class="fas fa-eye mr-1"></i>
                    {{ number_format($resource->view_count) }}
                </span>

                @if ($resource->is_downloadable)
                    <span class="flex items-center">
                        <i class="fas fa-download mr-1"></i>
                        {{ number_format($resource->download_count) }}
                    </span>
                @endif

                <span class="flex items-center">
                    <i class="fas fa-heart mr-1"></i>
                    {{ number_format($resource->like_count) }}
                </span>

                @if (isset($resource->comments_count) && $resource->comments_count > 0)
                    <span class="flex items-center">
                        <i class="fas fa-comment mr-1"></i>
                        {{ number_format($resource->comments_count) }}
                    </span>
                @endif
            </div>

            <!-- Read Time -->
            @if ($resource->read_time && $resource->read_time > 0)
                <span class="flex items-center text-xs">
                    <i class="fas fa-clock mr-1"></i>
                    {{ $resource->read_time }} min read
                </span>
            @endif
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center space-x-3 mt-4">
            <a href="{{ route('resources.show', $resource->slug) }}"
                class="flex-1 bg-primary-600 text-white text-center py-2 px-4 rounded-lg hover:bg-primary-700 transition-colors font-medium">
                <i class="fas fa-eye mr-2"></i>View Resource
            </a>

            @if ($resource->is_downloadable && $resource->file_path)
                <a href="{{ route('resources.download', $resource->slug) }}"
                    class="bg-gray-100 text-gray-700 p-2 rounded-lg hover:bg-gray-200 transition-colors"
                    title="Download">
                    <i class="fas fa-download"></i>
                </a>
            @endif
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function toggleBookmark(resourceId) {
                @auth
                fetch(`/api/v1/resources/${resourceId}/interactions/bookmark`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.bookmarked) {
                            showToast('Resource bookmarked!', 'success');
                        } else {
                            showToast('Bookmark removed!', 'info');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('An error occurred', 'error');
                    });
            @else
                window.location.href = '{{ url('admin/login') }}';
            @endauth
            }

            function showToast(message, type = 'info') {
                // Simple toast notification
                const toast = document.createElement('div');
                toast.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 ${
            type === 'success' ? 'bg-green-500' :
            type === 'error' ? 'bg-red-500' : 'bg-blue-500'
        }`;
                toast.textContent = message;
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.remove();
                }, 3000);
            }
        </script>
    @endpush

    @push('styles')
        <style>
            .line-clamp-2 {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .line-clamp-3 {
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
        </style>
    @endpush
@endonce

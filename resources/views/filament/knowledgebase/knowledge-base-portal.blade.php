<x-filament::page>
    <div class="mb-4">
        <h1 class="text-3xl font-bold mb-2 text-gray-900 dark:text-gray-100">Knowledge Base</h1>
        <form method="GET" class="flex flex-wrap gap-2 mb-4 w-full items-center">
            <input
                type="text"
                name="search"
                placeholder="Search articles, SOPs, videos..."
                value="{{ request('search', '') }}"
                class="w-64 border rounded p-2 bg-white text-gray-900 border-gray-300 dark:bg-gray-900 dark:text-gray-100 dark:border-gray-700"
            />

            <select name="program" class="w-48 border rounded p-2 bg-white text-gray-900 border-gray-300 dark:bg-gray-900 dark:text-gray-100 dark:border-gray-700">
                <option value="">All Programs</option>
                @foreach($this->programs as $prog)
                    <option value="{{ $prog->id }}" @selected(request('program') == $prog->id)>
                        {{ $prog->name }}
                    </option>
                @endforeach
            </select>

            <select name="category" class="w-48 border rounded p-2 bg-white text-gray-900 border-gray-300 dark:bg-gray-900 dark:text-gray-100 dark:border-gray-700">
                <option value="">All Categories</option>
                @foreach($this->categories as $cat)
                    <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>

            <select name="tag" class="w-48 border rounded p-2 bg-white text-gray-900 border-gray-300 dark:bg-gray-900 dark:text-gray-100 dark:border-gray-700">
                <option value="">All Tags</option>
                @foreach($this->tags as $tag)
                    <option value="{{ $tag->id }}" @selected(request('tag') == $tag->id)>
                        {{ $tag->name }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="bg-primary-600 text-white px-4 py-2 rounded hover:bg-primary-700 transition">Filter</button>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
        @forelse($this->articles as $article)
            <div class="bg-white dark:bg-gray-900 shadow rounded-xl p-4 flex flex-col h-full">
                <div>
                    <h2 class="font-bold text-lg mb-1 text-gray-800 dark:text-gray-100">{{ $article->title }}</h2>
                    <div class="flex flex-wrap gap-1 text-xs mb-2">
                        @foreach($article->programs as $program)
                            <span class="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-2 py-1 rounded">{{ $program->name }}</span>
                        @endforeach
                        @if($article->category)
                            <span class="bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-200 px-2 py-1 rounded">
                                {{ $article->category->name }}
                            </span>
                        @endif
                        @foreach($article->tags as $tag)
                            @php
                                $color = $tag->color ?? '#f3f4f6';
                                // Lighten tag color if it's too dark for dark mode
                                $textColor = '#222';
                            @endphp
                            <span
                                class="px-2 py-1 rounded"
                                style="background: {{ $color }}; color: {{ $textColor }};">
                                {{ $tag->name }}
                            </span>
                        @endforeach
                    </div>
                </div>
                <div class="prose dark:prose-invert line-clamp-4 mb-2 text-gray-700 dark:text-gray-200">
                    {!! \Illuminate\Support\Str::words(strip_tags($article->content), 24, '...') !!}
                </div>
                @if($article->attachments->count())
                    @php $att = $article->attachments->first(); @endphp
                    <div class="mb-2">
                        @if($att->type === 'pdf' && $att->file_path)
                            <iframe src="{{ asset('storage/' . $att->file_path) }}" width="100%" height="150" class="rounded"></iframe>
                        @elseif($att->type === 'image' && $att->file_path)
                            <img src="{{ asset('storage/' . $att->file_path) }}" class="rounded w-full h-32 object-cover" />
                        @elseif($att->type === 'video')
                            @if($att->file_path)
                                <video controls class="w-full h-32 object-cover rounded">
                                    <source src="{{ asset('storage/' . $att->file_path) }}" type="video/mp4" />
                                </video>
                            @elseif($att->external_url)
                                <iframe width="100%" height="150" src="{{ $att->external_url }}" frameborder="0" allowfullscreen class="rounded"></iframe>
                            @endif
                        @elseif($att->type === 'link' && $att->external_url)
                            <a href="{{ $att->external_url }}" target="_blank" class="text-blue-700 underline dark:text-blue-300">{{ $att->external_url }}</a>
                        @endif
                    </div>
                @endif
                <a href="{{ url('filament.knowledgebase.knowledge-base-article-detail', $article) }}" class="mt-auto text-primary-600 dark:text-primary-400 font-bold hover:underline">View Details</a>
            </div>
        @empty
            <div class="col-span-3">
                <div class="p-4 text-center text-gray-500 dark:text-gray-400">No articles found matching your search/filters.</div>
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $this->articles->withQueryString()->links() }}
    </div>
</x-filament::page>

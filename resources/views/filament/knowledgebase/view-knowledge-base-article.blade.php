<x-filament::page>
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">{{ $article->title }}</h1>
        <div class="flex flex-wrap gap-2 text-xs mb-4">
            @foreach($article->programs as $program)
                <span class="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-2 py-1 rounded">{{ $program->name }}</span>
            @endforeach
            @if($article->category)
                <span class="bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-200 px-2 py-1 rounded">{{ $article->category->name }}</span>
            @endif
            @foreach($article->tags as $tag)
                @php $color = $tag->color ?? '#f3f4f6'; @endphp
                <span class="px-2 py-1 rounded" style="background: {{ $color }}; color: #222;">
                    {{ $tag->name }}
                </span>
            @endforeach
        </div>
    </div>
    <div class="prose dark:prose-invert max-w-none mb-8 text-gray-800 dark:text-gray-100">
        {!! $article->content !!}
    </div>
    @if($article->attachments->count())
        <div>
            <h3 class="font-bold mb-2 text-gray-800 dark:text-gray-100">Attachments & Resources</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($article->attachments as $att)
                    <div class="rounded shadow bg-white dark:bg-gray-900 p-3">
                        <div class="font-semibold text-gray-800 dark:text-gray-100">{{ $att->display_name }}</div>
                        @if($att->type === 'pdf' && $att->file_path)
                            <iframe src="{{ asset('storage/' . $att->file_path) }}" width="100%" height="400px"></iframe>
                        @elseif($att->type === 'image' && $att->file_path)
                            <img src="{{ asset('storage/' . $att->file_path) }}" alt="{{ $att->display_name }}" class="rounded w-full" />
                        @elseif($att->type === 'video')
                            @if($att->file_path)
                                <video controls class="w-full mt-2 rounded">
                                    <source src="{{ asset('storage/' . $att->file_path) }}" type="video/mp4">
                                </video>
                            @elseif($att->external_url)
                                <iframe width="100%" height="315" src="{{ $att->external_url }}" frameborder="0" allowfullscreen></iframe>
                            @endif
                        @elseif($att->type === 'link' && $att->external_url)
                            <a href="{{ $att->external_url }}" class="text-blue-600 hover:underline dark:text-blue-300" target="_blank">
                                {{ $att->external_url }}
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    <div class="mt-8">
        <a href="{{ url()->previous() }}" class="text-primary-600 dark:text-primary-400 font-bold hover:underline">&larr; Back to Knowledge Base</a>
    </div>
</x-filament::page>

<x-filament-panels::page>
    @if ($isEmonc)
    @php
        $sections = $this->getResourceSections();
    @endphp

    <div x-data="{ open: 'introduction' }" class="space-y-4">
        @foreach ($sections as $key => $section)
            @php
                $isOpen = $key === 'introduction';
                $items = $section['items'];
                $count = $section['count'];
            @endphp

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <button type="button"
                        class="w-full flex items-center justify-between p-4 text-left bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                        @click="open = open === '{{ $key }}' ? null : '{{ $key }}'">
                    <div class="flex items-center gap-3">
                        <x-dynamic-component :component="$section['icon']" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $section['title'] }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $count }} item(s)</p>
                        </div>
                    </div>
                    <x-heroicon-o-chevron-down class="w-5 h-5 text-gray-500 transition-transform"
                                               x-bind:class="open === '{{ $key }}' ? 'rotate-180' : ''" />
                </button>

                <div x-show="open === '{{ $key }}'" x-collapse class="p-4 space-y-4">
                    @if ($key === 'introduction')
                        @if ($this->programModule->description)
                            <div class="prose dark:prose-invert max-w-none">
                                {!! Str::markdown($this->programModule->description) !!}
                            </div>
                        @endif

                        @forelse ($items as $item)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-2">{{ $item->title }}</h4>
                                <div class="prose dark:prose-invert max-w-none">
                                    {!! Str::markdown($item->content) !!}
                                </div>
                            </div>
                        @empty
                            @if (! $this->programModule->description)
                                <p class="text-gray-500">No introduction content available.</p>
                            @endif
                        @endforelse
                    @endif

                    @if (in_array($key, ['pre_test', 'post_test']))
                        @forelse ($items as $quiz)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="font-semibold text-gray-900 dark:text-white">{{ $quiz->title }}</h4>
                                        @if ($quiz->description)
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $quiz->description }}</p>
                                        @endif
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-300">
                                        {{ $quiz->questions->count() }} question(s)
                                    </span>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-2 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="inline-flex items-center gap-1">
                                        <x-heroicon-o-check-circle class="w-4 h-4" />
                                        Pass mark: {{ $quiz->pass_mark_percentage }}%
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <x-heroicon-o-question-mark-circle class="w-4 h-4" />
                                        Type: {{ str_replace('_', ' ', $quiz->type) }}
                                    </span>
                                </div>

                                <div class="mt-4 space-y-3">
                                    @forelse ($quiz->questions->sortBy('order_sequence') as $question)
                                        <div class="border border-gray-100 dark:border-gray-700 rounded-lg p-3 bg-gray-50 dark:bg-gray-900/40">
                                            <p class="font-medium text-gray-900 dark:text-white text-sm">
                                                {{ $loop->iteration }}. {{ $question->question_text }}
                                            </p>
                                            <ul class="mt-2 space-y-1">
                                                @foreach ($question->options->sortBy('order_sequence') as $option)
                                                    <li class="flex items-center gap-2 text-sm {{ $option->is_correct ? 'text-success-700 dark:text-success-400 font-semibold' : 'text-gray-600 dark:text-gray-400' }}">
                                                        @if ($option->is_correct)
                                                            <x-heroicon-o-check-circle class="w-4 h-4 shrink-0 text-success-600 dark:text-success-400" />
                                                        @else
                                                            <span class="w-4 h-4 shrink-0"></span>
                                                        @endif
                                                        {{ $option->option_text }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                            @if ($question->explanation)
                                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 italic">{{ $question->explanation }}</p>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-gray-500 text-sm">No questions configured for this quiz yet.</p>
                                    @endforelse
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500">No {{ strtolower($section['title']) }} quizzes configured.</p>
                        @endforelse
                    @endif

                    @if ($key === 'learning_objectives')
                        @forelse ($items as $objective)
                            <div class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <x-heroicon-o-check-circle class="w-4 h-4 shrink-0 mt-0.5 text-primary-500" />
                                <span>{{ $objective }}</span>
                            </div>
                        @empty
                            <p class="text-gray-500">No learning objectives configured.</p>
                        @endforelse
                    @endif

                    @if ($key === 'video')
                        @forelse ($items as $video)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-3">{{ $video->title }}</h4>
                                @php
                                    $embedUrl = $video->youtubeEmbedUrl();
                                    $isDirectVideo = $video->video_url && preg_match('/\.(mp4|webm|ogg|mov|m4v)(\?.*)?$/i', $video->video_url);
                                @endphp
                                @if ($embedUrl)
                                    <div class="rounded-lg overflow-hidden" style="width:100%;max-height:400px;aspect-ratio:16/9;margin:0 auto;background:#000;">
                                        <iframe src="{{ $embedUrl }}"
                                                style="width:100%;height:100%;"
                                                frameborder="0"
                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                allowfullscreen></iframe>
                                    </div>
                                @elseif ($isDirectVideo)
                                    <div class="rounded-lg overflow-hidden" style="width:100%;max-height:400px;aspect-ratio:16/9;margin:0 auto;background:#000;">
                                        <video src="{{ $video->video_url }}" controls style="width:100%;height:100%;object-fit:contain;"></video>
                                    </div>
                                @elseif ($video->video_url)
                                    {{-- Not YouTube, not a direct video file (e.g. a Google Drive share
                                         link) — <video src> can't play this, so link out instead. --}}
                                    <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-900/40 border border-gray-200 dark:border-gray-700">
                                        <div class="w-10 h-10 rounded-lg bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center shrink-0">
                                            <x-heroicon-o-link class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ $video->video_url }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">External video link</p>
                                        </div>
                                        <a href="{{ $video->video_url }}" target="_blank" rel="noopener noreferrer"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold transition-colors shrink-0">
                                            Open
                                        </a>
                                    </div>
                                @endif
                                @if ($video->content)
                                    <div class="prose dark:prose-invert max-w-none mt-3">
                                        {!! Str::markdown($video->content) !!}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-gray-500">No learning videos available.</p>
                        @endforelse
                    @endif

                    @if ($key === 'case_scenario')
                        @forelse ($items as $scenario)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <h4 class="font-semibold text-gray-900 dark:text-white mb-2">{{ $scenario->title }}</h4>
                                <div class="prose dark:prose-invert max-w-none">
                                    {!! Str::markdown($scenario->content) !!}
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500">No case scenarios available.</p>
                        @endforelse
                    @endif

                    @if ($key === 'equipment')
                        @if (count($items) > 0)
                            <div class="flex flex-wrap gap-2">
                                @foreach ($items as $item)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-xs font-medium text-gray-600 dark:text-gray-300">{{ $item }}</span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500">No equipment or materials listed for this module.</p>
                        @endif
                    @endif

                    @if ($key === 'debrief')
                        @if (count($items) > 0)
                            <ol class="space-y-2 list-decimal list-inside">
                                @foreach ($items as $question)
                                    <li class="text-sm text-gray-700 dark:text-gray-300">{{ $question }}</li>
                                @endforeach
                            </ol>
                        @else
                            <p class="text-gray-500">No debrief questions configured for this module.</p>
                        @endif
                    @endif

                    @if ($key === 'resources')
                        @forelse ($items as $resource)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="font-semibold text-gray-900 dark:text-white">{{ $resource->title }}</h4>
                                        @if ($resource->excerpt)
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ Str::limit($resource->excerpt, 160) }}</p>
                                        @endif
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @if ($resource->category)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-info-100 text-info-800 dark:bg-info-900 dark:text-info-300">
                                                    {{ $resource->category->name }}
                                                </span>
                                            @endif
                                            @if ($resource->resourceType)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-300">
                                                    {{ $resource->resourceType->name }}
                                                </span>
                                            @endif
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                {{ ucfirst($resource->status) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1 shrink-0">
                                        @if ($resource->primaryFile?->exists())
                                            <a href="{{ route('admin.resource-files.download', $resource->primaryFile) }}"
                                               class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-success-100 text-success-600 hover:bg-success-200 dark:bg-success-900 dark:text-success-300"
                                               title="Download">
                                                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                            </a>
                                        @endif
                                        @if (filled($resource->external_url))
                                            <a href="{{ $resource->external_url }}"
                                               target="_blank"
                                               rel="noopener"
                                               class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-warning-100 text-warning-600 hover:bg-warning-200 dark:bg-warning-900 dark:text-warning-300"
                                               title="Open external link">
                                                <x-heroicon-o-link class="w-4 h-4" />
                                            </a>
                                        @endif
                                        <a href="{{ App\Filament\Resources\ResourceResource::getUrl('view', ['record' => $resource]) }}"
                                           class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300"
                                           title="View resource">
                                            <x-heroicon-o-eye class="w-4 h-4" />
                                        </a>
                                        @if ($this->isAdmin())
                                            <button type="button"
                                                    wire:click="detachResource({{ $resource->id }})"
                                                    wire:confirm="Remove '{{ $resource->title }}' from this module?"
                                                    class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-danger-100 text-danger-600 hover:bg-danger-200 dark:bg-danger-900 dark:text-danger-300"
                                                    title="Remove from module">
                                                <x-heroicon-o-trash class="w-4 h-4" />
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500">No resources attached to this module.</p>
                        @endforelse
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @else
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        @if ($this->programModule->resources->isEmpty())
            <p class="text-gray-500 p-4">No resources attached to this module.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                        <th class="py-3 px-4 font-medium">Resource</th>
                        <th class="py-3 px-4 font-medium">Links</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($this->programModule->resources as $resource)
                        <tr>
                            <td class="py-3 px-4">
                                <p class="font-medium text-gray-900 dark:text-white">{{ $resource->title }}</p>
                                @if ($resource->excerpt)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ Str::limit($resource->excerpt, 120) }}</p>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    @if ($resource->primaryFile?->exists())
                                        <a href="{{ route('admin.resource-files.download', $resource->primaryFile) }}"
                                           class="inline-flex items-center gap-1 text-success-600 dark:text-success-400 hover:underline">
                                            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                            Download
                                        </a>
                                    @endif
                                    @if (filled($resource->external_url))
                                        <a href="{{ $resource->external_url }}"
                                           target="_blank"
                                           rel="noopener"
                                           class="inline-flex items-center gap-1 text-warning-600 dark:text-warning-400 hover:underline">
                                            <x-heroicon-o-link class="w-4 h-4" />
                                            Open link
                                        </a>
                                    @endif
                                    <a href="{{ App\Filament\Resources\ResourceResource::getUrl('view', ['record' => $resource]) }}"
                                       class="inline-flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:underline">
                                        <x-heroicon-o-eye class="w-4 h-4" />
                                        View
                                    </a>
                                    @if ($this->isAdmin())
                                        <button type="button"
                                                wire:click="detachResource({{ $resource->id }})"
                                                wire:confirm="Remove '{{ $resource->title }}' from this module?"
                                                class="inline-flex items-center gap-1 text-danger-600 dark:text-danger-400 hover:underline">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                            Remove
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @endif
</x-filament-panels::page>

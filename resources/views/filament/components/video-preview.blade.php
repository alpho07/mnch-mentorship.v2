@if($embedUrl)
    <div class="w-full rounded-lg overflow-hidden bg-black" style="height: 480px">
        <iframe src="{{ $embedUrl }}" class="w-full" style="height: 480px" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>
@elseif($isDirectVideo)
    <div class="w-full rounded-lg overflow-hidden bg-black" style="height: 480px">
        <video controls class="w-full h-full">
            <source src="{{ $videoUrl }}">
            <p class="text-sm text-white p-4">Your browser cannot play this video. <a href="{{ $videoUrl }}" target="_blank" class="underline">Open video</a></p>
        </video>
    </div>
@elseif($videoUrl)
    <div class="flex items-center gap-3 p-3 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
        <div class="w-10 h-10 rounded-lg bg-brand-100 dark:bg-brand-900/30 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-brand-600 dark:text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate">{{ $videoUrl }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">External video link</p>
        </div>
        <a href="{{ $videoUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold transition-colors shrink-0">
            Open
        </a>
    </div>
@else
    <div class="text-sm text-gray-500">No video available.</div>
@endif

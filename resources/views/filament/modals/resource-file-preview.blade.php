@php
    use App\Http\Controllers\Admin\ResourceFileController;

    $type     = $file->file_type ?? '';
    $isPdf    = $type === 'application/pdf';
    $isImage  = str_starts_with($type, 'image/');
    $isVideo  = str_starts_with($type, 'video/');
    $isAudio  = str_starts_with($type, 'audio/');
    $isText   = in_array($type, ['text/plain', 'text/csv']);
    $isOffice = in_array($type, [
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);

    $previewUrl  = route('admin.resource-files.preview', $file);
    $downloadUrl = route('admin.resource-files.download', $file);

    // Signed public URL for external viewers (expires in 5 min)
    $tempUrl = $isOffice ? ResourceFileController::signedTempUrl($file) : null;
    $officeViewerUrl = $tempUrl
        ? 'https://view.officeapps.live.com/op/embed.aspx?src=' . urlencode($tempUrl)
        : null;
@endphp

<div class="p-1">
    {{-- File info bar --}}
    <div class="flex items-center justify-between mb-3 px-1">
        <div class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">
            {{ $file->original_name }}
            @if($file->file_size)
                <span class="ml-2 text-xs">({{ $file->formatted_file_size }})</span>
            @endif
        </div>
        <a href="{{ $downloadUrl }}"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
            <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
            Download
        </a>
    </div>

    @if($isPdf)
        <iframe src="{{ $previewUrl }}"
                class="w-full rounded border border-gray-200 dark:border-gray-700"
                style="height: 75vh;"
                title="{{ $file->original_name }}">
        </iframe>

    @elseif($isImage)
        <div class="flex items-center justify-center bg-gray-50 dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700 p-4" style="min-height: 40vh;">
            <img src="{{ $previewUrl }}"
                 alt="{{ $file->original_name }}"
                 class="max-w-full max-h-[70vh] object-contain rounded shadow">
        </div>

    @elseif($isVideo)
        <div class="bg-black rounded overflow-hidden">
            <video controls class="w-full" style="max-height: 65vh;">
                <source src="{{ $previewUrl }}" type="{{ $type }}">
                Your browser does not support video playback.
            </video>
        </div>

    @elseif($isAudio)
        <div class="flex items-center justify-center py-10 bg-gray-50 dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700">
            <audio controls class="w-full max-w-lg">
                <source src="{{ $previewUrl }}" type="{{ $type }}">
                Your browser does not support audio playback.
            </audio>
        </div>

    @elseif($isText)
        <iframe src="{{ $previewUrl }}"
                class="w-full rounded border border-gray-200 dark:border-gray-700 font-mono text-sm"
                style="height: 65vh;"
                title="{{ $file->original_name }}">
        </iframe>

    @elseif($isOffice && $officeViewerUrl)
        {{-- Microsoft Office Online embedded viewer --}}
        <div class="relative rounded overflow-hidden border border-gray-200 dark:border-gray-700" style="height: 75vh;">
            <iframe src="{{ $officeViewerUrl }}"
                    class="w-full h-full"
                    frameborder="0"
                    title="{{ $file->original_name }}"
                    allowfullscreen>
            </iframe>
        </div>
        <p class="text-xs text-gray-400 dark:text-gray-500 text-center mt-2">
            Powered by Microsoft Office Online &mdash; requires internet access
        </p>

    @else
        <div class="flex flex-col items-center justify-center py-16 bg-gray-50 dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700 gap-4">
            <x-heroicon-o-document class="w-16 h-16 text-gray-300 dark:text-gray-600" />
            <div class="text-center">
                <p class="font-medium text-gray-700 dark:text-gray-300">{{ $file->original_name }}</p>
                <p class="text-sm text-gray-500 mt-1">This file type cannot be previewed in the browser.</p>
            </div>
            <a href="{{ $downloadUrl }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg hover:bg-primary-700 transition-colors">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                Download to Open
            </a>
        </div>
    @endif
</div>

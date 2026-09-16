@props(['resource', 'iconSize' => 'text-4xl', 'showLabel' => true])

@php
    $typeName = $resource->resourceType?->name ?? '';
    $icon     = $resource->resourceType?->icon ?? 'fas fa-file-alt';
    $lower    = strtolower($typeName . ' ' . $icon);

    $scheme = match(true) {
        str_contains($lower, 'pdf')                          => ['from-red-50',    'to-rose-100',    'text-red-500',    'bg-red-100'],
        str_contains($lower, 'present') ||
        str_contains($lower, 'pptx') ||
        str_contains($lower, 'powerpoint') ||
        str_contains($lower, 'slide')                        => ['from-orange-50', 'to-amber-100',   'text-orange-500', 'bg-orange-100'],
        str_contains($lower, 'video') ||
        str_contains($lower, 'film') ||
        str_contains($lower, 'camera')                       => ['from-purple-50', 'to-violet-100',  'text-purple-500', 'bg-purple-100'],
        str_contains($lower, 'audio') ||
        str_contains($lower, 'music') ||
        str_contains($lower, 'sound')                        => ['from-blue-50',   'to-indigo-100',  'text-blue-500',   'bg-blue-100'],
        str_contains($lower, 'excel') ||
        str_contains($lower, 'sheet') ||
        str_contains($lower, 'spreadsheet') ||
        str_contains($lower, 'table-cells')                  => ['from-green-50',  'to-emerald-100', 'text-green-600',  'bg-green-100'],
        str_contains($lower, 'word') ||
        str_contains($lower, 'doc') ||
        str_contains($lower, 'document')                     => ['from-sky-50',    'to-blue-100',    'text-sky-600',    'bg-sky-100'],
        str_contains($lower, 'image') ||
        str_contains($lower, 'photo') ||
        str_contains($lower, 'picture')                      => ['from-pink-50',   'to-rose-100',    'text-pink-500',   'bg-pink-100'],
        str_contains($lower, 'manual') ||
        str_contains($lower, 'guide') ||
        str_contains($lower, 'book')                         => ['from-teal-50',   'to-cyan-100',    'text-teal-600',   'bg-teal-100'],
        default                                              => ['from-primary-50', 'to-cyan-100',    'text-primary-600','bg-primary-100'],
    };

    [$gradFrom, $gradTo, $iconColor, $badgeBg] = $scheme;
@endphp

<div class="w-full h-full flex flex-col items-center justify-center relative overflow-hidden
            bg-gradient-to-br {{ $gradFrom }} {{ $gradTo }}">

    {{-- decorative blobs --}}
    <div class="absolute -top-5 -right-5 w-24 h-24 rounded-full bg-white opacity-20"></div>
    <div class="absolute -bottom-8 -left-8 w-32 h-32 rounded-full bg-white opacity-10"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2
                w-40 h-40 rounded-full bg-white opacity-5"></div>

    {{-- icon --}}
    <div class="relative z-10 flex flex-col items-center gap-1.5">
        <i class="{{ $icon }} {{ $iconSize }} {{ $iconColor }} drop-shadow-sm"></i>

        @if($showLabel && $typeName)
            <span class="text-xs font-semibold uppercase tracking-wider {{ $iconColor }} opacity-80">
                {{ $typeName }}
            </span>
        @endif
    </div>
</div>

{{-- Simple line icons for the error pages. $name: compass | lock | clock | tools | plug | file | alert --}}
@php $s = 'fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"'; @endphp
<svg viewBox="0 0 64 64" width="64" height="64" aria-hidden="true" focusable="false" {!! $s !!}>
    @switch($name)
        @case('compass')
            <circle cx="32" cy="32" r="24"/><path d="M41.5 22.5 36 36l-13.5 5.5L28 28z"/><circle cx="32" cy="32" r="1.6" fill="currentColor"/>
            @break
        @case('lock')
            <rect x="16" y="28" width="32" height="24" rx="5"/><path d="M23 28v-6a9 9 0 0 1 18 0v6"/><circle cx="32" cy="40" r="2.4" fill="currentColor"/><path d="M32 42.5V46"/>
            @break
        @case('clock')
            <circle cx="32" cy="32" r="24"/><path d="M32 18v15l9 6"/>
            @break
        @case('tools')
            <path d="M44.5 12.5a11 11 0 0 0-13.6 14L12 45.4a4.6 4.6 0 0 0 6.6 6.6l18.9-18.9a11 11 0 0 0 14-13.6l-7.2 7.2-5.6-1.4-1.4-5.6z"/>
            @break
        @case('plug')
            <path d="M24 10v12M40 10v12"/><path d="M16 22h32v8a16 16 0 0 1-32 0z"/><path d="M32 46v8"/>
            @break
        @case('file')
            <path d="M18 8h20l12 12v32a4 4 0 0 1-4 4H18a4 4 0 0 1-4-4V12a4 4 0 0 1 4-4z"/><path d="M38 8v12h12"/><path d="M32 30v14M26 38l6-8 6 8"/>
            @break
        @default
            <path d="M32 8 58 52H6z"/><path d="M32 26v13"/><circle cx="32" cy="46" r="1.8" fill="currentColor"/>
    @endswitch
</svg>

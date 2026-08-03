@php $outstanding = array_filter($requirements, fn ($r) => ! $r['filled']); @endphp
@if(! empty($outstanding))
<div x-data="{ open: false }" class="rounded-lg bg-gray-50 dark:bg-gray-800/60 text-sm">
    <button
        type="button"
        x-on:click="open = ! open"
        class="flex w-full items-center justify-between px-4 py-2 font-semibold text-gray-700 dark:text-gray-300"
    >
        <span>{{ count($outstanding) }} of {{ count($requirements) }} still needed</span>
        <span x-text="open ? '▾' : '▸'"></span>
    </button>
    <ul x-show="open" x-cloak class="list-disc list-inside space-y-1 px-4 pb-3 text-gray-600 dark:text-gray-400">
        @foreach($outstanding as $item)
            <li>{{ $item['label'] }}</li>
        @endforeach
    </ul>
</div>
@endif

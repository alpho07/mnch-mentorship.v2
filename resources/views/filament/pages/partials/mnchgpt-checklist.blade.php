@php $outstanding = array_filter($requirements, fn ($r) => ! $r['filled']); @endphp
@if(! empty($outstanding))
<div class="rounded-lg bg-gray-50 dark:bg-gray-800/60 p-4 text-sm">
    <p class="font-semibold text-gray-700 dark:text-gray-300 mb-2">Still needed:</p>
    <ul class="list-disc list-inside space-y-1 text-gray-600 dark:text-gray-400">
        @foreach($outstanding as $item)
            <li>{{ $item['label'] }}</li>
        @endforeach
    </ul>
</div>
@endif

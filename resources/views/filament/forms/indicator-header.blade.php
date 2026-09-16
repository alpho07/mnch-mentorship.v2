{{-- resources/views/filament/forms/indicator-header.blade.php --}}
@if($indicator)
<div class="mb-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border">
    <h4 class="font-semibold text-gray-900 dark:text-white text-sm mb-1">
        {{ $indicator->name }}
    </h4>

    @if($indicator->description)
    <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
        {{ $indicator->description }}
    </p>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
        <div>
            <span class="font-medium text-blue-600 dark:text-blue-400">Numerator:</span>
            <span class="text-gray-700 dark:text-gray-300">{{ $indicator->numerator_description }}</span>
        </div>

        @if($indicator->calculation_type !== 'count' && $indicator->denominator_description)
        <div>
            <span class="font-medium text-green-600 dark:text-green-400">Denominator:</span>
            <span class="text-gray-700 dark:text-gray-300">{{ $indicator->denominator_description }}</span>
        </div>
        @endif
    </div>

    @if($indicator->target_value)
    <div class="mt-2 text-xs">
        <span class="font-medium text-orange-600 dark:text-orange-400">Target:</span>
        <span class="text-gray-700 dark:text-gray-300">
            {{ $indicator->target_value }}{{ $indicator->calculation_type === 'percentage' ? '%' : ($indicator->calculation_type === 'rate' ? ' per 1000' : '') }}
        </span>
    </div>
    @endif
</div>
@endif

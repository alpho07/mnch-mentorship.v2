@php
    $materials = $training->trainingMaterials;
    $totalPlannedCost = $materials->sum('total_cost');
    $totalActualCost = $materials->sum('actual_cost');
    $totalSavings = $totalPlannedCost - $totalActualCost;
@endphp

<div class="bg-gray-50 rounded-lg p-4">
    <h4 class="text-sm font-medium text-gray-900 mb-3">Material Cost Analysis</h4>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="text-center">
            <div class="text-lg font-semibold text-gray-900">${{ number_format($totalPlannedCost, 2) }}</div>
            <div class="text-sm text-gray-600">Planned Cost</div>
        </div>
        <div class="text-center">
            <div class="text-lg font-semibold text-gray-900">${{ number_format($totalActualCost, 2) }}</div>
            <div class="text-sm text-gray-600">Actual Cost</div>
        </div>
        <div class="text-center">
            <div class="text-lg font-semibold {{ $totalSavings >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ $totalSavings >= 0 ? '+' : '' }}${{ number_format($totalSavings, 2) }}
            </div>
            <div class="text-sm text-gray-600">{{ $totalSavings >= 0 ? 'Savings' : 'Overspend' }}</div>
        </div>
    </div>

    @if($materials->count() > 0)
        <div class="space-y-2">
            <h5 class="text-xs font-medium text-gray-700 uppercase tracking-wide">Utilization Efficiency</h5>
            @foreach($materials as $material)
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">{{ $material->inventoryItem->name }}</span>
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-900">{{ $material->usage_percentage }}%</span>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $material->status_color }}">
                            {{ $material->status }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
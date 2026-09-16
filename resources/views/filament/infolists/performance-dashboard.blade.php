<div class="bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-lg p-4">
    <h4 class="font-medium text-gray-900 mb-3">Performance Dashboard</h4>
    
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        <div class="bg-white rounded-lg p-3 text-center">
            <div class="text-xl font-bold text-blue-600">{{ $stats['departments_represented'] }}</div>
            <div class="text-xs text-gray-600">Departments</div>
        </div>
        <div class="bg-white rounded-lg p-3 text-center">
            <div class="text-xl font-bold text-green-600">{{ $stats['cadres_represented'] }}</div>
            <div class="text-xs text-gray-600">Cadres</div>
        </div>
        <div class="bg-white rounded-lg p-3 text-center">
            <div class="text-xl font-bold text-purple-600">KES {{ number_format($stats['cost_per_mentee'], 0) }}</div>
            <div class="text-xs text-gray-600">Cost/Mentee</div>
        </div>
        <div class="bg-white rounded-lg p-3 text-center">
            <div class="text-xl font-bold text-orange-600">KES {{ number_format($stats['total_material_cost'], 0) }}</div>
            <div class="text-xs text-gray-600">Total Cost</div>
        </div>
    </div>
</div>
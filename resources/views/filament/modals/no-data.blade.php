{{-- resources/views/filament/modals/no-data.blade.php --}}
<div class="text-center py-8">
    <x-heroicon-o-exclamation-triangle class="mx-auto h-12 w-12 text-gray-400" />
    <h3 class="mt-2 text-sm font-medium text-gray-900">{{ $message ?? 'No data available' }}</h3>
    <p class="mt-1 text-sm text-gray-500">Please try again or contact support if this issue persists.</p>
</div>
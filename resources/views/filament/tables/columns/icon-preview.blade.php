<div class="flex items-center space-x-2">
    @if($getRecord()->icon)
        <x-filament::icon
            :name="'heroicon-o-' . $getRecord()->icon"
            class="w-5 h-5"
            :style="'color: ' . $getRecord()->color"
        />
        <span class="text-xs text-gray-500">{{ $getRecord()->icon }}</span>
    @else
        <span class="text-xs text-gray-400">No icon</span>
    @endif
</div>

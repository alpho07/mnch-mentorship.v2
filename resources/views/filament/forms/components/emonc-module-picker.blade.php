@php
    $statePath = $field->getStatePath();
    $modules = $field->getModules();
    $assignedModules = $field->getAssignedModules();
    $hasError = $errors->has($statePath);
@endphp

<div
    x-data="{
        selected: $wire.entangle('{{ $statePath }}').live,
        isSelected(id) {
            return this.selected && (this.selected.includes(String(id)) || this.selected.includes(Number(id)));
        },
        hasChildrenSelected(childIds) {
            return childIds.some(id => this.isSelected(id));
        },
        allChildrenSelected(childIds) {
            return childIds.length > 0 && childIds.every(id => this.isSelected(id));
        },
        toggleModule(moduleId, childIds) {
            if (!this.selected) {
                this.selected = [];
            }

            const childIdStrings = childIds.map(id => String(id));

            if (childIdStrings.length === 0) {
                // Leaf module — toggle the module itself.
                this.toggleTrack(moduleId);
                return;
            }

            // Parent with tracks: select all if not all selected, otherwise clear all.
            if (this.allChildrenSelected(childIds)) {
                this.selected = this.selected.filter(id => !childIdStrings.includes(id));
            } else {
                this.selected = [...new Set([...this.selected, ...childIdStrings])];
            }
        },
        toggleTrack(trackId) {
            if (!this.selected) {
                this.selected = [];
            }
            const trackIdStr = String(trackId);
            if (this.isSelected(trackIdStr)) {
                this.selected = this.selected.filter(id => id !== trackIdStr);
            } else {
                this.selected = [...this.selected, trackIdStr];
            }
        }
    }"
    class="emonc-module-picker {{ $hasError ? 'emonc-module-picker--error' : '' }}"
>
    @if($assignedModules->isNotEmpty())
        <div class="mb-3 space-y-3">
            @foreach($assignedModules as $module)
                @php
                    $assignedChildren = $module->assignedChildren ?? collect();
                @endphp
                <div class="rounded-lg border border-success-200 dark:border-success-800 overflow-hidden">
                    <div class="flex items-center gap-3 px-4 py-3 bg-success-50 dark:bg-success-900/20">
                        <x-heroicon-o-check-circle class="h-4 w-4 shrink-0 text-success-600 dark:text-success-400" />
                        <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">
                            {{ $module->name }}
                        </span>
                        <span class="ms-auto text-xs font-medium text-success-700 dark:text-success-400">
                            Already added
                        </span>
                    </div>
                    @if($assignedChildren->isNotEmpty())
                        <div class="divide-y divide-success-100 dark:divide-success-900">
                            @foreach($assignedChildren as $track)
                                <div class="flex items-center gap-3 px-4 py-2.5">
                                    <x-heroicon-o-check-circle class="h-4 w-4 shrink-0 text-success-600 dark:text-success-400" />
                                    <span class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ $track->name }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if($modules->isEmpty())
        <div class="text-sm text-gray-500 dark:text-gray-400">
            No modules available to add.
        </div>
    @else
        <div class="space-y-3">
            @foreach($modules as $module)
                @php
                    $children = $module->availableChildren ?? $module->children ?? collect();
                    $hasChildren = $children->isNotEmpty();
                    $childIds = $children->pluck('id')->toArray();
                    $childIdsJs = implode(',', $childIds);
                @endphp

                <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    {{-- Module header --}}
                    <label
                        class="flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-800 cursor-pointer select-none"
                        :class="{ 'bg-primary-50 dark:bg-primary-900/20': {{ $hasChildren ? 'true' : 'false' }} && hasChildrenSelected([{{ $childIdsJs }}]) }"
                        @click.prevent="toggleModule({{ $module->id }}, [{{ $childIdsJs }}])"
                    >
                        @if($hasChildren)
                            <input
                                type="checkbox"
                                :checked="hasChildrenSelected([{{ $childIdsJs }}])"
                                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600 pointer-events-none"
                            >
                        @else
                            <input
                                type="checkbox"
                                :checked="isSelected({{ $module->id }})"
                                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600 pointer-events-none"
                            >
                        @endif
                        <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">
                            {{ $module->name }}
                        </span>
                    </label>

                    {{-- Tracks --}}
                    @if($hasChildren)
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($children as $track)
                                <label
                                    class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer transition-colors"
                                    @click.prevent="toggleTrack({{ $track->id }})"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="isSelected({{ $track->id }})"
                                        class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600 pointer-events-none"
                                    >
                                    <span class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ $track->name }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if($hasError)
        <p class="text-sm text-danger-600 dark:text-danger-400 mt-2">
            {{ $errors->first($statePath) }}
        </p>
    @endif
</div>

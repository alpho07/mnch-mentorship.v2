@php
    $statePath = $field->getStatePath();
    $options = $field->getOptionsList();
    $lockedOptions = $field->getLockedOptionsList();
    $hasError = $errors->has($statePath);
    $max = $field->getMaxSelections();
@endphp

<div
    x-data="{
        selected: $wire.entangle('{{ $statePath }}').live,
        max: {{ $max === null ? 'null' : (int) $max }},
        isSelected(id) {
            return this.selected && (this.selected.includes(String(id)) || this.selected.includes(Number(id)));
        },
        atLimit() {
            return this.max !== null && (this.selected?.length ?? 0) >= this.max;
        },
        toggle(id) {
            if (!this.selected) {
                this.selected = [];
            }
            const idStr = String(id);
            if (this.isSelected(idStr)) {
                this.selected = this.selected.filter(v => v !== idStr);
                return;
            }
            if (this.atLimit()) {
                return;
            }
            this.selected = [...this.selected, idStr];
        }
    }"
    class="card-checkbox-list {{ $hasError ? 'card-checkbox-list--error' : '' }}"
>
    @if($max !== null)
        <div class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">
            <span x-text="selected?.length ?? 0"></span>/{{ $max }} selected
            <span x-show="atLimit()" x-cloak class="ml-1 text-warning-600 dark:text-warning-400">
                — limit reached, uncheck one to pick another
            </span>
        </div>
    @endif

    @if(! empty($lockedOptions))
        <div class="mb-3 space-y-3">
            @foreach($lockedOptions as $id => $label)
                <div class="rounded-lg border border-success-200 dark:border-success-800 overflow-hidden">
                    <div class="flex items-center gap-3 px-4 py-3 bg-success-50 dark:bg-success-900/20">
                        <x-heroicon-o-check-circle class="h-4 w-4 shrink-0 text-success-600 dark:text-success-400" />
                        <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">
                            {{ $label }}
                        </span>
                        <span class="ms-auto text-xs font-medium text-success-700 dark:text-success-400">
                            Already added
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if(empty($options))
        @if(empty($lockedOptions))
            <div class="text-sm text-gray-500 dark:text-gray-400">
                No options available.
            </div>
        @endif
    @else
        <div class="space-y-3">
            @foreach($options as $id => $label)
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <label
                        class="flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-800 select-none transition-opacity"
                        :class="{
                            'bg-primary-50 dark:bg-primary-900/20': isSelected(@js((string) $id)),
                            'cursor-pointer': isSelected(@js((string) $id)) || !atLimit(),
                            'cursor-not-allowed opacity-50': !isSelected(@js((string) $id)) && atLimit(),
                        }"
                        @click.prevent="toggle(@js((string) $id))"
                    >
                        <input
                            type="checkbox"
                            :checked="isSelected(@js((string) $id))"
                            :disabled="!isSelected(@js((string) $id)) && atLimit()"
                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600 pointer-events-none"
                        >
                        <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">
                            {{ $label }}
                        </span>
                    </label>
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

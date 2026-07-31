@php
    $statePath = $field->getStatePath();
    $options = $field->getOptionsList();
    $hasError = $errors->has($statePath);
@endphp

<div
    x-data="{
        selected: $wire.entangle('{{ $statePath }}').live,
        isSelected(id) {
            return this.selected && (this.selected.includes(String(id)) || this.selected.includes(Number(id)));
        },
        toggle(id) {
            if (!this.selected) {
                this.selected = [];
            }
            const idStr = String(id);
            if (this.isSelected(idStr)) {
                this.selected = this.selected.filter(v => v !== idStr);
            } else {
                this.selected = [...this.selected, idStr];
            }
        }
    }"
    class="card-checkbox-list {{ $hasError ? 'card-checkbox-list--error' : '' }}"
>
    @if(empty($options))
        <div class="text-sm text-gray-500 dark:text-gray-400">
            No options available.
        </div>
    @else
        <div class="space-y-3">
            @foreach($options as $id => $label)
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <label
                        class="flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-800 cursor-pointer select-none"
                        :class="{ 'bg-primary-50 dark:bg-primary-900/20': isSelected(@js((string) $id)) }"
                        @click.prevent="toggle(@js((string) $id))"
                    >
                        <input
                            type="checkbox"
                            :checked="isSelected(@js((string) $id))"
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

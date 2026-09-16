@php $modules = $this->getEmoncModuleTree(); @endphp

<div
    wire:key="turn-modules"
    x-data="{
        selected: [],
        dates: $wire.entangle('moduleDates').live,
        dateModal: { open: false, id: null, start: '', end: '' },
        isSelected(id) {
            return this.selected.includes(String(id)) || this.selected.includes(Number(id));
        },
        hasChildrenSelected(childIds) {
            return childIds.some(id => this.isSelected(id));
        },
        allChildrenSelected(childIds) {
            return childIds.length > 0 && childIds.every(id => this.isSelected(id));
        },
        toggleModule(moduleId, childIds) {
            const childIdStrings = childIds.map(id => String(id));

            if (childIdStrings.length === 0) {
                // Leaf module — toggle the module itself.
                this.toggleTrack(moduleId);
                return;
            }

            // Parent with tracks: select all if not all selected, otherwise clear all.
            if (this.allChildrenSelected(childIds)) {
                this.selected = this.selected.filter(id => !childIdStrings.includes(id));
                childIdStrings.forEach(id => this.clearDates(id));
            } else {
                this.selected = [...new Set([...this.selected, ...childIdStrings])];
            }
        },
        toggleTrack(trackId) {
            const trackIdStr = String(trackId);
            if (this.isSelected(trackIdStr)) {
                this.selected = this.selected.filter(id => id !== trackIdStr);
                this.clearDates(trackIdStr);
            } else {
                this.selected = [...this.selected, trackIdStr];
                this.openDateModal(trackIdStr);
            }
        },
        openDateModal(id) {
            if (!this.dates) {
                this.dates = {};
            }
            const existing = this.dates[id] ?? {};
            this.dateModal = { open: true, id, start: existing.start ?? '', end: existing.end ?? '' };
        },
        saveDateModal() {
            if (!this.dates) {
                this.dates = {};
            }
            this.dates = {
                ...this.dates,
                [this.dateModal.id]: { start: this.dateModal.start, end: this.dateModal.end },
            };
            this.dateModal.open = false;
        },
        skipDateModal() {
            this.dateModal.open = false;
        },
        clearDates(id) {
            if (!this.dates || !this.dates[id]) {
                return;
            }
            const next = { ...this.dates };
            delete next[id];
            this.dates = next;
        },
        formatDateRange(id) {
            const entry = this.dates?.[id];
            if (!entry || (!entry.start && !entry.end)) {
                return '';
            }
            const fmt = (value) => {
                if (!value) return '?';
                const d = new Date(value + 'T00:00:00');
                return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            };
            return `(${fmt(entry.start)} – ${fmt(entry.end)})`;
        }
    }"
    class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3"
>
    @if ($modules->isEmpty())
        <div class="text-sm text-gray-500 dark:text-gray-400">
            No modules available to add.
        </div>
    @else
        <div class="space-y-3">
            @foreach ($modules as $module)
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
                        @if ($hasChildren)
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
                        @unless ($hasChildren)
                            <span class="text-xs text-gray-500 dark:text-gray-400" x-text="formatDateRange({{ $module->id }})"></span>
                            <button
                                type="button"
                                x-show="isSelected({{ $module->id }})"
                                x-cloak
                                class="ms-auto text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                                @click.stop.prevent="openDateModal('{{ $module->id }}')"
                            >
                                Set dates
                            </button>
                        @endunless
                    </label>

                    {{-- Tracks --}}
                    @if ($hasChildren)
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($children as $track)
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
                                    <span class="text-xs text-gray-500 dark:text-gray-400" x-text="formatDateRange('{{ $track->id }}')"></span>
                                    <button
                                        type="button"
                                        x-show="isSelected({{ $track->id }})"
                                        x-cloak
                                        class="ms-auto text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                                        @click.stop.prevent="openDateModal('{{ $track->id }}')"
                                    >
                                        Set dates
                                    </button>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @error('value')
        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
    @enderror

    <x-filament::button x-on:click="$wire.submitModules(selected)">
        Continue
    </x-filament::button>

    {{-- Per-row start/end date modal — pops up right after checking a
         module/track (or via "Set dates" to edit later). --}}
    <div
        x-show="dateModal.open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click.self="skipDateModal()"
        @keydown.escape.window="skipDateModal()"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-6"
    >
        <div
            @click.stop
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900"
        >
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                Set dates
            </h3>
            <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">
                When does this run? You can set it later, but it's needed for every selected module before you can continue.
            </p>

            <div class="mt-5 grid grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">Start Date</label>
                    <input
                        type="date"
                        x-model="dateModal.start"
                        min="{{ now()->toDateString() }}"
                        class="mt-1.5 block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    >
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300">End Date</label>
                    <input
                        type="date"
                        x-model="dateModal.end"
                        :min="dateModal.start || '{{ now()->toDateString() }}'"
                        class="mt-1.5 block w-full rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    >
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    @click="skipDateModal()"
                    class="rounded-lg px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Later
                </button>
                <button
                    type="button"
                    @click="saveDateModal()"
                    class="rounded-lg bg-primary-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-primary-500"
                >
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

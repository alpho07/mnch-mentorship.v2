@if($training)
    <x-filament::section>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <x-heroicon-o-exclamation-triangle class="h-6 w-6 shrink-0 text-warning-500" />
                <div>
                    <h3 class="text-base font-semibold">
                        You have a pending mentorship setup
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        <span class="font-medium">{{ $training->title }}</span>
                        @if($class)
                            — class "{{ $class->name }}"
                        @endif
                        was started but never finished. Continue where you left off, or discard it.
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <x-filament::button tag="a" :href="$continueUrl" color="primary">
                    Continue
                </x-filament::button>

                <x-filament::button
                    type="button"
                    color="danger"
                    outlined
                    wire:click="discard"
                    wire:confirm="Discard this pending mentorship draft? This cannot be undone."
                >
                    Discard
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
@endif

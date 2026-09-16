<x-filament-panels::page>

    {{-- ─────────────────────────────────────────────────────────────────────
         STATUS BANNER
         Shows the current configuration state prominently at the top.
    ───────────────────────────────────────────────────────────────────────── --}}
    @if ($facilityLocked && $isConfigured)
        <div class="rounded-xl border border-success-200 bg-success-50 dark:bg-success-950/20 dark:border-success-800 p-4 flex items-start gap-3">
            <x-heroicon-o-lock-closed class="w-5 h-5 text-success-600 dark:text-success-400 shrink-0 mt-0.5" />
            <div>
                <p class="text-sm font-semibold text-success-700 dark:text-success-300">
                Facility Setup Complete &amp; Locked
                </p>
                <p class="text-xs text-success-600 dark:text-success-400 mt-0.5">
                    <strong>{{ $facilityData['name'] ?? '—' }}</strong>
                    @if($facilityData['mfl_code'] ?? null)
                        (MFL: {{ $facilityData['mfl_code'] }})
                    @endif
                is confirmed for indicator reporting.
                    Reporting types: <strong>{{ collect($enabledTypes)->pluck('name')->join(', ') }}</strong>.
                    @if($isSuperAdmin)
                        <span class="ml-1 text-warning-600">[Super Admin: use "Unlock Assignment" to modify]</span>
                    @endif
                </p>
                @if($assignmentData['locked_at'] ?? null)
                    <p class="text-xs text-success-500 dark:text-success-500 mt-1">
                        Locked on {{ \Carbon\Carbon::parse($assignmentData['locked_at'])->format('d M Y, H:i') }}
                    </p>
                @endif
            </div>
        </div>

        @elseif ($isConfigured && !$facilityLocked)
        <div class="rounded-xl border border-warning-200 bg-warning-50 dark:bg-warning-950/20 dark:border-warning-800 p-4 flex items-start gap-3">
            <x-heroicon-o-clock class="w-5 h-5 text-warning-600 dark:text-warning-400 shrink-0 mt-0.5" />
            <div>
                <p class="text-sm font-semibold text-warning-700 dark:text-warning-300">
                Setup Saved — Not Yet Locked
                </p>
                <p class="text-xs text-warning-600 dark:text-warning-400 mt-0.5">
                Facility setup has been saved but not confirmed. 
                    Click <strong>Confirm &amp; Lock</strong> when you are ready to start submitting reports.
                </p>
            </div>
        </div>

    @else
        <div class="rounded-xl border border-primary-200 bg-primary-50 dark:bg-primary-950/20 dark:border-primary-800 p-4 flex items-start gap-3">
            <x-heroicon-o-information-circle class="w-5 h-5 text-primary-600 dark:text-primary-400 shrink-0 mt-0.5" />
            <div>
                <p class="text-sm font-semibold text-primary-700 dark:text-primary-300">
                Facility Setup Required
                </p>
                <p class="text-xs text-primary-600 dark:text-primary-400 mt-0.5">
                Before you can submit indicator reports, you need to confirm which facility 
                this account represents and which report types apply.
                    @if(!$facilityData)
                Select your facility below to get started.
                    @else
                Your facility has been pre-assigned. Select the report types and confirm to proceed.
                    @endif
                </p>
            </div>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────────────
         FACILITY CARD (read-only preview when a facility is resolved)
    ───────────────────────────────────────────────────────────────────────── --}}
    @if ($facilityData && $facilityLocked)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-building-office-2 class="w-5 h-5 text-primary-500" />
                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                    Assigned Facility
                    </span>
                </div>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium
                    {{ $facilityLocked ? 'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400' : 'bg-gray-100 text-gray-600' }}">
                    <x-heroicon-m-lock-closed class="w-3 h-3" />
                    {{ $facilityLocked ? 'Locked' : 'Unlocked' }}
                </span>
            </div>
            <div class="px-5 py-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Facility Name</p>
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $facilityData['name'] }}</p>
                </div>
                @if($facilityData['mfl_code'] ?? null)
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">MFL Code</p>
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $facilityData['mfl_code'] }}</p>
                    </div>
                @endif
                @if($facilityData['subcounty'] ?? null)
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Sub-County</p>
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $facilityData['subcounty'] }}</p>
                    </div>
                @endif
                @if($facilityData['county'] ?? null)
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">County</p>
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $facilityData['county'] }}</p>
                    </div>
                @endif
            </div>

            {{-- Enabled Report Types badges --}}
            @if(count($enabledTypes))
                <div class="px-5 pb-4">
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-2">Active Report Types</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($enabledTypes as $rt)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium
                      bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                                <x-heroicon-m-clipboard-document-list class="w-3 h-3" />
                                {{ $rt['name'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────────────
         SETUP FORM
         Hidden when fully locked AND user is not super_admin
    ───────────────────────────────────────────────────────────────────────── --}}
    @if (! $facilityLocked || $isSuperAdmin)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                    <x-heroicon-o-cog-6-tooth class="w-4 h-4 text-gray-400" />
                Configuration
                </h3>
                @if($isSuperAdmin && $facilityLocked)
                    <p class="text-xs text-warning-600 mt-1">
                You are viewing a locked assignment as Super Admin. Changes here will take effect after saving.
                    </p>
                @endif
            </div>
            <div class="p-5">
                <form wire:submit.prevent="">
                    {{ $this->form }}
                </form>
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            @if(! $facilityLocked || $isSuperAdmin)
                <x-filament::button
            wire:click="saveAssignment"
            wire:loading.attr="disabled"
            color="gray"
            icon="heroicon-o-check"
            >
                    <span wire:loading.remove wire:target="saveAssignment">Save</span>
                    <span wire:loading wire:target="saveAssignment">Saving…</span>
                </x-filament::button>

                <x-filament::button
            wire:click="$dispatch('open-modal', { id: 'confirm-lock' })"
            color="success"
            icon="heroicon-o-lock-closed"
            >
            Confirm &amp; Lock
                </x-filament::button>
            @endif

            @if($facilityLocked && $isSuperAdmin)
                <x-filament::button
            wire:click="$dispatch('open-modal', { id: 'confirm-unlock' })"
            color="warning"
            icon="heroicon-o-lock-open"
            >
            Unlock Assignment
                </x-filament::button>
            @endif
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────────────
         CONFIRM LOCK MODAL
    ───────────────────────────────────────────────────────────────────────── --}}
    <x-filament::modal id="confirm-lock" width="md">
        <x-slot name="heading">
            Confirm &amp; Lock Facility Setup
        </x-slot>
        <x-slot name="description">
            Once locked, this configuration cannot be changed without administrator intervention.
            Make sure the facility and report types are correct before confirming.
        </x-slot>
        <x-slot name="footer">
            <x-filament::button
                wire:click="lockAssignment"
                color="success"
                icon="heroicon-o-lock-closed"
                >
                Yes, Confirm &amp; Lock
            </x-filament::button>
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'confirm-lock' })"
                color="gray"
                >
                Cancel
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- ─────────────────────────────────────────────────────────────────────
         CONFIRM UNLOCK MODAL
    ───────────────────────────────────────────────────────────────────────── --}}
    <x-filament::modal id="confirm-unlock" width="md">
        <x-slot name="heading">
            Unlock Facility Assignment
        </x-slot>
        <x-slot name="description">
            Unlocking allows modification of the facility and report type configuration.
            Remember to re-lock after making changes.
        </x-slot>
        <x-slot name="footer">
            <x-filament::button
                wire:click="unlockAssignment"
                color="warning"
                icon="heroicon-o-lock-open"
                >
                Unlock
            </x-filament::button>
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'confirm-unlock' })"
                color="gray"
                >
                Cancel
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- ─────────────────────────────────────────────────────────────────────
         WHAT'S NEXT — guides user to reporting after setup
    ───────────────────────────────────────────────────────────────────────── --}}
    @if ($facilityLocked && $isConfigured)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                    <x-heroicon-o-arrow-right-circle class="w-4 h-4 text-primary-500" />
                Next Steps
                </h3>
            </div>
            <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Step 1 --}}
                <a href="{{ \App\Filament\Pages\Indicators\IndicatorReporting::getUrl() }}"
               class="group rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:border-primary-400 hover:shadow-sm transition-all">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 text-sm font-bold">1</span>
                        <x-heroicon-o-clipboard-document-check class="w-5 h-5 text-primary-500" />
                    </div>
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100 group-hover:text-primary-600">Submit a Report</p>
                    <p class="text-xs text-gray-500 mt-1">Select a reporting period and fill in indicator data.</p>
                </a>

                {{-- Step 2 --}}
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500 text-sm font-bold">2</span>
                        <x-heroicon-o-check-badge class="w-5 h-5 text-gray-400" />
                    </div>
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100">Validation</p>
                    <p class="text-xs text-gray-500 mt-1">A county or national mentor validates your submission.</p>
                </div>

                {{-- Step 3 --}}
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500 text-sm font-bold">3</span>
                        <x-heroicon-o-arrow-up-circle class="w-5 h-5 text-gray-400" />
                    </div>
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100">DHIS2 Sync</p>
                    <p class="text-xs text-gray-500 mt-1">Validated reports are pushed to DHIS2 automatically or manually.</p>
                </div>
            </div>
        </div>
    @endif

</x-filament-panels::page>
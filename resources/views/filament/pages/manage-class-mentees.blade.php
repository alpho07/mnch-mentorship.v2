<x-filament-panels::page>
    {{-- Enrollment Link Section (Persistent Display) --}}
    @if($enrollmentLink)
        <div class="mb-6">
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::icon 
                            icon="heroicon-o-link" 
                            class="h-5 w-5 text-primary-500"
                        />
                        <span>Class Enrollment Link</span>
                    </div>
                </x-slot>
                
                <x-slot name="description">
                    Share this link with mentees to enroll in this class
                </x-slot>
                
                <div class="space-y-3">
                    {{-- Link Display Box --}}
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="flex-1 font-mono text-sm text-gray-700 dark:text-gray-300 break-all">
                                {{ $enrollmentLink }}
                            </div>
                        </div>
                    </div>
                    
                    {{-- Action Buttons --}}
                    <div class="flex flex-wrap gap-2">
                        {{-- Copy Button --}}
                        <x-filament::button
                            color="primary"
                            icon="heroicon-o-clipboard-document"
                            x-data="{}"
                            x-on:click="
                                navigator.clipboard.writeText('{{ $enrollmentLink }}');
                                new FilamentNotification()
                                    .title('Link Copied!')
                                    .success()
                                    .send();
                            "
                        >
                            Copy Link
                        </x-filament::button>
                        
                        {{-- Open in New Tab --}}
                        <x-filament::button
                            color="gray"
                            icon="heroicon-o-arrow-top-right-on-square"
                            tag="a"
                            :href="$enrollmentLink"
                            target="_blank"
                        >
                            Open Link
                        </x-filament::button>
                        
                        {{-- Share Button (if supported) --}}
                        <x-filament::button
                            color="info"
                            icon="heroicon-o-share"
                            x-data="{}"
                            x-on:click="
                                if (navigator.share) {
                                    navigator.share({
                                        title: 'Class Enrollment',
                                        text: 'Join {{ addslashes($class->name) }}',
                                        url: '{{ $enrollmentLink }}'
                                    });
                                } else {
                                    navigator.clipboard.writeText('{{ $enrollmentLink }}');
                                    new FilamentNotification()
                                        .title('Link Copied!')
                                        .body('Share button not supported. Link copied to clipboard.')
                                        .success()
                                        .send();
                                }
                            "
                        >
                            Share
                        </x-filament::button>
                        
                        {{-- Deactivate Button --}}
                        <x-filament::button
                            color="danger"
                            icon="heroicon-o-x-circle"
                            wire:click="deactivateEnrollmentLink"
                            wire:confirm="Are you sure you want to deactivate this enrollment link? Mentees will no longer be able to use it."
                        >
                            Deactivate Link
                        </x-filament::button>
                    </div>
                    
                    {{-- Link Stats --}}
                    <div class="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-400">
                        <div class="flex items-center gap-2">
                            <x-filament::icon 
                                icon="heroicon-o-users" 
                                class="h-4 w-4"
                            />
                            <span>{{ $class->participants()->count() }} enrolled</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::icon 
                                icon="heroicon-o-check-circle" 
                                class="h-4 w-4 text-green-500"
                            />
                            <span>Link Active</span>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        </div>
    @endif
    
    {{-- Main Table --}}
    {{ $this->table }}
</x-filament-panels::page>
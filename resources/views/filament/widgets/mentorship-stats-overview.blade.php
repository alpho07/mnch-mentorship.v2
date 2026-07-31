<x-filament-widgets::widget>
    @php
        $programColors = [
            'Maternal Health (EmONC)' => ['from' => '#C81E70', 'to' => '#8F1152', 'bg' => '#FCE9F1', 'icon' => 'heroicon-o-heart'],
            'Newborn Care'            => ['from' => '#A855C8', 'to' => '#6B2E8C', 'bg' => '#F5EBFA', 'icon' => 'heroicon-o-face-smile'],
            'Infant and Child Care'   => ['from' => '#7DB83A', 'to' => '#4B7A1A', 'bg' => '#EFF7E6', 'icon' => 'heroicon-o-puzzle-piece'],
        ];
        $defaultColor = ['from' => '#1D6FB8', 'to' => '#2C478D', 'bg' => '#EAF7FE', 'icon' => 'heroicon-o-academic-cap'];

        $cards = [['name' => 'All Mentorships', 'color' => $defaultColor, 'mentorships' => $overall['mentorships'], 'mentees' => $overall['mentees']]];
        foreach ($programs as $program) {
            $cards[] = [
                'name' => $program['name'],
                'color' => $programColors[$program['name']] ?? $defaultColor,
                'mentorships' => $program['mentorships'],
                'mentees' => $program['mentees'],
            ];
        }
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
        @foreach($cards as $card)
            <div class="relative overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
                <div class="absolute top-0 left-0 right-0 h-1" style="background:linear-gradient(90deg,{{ $card['color']['from'] }},{{ $card['color']['to'] }})"></div>
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background:{{ $card['color']['bg'] }}">
                            <x-dynamic-component :component="$card['color']['icon']" class="w-5 h-5" style="color:{{ $card['color']['from'] }}" />
                        </div>
                        <p class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 truncate">{{ $card['name'] }}</p>
                    </div>
                    <div class="grid grid-cols-2 divide-x divide-gray-100 dark:divide-gray-800">
                        <div class="pr-5">
                            <p class="text-3xl font-extrabold text-gray-900 dark:text-white leading-none">{{ $card['mentorships'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Mentorships</p>
                        </div>
                        <div class="pl-5">
                            <p class="text-3xl font-extrabold text-gray-900 dark:text-white leading-none">{{ $card['mentees'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Mentees</p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-4">Live mentorships only — pilot runs are excluded from these counts.</p>
</x-filament-widgets::widget>

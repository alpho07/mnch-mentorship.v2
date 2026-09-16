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
            @php
                $avgMentees = $card['mentorships'] > 0 ? round($card['mentees'] / $card['mentorships'], 1) : 0;
            @endphp
            <div class="group relative overflow-hidden rounded-2xl border border-gray-200/70 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 ease-out">
                {{-- Decorative glow, positioned behind everything --}}
                <div
                    class="pointer-events-none absolute -top-12 -right-12 w-40 h-40 rounded-full opacity-[0.09] dark:opacity-[0.14] blur-2xl transition-opacity duration-300 group-hover:opacity-[0.16]"
                    style="background:{{ $card['color']['from'] }}"
                ></div>

                {{-- Top accent bar --}}
                <div class="h-1.5 w-full" style="background:linear-gradient(90deg,{{ $card['color']['from'] }},{{ $card['color']['to'] }})"></div>

                <div class="relative p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div
                            class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-md transition-transform duration-300 group-hover:scale-105"
                            style="background:linear-gradient(135deg,{{ $card['color']['from'] }},{{ $card['color']['to'] }})"
                        >
                            <x-dynamic-component :component="$card['color']['icon']" class="w-5 h-5 text-white" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 truncate">{{ $card['name'] }}</p>
                            @if($avgMentees > 0)
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">~{{ $avgMentees }} mentees / mentorship</p>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-gray-50 dark:bg-gray-800/60 px-4 py-3.5">
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <x-heroicon-o-briefcase class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" />
                                <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Mentorships</p>
                            </div>
                            <p class="text-2xl font-extrabold leading-none" style="color:{{ $card['color']['from'] }}">{{ $card['mentorships'] }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 dark:bg-gray-800/60 px-4 py-3.5">
                            <div class="flex items-center gap-1.5 mb-1.5">
                                <x-heroicon-o-user-group class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" />
                                <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500">Mentees</p>
                            </div>
                            <p class="text-2xl font-extrabold leading-none text-gray-900 dark:text-white">{{ $card['mentees'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-4">Live mentorships only — pilot runs are excluded from these counts.</p>
</x-filament-widgets::widget>

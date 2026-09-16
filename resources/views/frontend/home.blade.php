@extends('layouts.app')

@section('title', 'MNCH Kenya — Training & Resource Hub')
@section('meta_description', 'Access training resources, upcoming programs, and learning materials for maternal, neonatal, and child health in Kenya.')

@section('content')
<div x-data="homePage">

    {{-- ═══════════════════════════════════════════
         THREE PILLARS OF CARE — MoH clinical hero
    ═══════════════════════════════════════════ --}}
    <section class="relative overflow-hidden" data-aos="fade-up" data-aos-delay="80">
        <img src="{{ asset('mnch_hero_wide.png') }}" alt="Strengthening care for mothers, newborns & children — Healthy today, stronger tomorrow"
             class="w-full h-auto block">

        {{-- Mini pillar cards, overlaid on the left side of the hero image --}}
        <div class="hidden lg:flex absolute inset-y-0 left-0 flex-col justify-center gap-4 pl-6 xl:pl-12 py-8 pillar-grid">
            <a href="{{ route('resources.category', 'maternal-health') }}"
               class="group relative overflow-hidden rounded-2xl p-4 w-48 text-white pillar-card"
               style="background: linear-gradient(155deg, #C81E70 0%, #8F1152 100%); --pillar-shadow: rgba(200,30,112,.55); --pillar-shadow-soft: rgba(200,30,112,.3);">
                <div class="pillar-sheen"></div>
                <div class="relative">
                    <div class="w-9 h-9 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center mb-2.5 border border-white/25 transition-transform group-hover:scale-110">
                        <i class="fas fa-heart-pulse text-sm"></i>
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/75 mb-0.5">Maternal Health</p>
                    <h4 class="text-sm font-extrabold leading-tight">EmONC Mentorship</h4>
                </div>
            </a>
            <a href="{{ route('resources.category', 'newborn-care') }}"
               class="group relative overflow-hidden rounded-2xl p-4 w-48 text-white pillar-card"
               style="background: linear-gradient(155deg, #A855C8 0%, #6B2E8C 100%); --pillar-shadow: rgba(168,85,200,.55); --pillar-shadow-soft: rgba(168,85,200,.3);">
                <div class="pillar-sheen"></div>
                <div class="relative">
                    <div class="w-9 h-9 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center mb-2.5 border border-white/25 transition-transform group-hover:scale-110">
                        <i class="fas fa-baby-carriage text-sm"></i>
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/75 mb-0.5">Neonatal Care</p>
                    <h4 class="text-sm font-extrabold leading-tight">Newborn Mentorship</h4>
                </div>
            </a>
            <a href="{{ route('resources.category', 'child-health') }}"
               class="group relative overflow-hidden rounded-2xl p-4 w-48 text-white pillar-card"
               style="background: linear-gradient(155deg, #7DB83A 0%, #4B7A1A 100%); --pillar-shadow: rgba(125,184,58,.55); --pillar-shadow-soft: rgba(125,184,58,.3);">
                <div class="pillar-sheen"></div>
                <div class="relative">
                    <div class="w-9 h-9 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center mb-2.5 border border-white/25 transition-transform group-hover:scale-110">
                        <i class="fas fa-child-reaching text-sm"></i>
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/75 mb-0.5">Paediatric Care</p>
                    <h4 class="text-sm font-extrabold leading-tight">Infant &amp; Child Mentorship</h4>
                </div>
            </a>
        </div>
    </section>

    {{-- ═══════════════════════════════════════════
         TRAINING + MENTORSHIP INSIGHTS
    ═══════════════════════════════════════════ --}}
    @if($trainingInsights['has_data'] ?? false)
    <section class="relative overflow-hidden py-14" data-aos="fade-up" data-aos-delay="100" style="background: linear-gradient(135deg, #16224A 0%, #1B2E5E 48%, #2C478D 100%);">
        <div class="absolute inset-0 opacity-[0.07]">
            <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                <pattern id="insight-grid" width="42" height="42" patternUnits="userSpaceOnUse">
                    <path d="M 42 0 L 0 0 0 42" fill="none" stroke="white" stroke-width="1"/>
                </pattern>
                <rect width="100%" height="100%" fill="url(#insight-grid)"/>
            </svg>
        </div>
        <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full opacity-20" style="background: radial-gradient(circle, #6FC4EF 0%, transparent 68%);"></div>
        <div class="absolute -left-20 bottom-0 h-64 w-64 rounded-full opacity-10" style="background: radial-gradient(circle, #FCD34D 0%, transparent 70%);"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5 mb-8">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-sky-200/20 px-3 py-1 text-xs font-bold uppercase tracking-widest text-sky-100"
                         style="background: rgba(255,255,255,0.08);">
                        <i class="fas fa-chart-line text-sky-200"></i>
                        Program Intelligence
                    </div>
                    <h2 class="mt-4 text-2xl md:text-4xl font-extrabold text-white tracking-tight">
                        Training and mentorship signals that need attention
                    </h2>
                    <p class="mt-2 text-sm md:text-base text-sky-100/90 leading-relaxed">
                        A live readout from scheduled trainings, facility mentorships, class enrollments, and participant records.
                    </p>
                </div>
                <a href="{{ url('analytics/dashboard') }}"
                   class="analytics-cta-pulse inline-flex w-fit items-center gap-2 rounded-xl border border-white/30 px-5 py-3 text-sm font-bold text-white transition-all hover:border-white/60 hover:scale-105"
                   style="background: rgba(255,255,255,0.15); box-shadow: 0 0 20px rgba(111, 196, 239, 0.25);">
                    Open analytics map <i class="fas fa-arrow-right text-xs analytics-cta-arrow"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                @foreach(($trainingInsights['cards'] ?? []) as $index => $card)
                    @php
                        $normalized   = str_replace(',', '', $card['value']);
                        $numericValue = preg_replace('/[^0-9.]/', '', $normalized);
                        $suffixValue  = str_replace($numericValue, '', $normalized);
                    @endphp
                <div class="group rounded-2xl border border-white/12 p-5 shadow-2xl transition-all hover:-translate-y-1 hover:border-white/25"
                     data-aos="fade-up" data-aos-delay="{{ 150 + $index * 100 }}"
                     style="background: rgba(255,255,255,0.96);">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-extrabold uppercase tracking-widest text-gray-500">{{ $card['label'] }}</p>
                            <div class="mt-2 text-3xl font-black tracking-tight text-gray-950 counter-animate"
                                 data-counter="{{ $numericValue }}"
                                 data-suffix="{{ $suffixValue }}">0{{ $suffixValue }}</div>
                        </div>
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl"
                             style="background: {{ $card['bg'] }}; color: {{ $card['accent'] }};">
                            <i class="{{ $card['icon'] }}"></i>
                        </div>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-gray-600">{{ $card['detail'] }}</p>
                </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                @foreach(($trainingInsights['signals'] ?? []) as $index => $signal)
                <div class="rounded-2xl border border-white/15 p-5 text-white"
                     data-aos="fade-up" data-aos-delay="{{ 200 + $index * 100 }}"
                     style="background: rgba(255,255,255,0.09); backdrop-filter: blur(10px);">
                    <div class="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-sky-100">
                        <span class="h-2 w-2 rounded-full bg-sky-300"></span>
                        {{ $signal['title'] }}
                    </div>
                    <p class="text-sm leading-relaxed text-white/88">{{ $signal['text'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════
         UPCOMING TRAININGS  ← moved to top
    ═══════════════════════════════════════════ --}}
    @if(isset($upcomingTrainings) && $upcomingTrainings->count() > 0)
    <section class="py-14 bg-white" data-aos="fade-up">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-1 h-5 rounded-full" style="background: linear-gradient(180deg, #1D6FB8, #4FB3E8);"></div>
                        <span class="text-xs font-semibold text-primary-600 uppercase tracking-widest">Scheduled</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Upcoming Trainings</h2>
                    <p class="text-gray-500 text-sm mt-1">Global MOH training programs open for participation</p>
                </div>
                <a href="{{ url('analytics/dashboard') }}"
                   class="hidden md:inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                    View all <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($upcomingTrainings as $index => $training)
                <div class="group relative bg-white rounded-2xl border border-gray-200 p-5 hover:border-primary-300 hover:shadow-lg transition-all duration-200 overflow-hidden"
                     data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
                    <div class="absolute top-0 left-0 right-0 h-0.5 rounded-t-2xl"
                         style="background: linear-gradient(90deg, #1D6FB8 0%, #4FB3E8 100%);"></div>
                    @if($training->start_date)
                    <div class="inline-flex items-center gap-2 mb-3">
                        <div class="w-10 h-10 rounded-xl text-white flex flex-col items-center justify-center leading-none flex-shrink-0"
                             style="background: linear-gradient(135deg, #1D6FB8 0%, #4FB3E8 100%);">
                            <span class="text-sm font-extrabold">{{ $training->start_date->format('d') }}</span>
                            <span class="font-semibold uppercase" style="font-size: 8px; letter-spacing: 0.5px;">{{ $training->start_date->format('M') }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            <div class="font-semibold text-gray-700">{{ $training->start_date->format('M d, Y') }}</div>
                            @if($training->end_date)<div>to {{ $training->end_date->format('M d') }}</div>@endif
                        </div>
                    </div>
                    @endif
                    <h3 class="font-bold text-gray-900 text-sm leading-snug mb-3 group-hover:text-primary-700 transition-colors line-clamp-2">
                        {{ $training->title }}
                    </h3>
                    <div class="space-y-1.5">
                        @if($training->county)
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <i class="fas fa-map-marker-alt text-primary-400 w-3.5"></i>
                            <span class="truncate">{{ $training->county->name }}</span>
                        </div>
                        @endif
                        <div class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                             style="background: #EAF7FE; color: #2C478D;">
                            {{ ucfirst($training->status) }}
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════
         ONGOING MENTORSHIPS
    ═══════════════════════════════════════════ --}}
    @if(isset($ongoingMentorships) && $ongoingMentorships->count() > 0)
    <section class="py-14" data-aos="fade-up" style="background: linear-gradient(135deg, #EAF7FE 0%, #F0FDFF 100%);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="animate-pulse inline-block w-2 h-2 rounded-full bg-green-400" style="box-shadow:0 0 6px #4ade80;"></span>
                        <span class="text-xs font-semibold text-primary-700 uppercase tracking-widest">Live Now · {{ $ongoingMentorships->count() }}</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Ongoing Mentorships</h2>
                    <p class="text-gray-500 text-sm mt-1">Currently running at facilities across Kenya</p>
                </div>
                <a href="{{ url('analytics/dashboard') }}"
                   class="hidden md:inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                    View all <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($ongoingMentorships as $index => $mentorship)
                <div class="group bg-white rounded-2xl border border-primary-100 p-5 hover:border-primary-300 hover:shadow-lg transition-all duration-200 relative overflow-hidden"
                     data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
                    <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl" style="background: linear-gradient(90deg, #17579A, #1D6FB8);"></div>
                    <div class="flex items-start justify-between mb-3 mt-1">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                             style="background: linear-gradient(135deg, #17579A 0%, #1D6FB8 100%);">
                            <i class="fas fa-user-md text-white text-xs"></i>
                        </div>
                        <div class="text-right text-xs text-gray-500 leading-tight">
                            @if($mentorship->end_date)
                            <div class="font-semibold text-gray-700">ends {{ $mentorship->end_date->format('M d, Y') }}</div>
                            @endif
                            @if($mentorship->duration_label)
                            <div class="text-primary-600 mt-0.5">⏱ {{ $mentorship->duration_label }}</div>
                            @endif
                        </div>
                    </div>
                    <h3 class="font-bold text-gray-900 text-sm leading-snug mb-3 group-hover:text-primary-700 transition-colors line-clamp-2">
                        {{ $mentorship->title }}
                    </h3>
                    <div class="space-y-1.5">
                        @if($mentorship->facility)
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <i class="fas fa-hospital text-primary-400 w-3.5"></i>
                            <span class="truncate">{{ $mentorship->facility->name }}</span>
                        </div>
                        @elseif($mentorship->county)
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <i class="fas fa-map-marker-alt text-primary-400 w-3.5"></i>
                            <span class="truncate">{{ $mentorship->county->name }}</span>
                        </div>
                        @endif
                        <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold"
                             style="background: #EAF7FE; color: #1D6FB8;">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-400 inline-block"></span>
                            Ongoing
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════
         UPCOMING MENTORSHIPS
    ═══════════════════════════════════════════ --}}
    @if(isset($upcomingMentorships) && $upcomingMentorships->count() > 0)
    <section class="py-14" data-aos="fade-up" style="background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <i class="fas fa-calendar-alt text-xs" style="color:#15803d;"></i>
                        <span class="text-xs font-semibold uppercase tracking-widest" style="color:#15803d;">Coming Up · {{ $upcomingMentorships->count() }}</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Upcoming Mentorships</h2>
                    <p class="text-gray-500 text-sm mt-1">Facility mentorships scheduled to begin soon</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($upcomingMentorships as $index => $mentorship)
                <div class="group bg-white rounded-2xl border border-green-100 p-5 hover:border-green-300 hover:shadow-lg transition-all duration-200 relative overflow-hidden"
                     data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
                    <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl" style="background: linear-gradient(90deg, #15803d, #22c55e);"></div>
                    <div class="flex items-start justify-between mb-3 mt-1">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                             style="background: linear-gradient(135deg, #15803d 0%, #22c55e 100%);">
                            <i class="fas fa-calendar-check text-white text-xs"></i>
                        </div>
                        <div class="text-right text-xs text-gray-500 leading-tight">
                            @if($mentorship->start_date)
                            <div class="font-semibold text-gray-700">starts {{ $mentorship->start_date->format('M d, Y') }}</div>
                            @endif
                            @if($mentorship->duration_label)
                            <div class="mt-0.5" style="color:#15803d;">⏱ {{ $mentorship->duration_label }}</div>
                            @endif
                        </div>
                    </div>
                    <h3 class="font-bold text-gray-900 text-sm leading-snug mb-3 group-hover:text-green-700 transition-colors line-clamp-2">
                        {{ $mentorship->title }}
                    </h3>
                    <div class="space-y-1.5">
                        @if($mentorship->facility)
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <i class="fas fa-hospital text-green-400 w-3.5"></i>
                            <span class="truncate">{{ $mentorship->facility->name }}</span>
                        </div>
                        @elseif($mentorship->county)
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <i class="fas fa-map-marker-alt text-green-400 w-3.5"></i>
                            <span class="truncate">{{ $mentorship->county->name }}</span>
                        </div>
                        @endif
                        <div class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                             style="background: #DCFCE7; color: #15803d;">
                            Upcoming
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════
         RECENTLY CLOSED MENTORSHIPS
    ═══════════════════════════════════════════ --}}
    {{-- ═══════════════════════════════════════════
         RECENTLY CLOSED MENTORSHIPS
    ═══════════════════════════════════════════ --}}
    @if(isset($closedMentorships) && $closedMentorships->count() > 0)
    <section class="py-10" data-aos="fade-up" style="background: linear-gradient(135deg, #F9FAFB 0%, #F3F4F6 100%);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <i class="fas fa-check-circle text-xs text-gray-400"></i>
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Recently Closed · {{ $closedMentorships->count() }}</span>
                    </div>
                    <h2 class="text-xl md:text-2xl font-extrabold text-gray-500">Completed Mentorships</h2>
                    <p class="text-gray-500 text-sm mt-1">Programs that wrapped up in the last 30 days</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($closedMentorships as $index => $mentorship)
                <div class="bg-white rounded-2xl border border-gray-100 p-5 relative overflow-hidden"
                     data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
                    <div class="absolute top-0 left-0 right-0 h-1 rounded-t-2xl bg-gray-300"></div>
                    <div class="flex items-start justify-between mb-3 mt-1">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 bg-gray-200 opacity-75">
                            <i class="fas fa-user-md text-gray-400 text-xs"></i>
                        </div>
                        <div class="text-right text-xs text-gray-400 leading-tight">
                            @if($mentorship->end_date)
                            <div class="font-semibold">ended {{ $mentorship->end_date->format('M d, Y') }}</div>
                            @endif
                            @if($mentorship->duration_label)
                            <div class="mt-0.5">⏱ {{ $mentorship->duration_label }}</div>
                            @endif
                        </div>
                    </div>
                    <h3 class="font-semibold text-gray-500 text-sm leading-snug mb-3 line-clamp-2 opacity-75">
                        {{ $mentorship->title }}
                    </h3>
                    <div class="space-y-1.5">
                        @if($mentorship->facility)
                        <div class="flex items-center gap-1.5 text-xs text-gray-400">
                            <i class="fas fa-hospital text-gray-300 w-3.5"></i>
                            <span class="truncate">{{ $mentorship->facility->name }}</span>
                        </div>
                        @elseif($mentorship->county)
                        <div class="flex items-center gap-1.5 text-xs text-gray-400">
                            <i class="fas fa-map-marker-alt text-gray-300 w-3.5"></i>
                            <span class="truncate">{{ $mentorship->county->name }}</span>
                        </div>
                        @endif
                        <div class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-400">
                            ✓ Closed
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════
         FEATURED RESOURCES
    ═══════════════════════════════════════════ --}}
    @if($featuredResources->count() > 0)
    <section class="py-14 bg-gray-50" data-aos="fade-up">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-end justify-between mb-8">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-1 h-5 rounded-full" style="background: linear-gradient(180deg, #1D6FB8, #4FB3E8);"></div>
                        <span class="text-xs font-semibold text-primary-600 uppercase tracking-widest">Curated</span>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">Featured Resources</h2>
                    <p class="text-gray-500 text-sm mt-1">Hand-picked clinical materials our community values most</p>
                </div>
                <a href="{{ route('resources.index', ['featured' => 1]) }}"
                   class="hidden md:inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                    View all <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($featuredResources as $index => $resource)
                    <div data-aos="fade-up" data-aos-delay="{{ $index * 100 }}">
                        @include('components.resource-card', ['resource' => $resource, 'featured' => true])
                    </div>
                @endforeach
            </div>
            <div class="text-center mt-8 md:hidden">
                <a href="{{ route('resources.index', ['featured' => 1]) }}"
                   class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-white text-sm font-semibold shadow-md"
                   style="background: linear-gradient(135deg, #1D6FB8 0%, #4FB3E8 100%);">
                    <i class="fas fa-star"></i>View All Featured
                </a>
            </div>
        </div>
    </section>
    @endif

    {{-- ═══════════════════════════════════════════
         CTA BANNER
    ═══════════════════════════════════════════ --}}
    <section class="py-16 relative overflow-hidden" data-aos="zoom-in" data-aos-duration="800" style="background: linear-gradient(135deg, #1B2E5E 0%, #1D6FB8 100%);">
        <div class="absolute inset-0 opacity-[0.04]">
            <svg width="100%" height="100%"><pattern id="dots" x="0" y="0" width="40" height="40" patternUnits="userSpaceOnUse"><circle cx="20" cy="20" r="1.5" fill="white"/></pattern><rect width="100%" height="100%" fill="url(#dots)"/></svg>
        </div>
        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div>
                <h2 class="text-2xl font-extrabold text-white mb-4">Ready to Start Learning?</h2>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    @guest
                    <a href="{{ url('register') }}"
                       class="inline-flex items-center justify-center gap-2 px-7 py-3.5 bg-white text-primary-700 rounded-xl font-bold text-sm hover:bg-gray-100 transition-all shadow-lg">
                        <i class="fas fa-user-plus"></i>Get Started Free
                    </a>
                    @endguest
                    <a href="{{ route('resources.index') }}"
                       class="inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl font-bold text-sm text-white border-2 border-white/30 hover:border-white/60 transition-all"
                       style="background: rgba(255,255,255,0.12);">
                        <i class="fas fa-book-open"></i>Explore Resources
                    </a>
                    <a href="{{ url('analytics/dashboard') }}"
                       class="inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl font-bold text-sm text-white border-2 border-white/30 hover:border-white/60 transition-all"
                       style="background: rgba(255,255,255,0.12);">
                        <i class="fas fa-map"></i>View Training Map
                    </a>
                </div>
                <div class="flex items-center justify-center gap-8 mt-8 text-sky-200 text-xs">
                    <div class="flex items-center gap-1.5"><i class="fas fa-shield-alt text-sky-300"></i>Secure Platform</div>
                    <div class="flex items-center gap-1.5"><i class="fas fa-mobile-alt text-sky-300"></i>Mobile Ready</div>
                    <div class="flex items-center gap-1.5"><i class="fas fa-cloud-download-alt text-sky-300"></i>Offline Access</div>
                </div>
            </div>
        </div>
    </section>

</div>

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
<style>
    .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .scrollbar-none::-webkit-scrollbar { display: none; }
    .scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }

    /* ── 3D Pillar Cards — the three core mentorship pillars ────────────── */
    .pillar-grid { perspective: 1800px; }
    .pillar-card {
        transform-style: preserve-3d;
        transform: perspective(1200px) rotateX(7deg) translateZ(0);
        box-shadow:
            inset 0 1.5px 0 rgba(255,255,255,.35),
            inset 0 -36px 44px -28px rgba(0,0,0,.4),
            0 2px 0 rgba(0,0,0,.12),
            0 16px 20px -10px var(--pillar-shadow, rgba(0,0,0,.35)),
            0 32px 46px -18px var(--pillar-shadow-soft, rgba(0,0,0,.25));
        transition: transform .45s cubic-bezier(.2,.8,.2,1), box-shadow .45s cubic-bezier(.2,.8,.2,1);
        will-change: transform;
    }
    .pillar-card:hover {
        transform: perspective(1200px) rotateX(0deg) translateY(-12px) translateZ(24px) scale(1.015);
        box-shadow:
            inset 0 1.5px 0 rgba(255,255,255,.4),
            inset 0 -36px 44px -28px rgba(0,0,0,.35),
            0 3px 0 rgba(0,0,0,.12),
            0 26px 30px -12px var(--pillar-shadow, rgba(0,0,0,.4)),
            0 48px 64px -20px var(--pillar-shadow-soft, rgba(0,0,0,.3));
    }
    .pillar-sheen {
        position: absolute;
        inset: 0;
        background: linear-gradient(115deg, rgba(255,255,255,.4) 0%, rgba(255,255,255,.1) 20%, transparent 42%);
        mix-blend-mode: screen;
        pointer-events: none;
    }
    @media (prefers-reduced-motion: reduce) {
        .pillar-card, .pillar-card:hover { transition: none; transform: none; }
    }

    /* Analytics CTA pulse highlight */
    .analytics-cta-pulse {
        animation: analyticsPulse 2.5s ease-in-out infinite;
    }
    .analytics-cta-pulse:hover {
        animation-play-state: paused;
    }
    @keyframes analyticsPulse {
        0%, 100% { box-shadow: 0 0 18px rgba(111, 196, 239, 0.25); transform: scale(1); }
        50% { box-shadow: 0 0 32px rgba(111, 196, 239, 0.55); transform: scale(1.03); }
    }
    .analytics-cta-arrow {
        animation: arrowBounce 1.4s ease-in-out infinite;
    }
    .analytics-cta-pulse:hover .analytics-cta-arrow {
        animation-play-state: paused;
    }
    @keyframes arrowBounce {
        0%, 100% { transform: translateX(0); }
        50% { transform: translateX(4px); }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('homePage', () => ({ init() {} }));
    });

    document.addEventListener('DOMContentLoaded', () => {
        if (typeof AOS !== 'undefined') {
            AOS.init({
                duration: 650,
                easing: 'ease-out-cubic',
                once: true,
                offset: 50,
                disable: window.matchMedia('(prefers-reduced-motion: reduce)').matches
            });
        }

        initHomeCounters();
    });

    function initHomeCounters() {
        const counters = document.querySelectorAll('.counter-animate');
        if (!counters.length) return;

        const formatNumber = (value, decimals = 0) => {
            const num = parseFloat(value) || 0;
            if (decimals > 0) {
                return num.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            }
            return Math.round(num).toLocaleString();
        };

        const animate = (el) => {
            if (el.dataset.counterAnimated === 'true') return;
            el.dataset.counterAnimated = 'true';

            const target = parseFloat(el.dataset.counter) || 0;
            const suffix = el.dataset.suffix || '';
            const decimals = parseInt(el.dataset.decimals || '0', 10);
            const duration = parseInt(el.dataset.counterDuration || '1500', 10);
            const start = performance.now();

            const step = (now) => {
                const elapsed = now - start;
                const progress = Math.min(elapsed / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                const current = target * eased;
                el.textContent = formatNumber(current, decimals) + suffix;
                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    el.textContent = formatNumber(target, decimals) + suffix;
                }
            };

            requestAnimationFrame(step);
        };

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animate(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });
            counters.forEach(counter => observer.observe(counter));
        } else {
            counters.forEach(counter => animate(counter));
        }
    }
</script>
@endpush
@endsection

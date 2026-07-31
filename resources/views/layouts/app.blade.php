<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Resource Center') - {{ config('app.name') }}</title>
        <meta name="description" content="@yield('meta_description', 'Discover valuable resources, tools, and learning materials.')"/>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Lexend:wght@500;600;700;800&display=swap" rel="stylesheet">

        <!-- Icons -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <link rel="stylesheet" href="{{ asset('css/map.css') }}">

        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            'sans': ['Inter', 'system-ui', 'sans-serif'],
                            'display': ['Lexend', 'Inter', 'system-ui', 'sans-serif'],
                        },
                        colors: {
                            /* MoH Kenya clinical blue — sampled from the official EmONC
                               manual cover (sky blue to navy gradient). */
                            primary: {
                                50:  '#EAF7FE',
                                100: '#CFEEFC',
                                200: '#9EDDFA',
                                300: '#6FC4EF',
                                400: '#4FB3E8',
                                500: '#2E93D6',
                                600: '#1D6FB8',
                                700: '#17579A',
                                800: '#2C478D',
                                900: '#1B2E5E',
                            },
                            /* Programme pillar accents, sampled/derived from the real
                               mentorship manual covers. */
                            pillar: {
                                maternal: '#C81E70',
                                newborn:  '#A855C8',
                                child:    '#7DB83A',
                            }
                        }
                    }
                }
            }
        </script>

        <style>
            .nav-link {
                @apply relative text-gray-600 hover:text-primary-600 px-3 py-2 rounded-md text-sm font-medium transition-all duration-200;
            }
            .nav-link::after {
                content: '';
                @apply absolute bottom-1 left-1/2 w-0 h-0.5 bg-primary-500 rounded-full transition-all duration-200;
                transform: translateX(-50%);
            }
            .nav-link:hover,
            .nav-link:focus-visible {
                @apply text-primary-600 -translate-y-0.5 outline-none;
            }
            .nav-link:hover::after,
            .nav-link:focus-visible::after {
                @apply w-4/5;
            }
            .nav-link.active {
                @apply text-primary-600 bg-primary-50 font-semibold;
            }
            .nav-link.active::after {
                @apply w-4/5;
            }
            .mobile-nav-link {
                @apply flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-primary-50 hover:text-primary-700 rounded-xl text-base font-medium transition-all duration-200;
            }
            .mobile-nav-link:hover,
            .mobile-nav-link:focus-visible {
                @apply translate-x-1 outline-none;
            }
            .mobile-nav-link.active {
                @apply bg-primary-50 text-primary-700 font-semibold;
            }
            .mobile-nav-animate {
                @apply transition-transform duration-200;
            }
            .mobile-nav-animate:hover,
            .mobile-nav-animate:focus-visible {
                @apply translate-x-1 outline-none;
            }
            /* Footer links subtle lift */
            footer ul a {
                @apply inline-block transition-all duration-200;
            }
            footer ul a:hover,
            footer ul a:focus-visible {
                @apply translate-x-0.5 text-primary-400 outline-none;
            }
            /* iOS safe area */
            .safe-area-inset-top    { padding-top: env(safe-area-inset-top, 0px); }
            .safe-area-inset-bottom { padding-bottom: env(safe-area-inset-bottom, 0px); }
        </style>

        @stack('styles')
    </head>

    <body class="bg-gray-50 font-sans antialiased">

        <!-- ═══ NAVIGATION ═══ -->
        <nav x-data="{ mobileOpen: false }"
             class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm safe-area-inset-top"
             style="padding-top: env(safe-area-inset-top, 0px);">

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">

                    <!-- Logo -->
                    <div class="flex items-center flex-shrink-0">
                        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                                 style="background: linear-gradient(135deg, #1D6FB8 0%, #4FB3E8 100%);">
                                <i class="fas fa-stethoscope text-white text-sm"></i>
                            </div>
                            <span class="text-lg font-bold text-gray-900 leading-tight">
                                <span class="text-primary-600">MNCH</span> Kenya
                            </span>
                        </a>
                    </div>

                    <!-- Desktop Nav -->
                    <div class="hidden md:flex items-center gap-4">
                        <a href="{{ route('home') }}"
                           class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
                            <i class="fas fa-home mr-1.5 text-xs"></i>Home
                        </a>
                        <a href="{{ route('resources.index') }}"
                           class="nav-link {{ request()->routeIs('resources.*') ? 'active' : '' }}">
                            <i class="fas fa-book-open mr-1.5 text-xs"></i>Resources
                        </a>
                        <a href="{{ route('categories.index') }}"
                           class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                            <i class="fas fa-th-large mr-1.5 text-xs"></i>Categories
                        </a>
                        <a href="{{ url('analytics/dashboard') }}"
                           class="nav-link {{ request()->is('analytics*') ? 'active' : '' }}">
                            <i class="fas fa-chart-bar mr-1.5 text-xs"></i>Dashboard
                        </a>
                    </div>

                    <!-- Search (desktop lg+) -->
                    <div class="hidden lg:block flex-1 max-w-xs mx-6">
                        <form action="{{ route('resources.search') }}" method="GET">
                            <div class="relative">
                                <input type="text" name="q" value="{{ request('q') }}"
                                       placeholder="Search resources…"
                                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary-400 focus:border-transparent outline-none transition-all">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                            </div>
                        </form>
                    </div>

                    <!-- User menu + hamburger -->
                    <div class="flex items-center gap-3">
                        @auth
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open"
                                        class="flex items-center gap-2 rounded-xl px-2 py-1.5 hover:bg-gray-100 transition-colors">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold"
                                         style="background: linear-gradient(135deg, #1D6FB8 0%, #4FB3E8 100%);">
                                        {{ strtoupper(substr(auth()->user()->full_name, 0, 1)) }}
                                    </div>
                                    <i class="fas fa-chevron-down text-xs text-gray-500 hidden sm:block"></i>
                                </button>
                                <div x-show="open" @click.away="open = false"
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100"
                                     x-transition:leave-end="opacity-0 scale-95"
                                     class="absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 origin-top-right">
                                    <div class="px-4 py-2 border-b border-gray-100 mb-1">
                                        <p class="text-sm font-semibold text-gray-900 truncate">{{ auth()->user()->full_name }}</p>
                                        <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                                    </div>
                                    <a href="{{ url('/admin') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
                                        <i class="fas fa-cog w-4 text-gray-400"></i>Admin Panel
                                    </a>
                                    <a href="#" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">
                                        <i class="fas fa-bookmark w-4 text-gray-400"></i>Bookmarks
                                    </a>
                                    <div class="border-t border-gray-100 my-1"></div>
                                    <form method="POST" action="{{ url('admin/logout') }}">
                                        @csrf
                                        <button type="submit" class="flex items-center gap-2.5 w-full px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">
                                            <i class="fas fa-sign-out-alt w-4"></i>Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <a href="{{ url('admin/login') }}"
                               class="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-primary-600 border border-primary-200 rounded-xl hover:bg-primary-50 transition-colors">
                                <i class="fas fa-sign-in-alt text-xs"></i>Sign In
                            </a>
                        @endauth

                        <!-- Hamburger (mobile) -->
                        <button @click="mobileOpen = true"
                                class="md:hidden p-2 rounded-xl text-gray-600 hover:bg-gray-100 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Mobile Nav Drawer ── -->
            <div x-show="mobileOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-[60] md:hidden"
                 style="display: none;">
                <!-- Backdrop -->
                <div @click="mobileOpen = false" class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"></div>

                <!-- Drawer Panel -->
                <div x-show="mobileOpen"
                     x-transition:enter="transition ease-out duration-250"
                     x-transition:enter-start="translate-x-full"
                     x-transition:enter-end="translate-x-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="translate-x-0"
                     x-transition:leave-end="translate-x-full"
                     class="absolute right-0 top-0 bottom-0 w-80 bg-white overflow-y-auto flex flex-col"
                     style="padding-top: env(safe-area-inset-top, 0px); padding-bottom: env(safe-area-inset-bottom, 16px);">

                    <!-- Drawer header -->
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center"
                                 style="background: linear-gradient(135deg, #1D6FB8 0%, #4FB3E8 100%);">
                                <i class="fas fa-stethoscope text-white text-xs"></i>
                            </div>
                            <span class="font-bold text-gray-900 text-sm">MNCH Kenya</span>
                        </div>
                        <button @click="mobileOpen = false"
                                class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Mobile search -->
                    <div class="px-4 py-3 border-b border-gray-100">
                        <form action="{{ route('resources.search') }}" method="GET">
                            <div class="relative">
                                <input type="text" name="q" value="{{ request('q') }}"
                                       placeholder="Search resources…"
                                       class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary-400 focus:border-transparent outline-none">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                            </div>
                        </form>
                    </div>

                    <!-- Nav links -->
                    @php
                        $navHome       = request()->routeIs('home');
                        $navResources  = request()->routeIs('resources.*');
                        $navCategories = request()->routeIs('categories.*');
                    @endphp
                    <nav class="flex-1 px-4 py-4 space-y-1.5">

                        <p class="px-2 mb-3 text-xs font-bold text-gray-400 uppercase tracking-widest">Menu</p>

                        {{-- Home --}}
                        <a href="{{ route('home') }}" @click="mobileOpen = false"
                           class="mobile-nav-animate flex items-center gap-4 px-3 py-3 rounded-2xl transition-all duration-150 {{ $navHome ? 'bg-primary-50' : 'hover:bg-gray-50' }}">
                            <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm"
                                 style="{{ $navHome ? 'background: linear-gradient(135deg,#1D6FB8 0%,#4FB3E8 100%);' : 'background:#F3F4F6;' }}">
                                <i class="fas fa-home {{ $navHome ? 'text-white' : 'text-gray-500' }}" style="font-size:15px;"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-[15px] leading-none {{ $navHome ? 'text-primary-700' : 'text-gray-800' }}">Home</p>
                                <p class="text-xs text-gray-400 mt-0.5">Dashboard &amp; overview</p>
                            </div>
                            @if($navHome)
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#1D6FB8;"></span>
                            @endif
                        </a>

                        {{-- Resources --}}
                        <a href="{{ route('resources.index') }}" @click="mobileOpen = false"
                           class="mobile-nav-animate flex items-center gap-4 px-3 py-3 rounded-2xl transition-all duration-150 {{ $navResources ? 'bg-primary-50' : 'hover:bg-gray-50' }}">
                            <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm"
                                 style="{{ $navResources ? 'background: linear-gradient(135deg,#1D6FB8 0%,#4FB3E8 100%);' : 'background:#F3F4F6;' }}">
                                <i class="fas fa-book-open {{ $navResources ? 'text-white' : 'text-gray-500' }}" style="font-size:15px;"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-[15px] leading-none {{ $navResources ? 'text-primary-700' : 'text-gray-800' }}">Resources</p>
                                <p class="text-xs text-gray-400 mt-0.5">Guidelines &amp; materials</p>
                            </div>
                            @if($navResources)
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#1D6FB8;"></span>
                            @endif
                        </a>

                        {{-- Categories --}}
                        <a href="{{ route('categories.index') }}" @click="mobileOpen = false"
                           class="mobile-nav-animate flex items-center gap-4 px-3 py-3 rounded-2xl transition-all duration-150 {{ $navCategories ? 'bg-primary-50' : 'hover:bg-gray-50' }}">
                            <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm"
                                 style="{{ $navCategories ? 'background: linear-gradient(135deg,#1D6FB8 0%,#4FB3E8 100%);' : 'background:#F3F4F6;' }}">
                                <i class="fas fa-th-large {{ $navCategories ? 'text-white' : 'text-gray-500' }}" style="font-size:15px;"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-[15px] leading-none {{ $navCategories ? 'text-primary-700' : 'text-gray-800' }}">Categories</p>
                                <p class="text-xs text-gray-400 mt-0.5">Browse by topic</p>
                            </div>
                            @if($navCategories)
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#1D6FB8;"></span>
                            @endif
                        </a>

                        {{-- Dashboard --}}
                        <a href="{{ url('analytics/dashboard') }}" @click="mobileOpen = false"
                           class="mobile-nav-animate flex items-center gap-4 px-3 py-3 rounded-2xl transition-all duration-150 {{ request()->is('analytics*') ? 'bg-primary-50' : 'hover:bg-gray-50' }}">
                            <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0 shadow-sm"
                                 style="{{ request()->is('analytics*') ? 'background: linear-gradient(135deg,#1D6FB8 0%,#4FB3E8 100%);' : 'background:#F3F4F6;' }}">
                                <i class="fas fa-chart-bar {{ request()->is('analytics*') ? 'text-white' : 'text-gray-500' }}" style="font-size:15px;"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-[15px] leading-none {{ request()->is('analytics*') ? 'text-primary-700' : 'text-gray-800' }}">Dashboard</p>
                                <p class="text-xs text-gray-400 mt-0.5">Analytics &amp; reports</p>
                            </div>
                            @if(request()->is('analytics*'))
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#1D6FB8;"></span>
                            @endif
                        </a>

                    </nav>

                    <!-- User section at bottom -->
                    <div class="border-t border-gray-100 px-4 py-3">
                        @auth
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                                     style="background: linear-gradient(135deg, #1D6FB8 0%, #4FB3E8 100%);">
                                    {{ strtoupper(substr(auth()->user()->full_name, 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ auth()->user()->full_name }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                                </div>
                            </div>
                            <a href="{{ url('/admin') }}"
                               class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-xl mb-1"
                               @click="mobileOpen = false">
                                <i class="fas fa-cog w-4 text-gray-400"></i>Admin Panel
                            </a>
                            <form method="POST" action="{{ url('admin/logout') }}">
                                @csrf
                                <button type="submit" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-xl">
                                    <i class="fas fa-sign-out-alt w-4"></i>Logout
                                </button>
                            </form>
                        @else
                            <a href="{{ url('admin/login') }}"
                               class="flex items-center justify-center gap-2 w-full px-4 py-3 text-sm font-semibold text-white rounded-xl transition-all"
                               style="background: linear-gradient(135deg, #1D6FB8 0%, #4FB3E8 100%);"
                               @click="mobileOpen = false">
                                <i class="fas fa-sign-in-alt"></i>Sign In
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <!-- Breadcrumbs -->
        @if (!request()->routeIs('home'))
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="inline-flex items-center space-x-1 md:space-x-3">
                        <li class="inline-flex items-center">
                            <a href="{{ route('home') }}" class="text-gray-400 hover:text-primary-600 transition-colors">
                                <i class="fas fa-home text-sm"></i>
                            </a>
                        </li>
                        @yield('breadcrumbs')
                    </ol>
                </nav>
            </div>
        </div>
        @endif

        <!-- Page Header -->
        @hasSection('page_header')
        <div class="bg-white border-b border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                @yield('page_header')
            </div>
        </div>
        @endif

        <!-- Flash Messages -->
        @if (session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl relative max-w-7xl mx-auto mt-4 flex items-start gap-3" role="alert">
            <i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0"></i>
            <span>{{ session('success') }}</span>
            <button onclick="this.parentElement.style.display='none'" class="ml-auto text-green-400 hover:text-green-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        @endif

        @if ($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl relative max-w-7xl mx-auto mt-4" role="alert">
            <div class="flex items-center gap-2 mb-2">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <strong class="font-semibold">Please fix the following:</strong>
            </div>
            <ul class="space-y-1 ml-6">
                @foreach ($errors->all() as $error)
                    <li class="text-sm list-disc">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- Main Content -->
        <main class="min-h-screen">
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="bg-gray-900 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">
                    <!-- About -->
                    <div class="md:col-span-1">
                        <div class="flex items-center gap-2.5 mb-4">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center"
                                 style="background: linear-gradient(135deg, #1D6FB8 0%, #4FB3E8 100%);">
                                <i class="fas fa-stethoscope text-white text-sm"></i>
                            </div>
                            <span class="text-lg font-bold"><span class="text-primary-400">MNCH</span> Kenya</span>
                        </div>
                        <p class="text-gray-400 text-sm mb-4 leading-relaxed">
                            Kenya's comprehensive platform for maternal, neonatal, and child health training resources.
                        </p>
                        <div class="flex gap-3">
                            <a href="#" class="w-8 h-8 rounded-lg bg-gray-800 hover:bg-primary-700 flex items-center justify-center text-gray-400 hover:text-white transition-all">
                                <i class="fab fa-twitter text-sm"></i>
                            </a>
                            <a href="#" class="w-8 h-8 rounded-lg bg-gray-800 hover:bg-primary-700 flex items-center justify-center text-gray-400 hover:text-white transition-all">
                                <i class="fab fa-linkedin text-sm"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-200 uppercase tracking-wider mb-4">Quick Links</h3>
                        <ul class="space-y-2.5">
                            <li><a href="{{ route('resources.index') }}" class="text-gray-400 hover:text-primary-400 text-sm transition-colors">All Resources</a></li>
                            <li><a href="{{ route('categories.index') }}" class="text-gray-400 hover:text-primary-400 text-sm transition-colors">Categories</a></li>
                            <li><a href="{{ route('resources.browse') }}" class="text-gray-400 hover:text-primary-400 text-sm transition-colors">Browse</a></li>
                            <li><a href="{{ url('analytics/dashboard') }}" class="text-gray-400 hover:text-primary-400 text-sm transition-colors">Analytics Map</a></li>
                        </ul>
                    </div>

                    <!-- Categories -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-200 uppercase tracking-wider mb-4">Top Categories</h3>
                        <ul class="space-y-2.5">
                            @foreach (\App\Models\ResourceCategory::active()->parent()->withCount('resources')->orderByDesc('resources_count')->limit(5)->get() as $category)
                            <li>
                                <a href="{{ route('resources.category', $category->slug) }}" class="text-gray-400 hover:text-primary-400 text-sm transition-colors">
                                    {{ $category->name }}
                                    <span class="text-gray-600 text-xs">({{ $category->resources_count }})</span>
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>

                    <!-- Contact -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-200 uppercase tracking-wider mb-4">Contact</h3>
                        <ul class="space-y-3 text-sm text-gray-400">
                            <li class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-gray-800 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-envelope text-primary-400 text-xs"></i>
                                </div>
                                mnch@health.go.ke
                            </li>
                            <li class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-gray-800 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-phone text-primary-400 text-xs"></i>
                                </div>
                                +254 700 000 000
                            </li>
                            <li class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-gray-800 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-map-marker-alt text-primary-400 text-xs"></i>
                                </div>
                                Nairobi, Kenya
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="border-t border-gray-800 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-gray-500">
                    <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                    <p class="flex items-center gap-1.5">
                        <i class="fas fa-heart text-primary-500 text-xs"></i>
                        Strengthening Healthcare in Kenya
                    </p>
                </div>
            </div>
        </footer>

        <!-- Alpine.js -->
        <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

        @stack('scripts')
    </body>

</html>

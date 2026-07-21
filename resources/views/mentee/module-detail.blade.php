<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $classModule->programModule?->name ?? 'Module' }} — {{ $class->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: {
                        brand: { 50:'#eef2ff',100:'#e0e7ff',200:'#c7d2fe',400:'#818cf8',500:'#6366f1',600:'#4f46e5',700:'#4338ca' }
                    }
                }
            }
        }
    </script>
    <script>(function(){const s=localStorage.getItem('theme'),d=window.matchMedia('(prefers-color-scheme: dark)').matches;if(s==='dark'||(!s&&d))document.documentElement.classList.add('dark');})()</script>
    <style>
        *:focus-visible{outline:2px solid #4f46e5;outline-offset:2px}
        @keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:.4}}
        .pulse-dot{animation:pulse-dot 1.6s ease-in-out infinite}
        @keyframes fadeSlide{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
        .fade-slide{animation:fadeSlide .35s ease both}
        [x-cloak]{display:none!important}

        /* 3-pane grid */
        .module-grid{display:grid;gap:24px;grid-template-columns:1fr}
        @media(min-width:1024px){
            .module-grid{grid-template-columns:220px 1fr}
            .col-left{order:0}
            .col-main{order:1}
            .col-right{order:2;grid-column:1/-1}
        }
        @media(min-width:1280px){
            .module-grid{grid-template-columns:220px 1fr 240px}
            .col-right{order:2;grid-column:auto}
        }

        /* Sticky sidebars on large screens */
        @media(min-width:1024px){
            .sticky-side{position:sticky;top:80px;align-self:start}
        }

        /* Progress stepper */
        .step-line{flex:1;height:2px;min-width:8px}

        /* Section card */
        .section-card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;overflow:hidden}
        .dark .section-card{background:#0f172a;border-color:#1e293b}
        .card-stripe{height:3px}

        /* Info row in sidebars */
        .info-row{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid #f1f5f9}
        .dark .info-row{border-bottom-color:#1e293b}
        .info-row:last-child{border-bottom:none}
        .info-label{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;min-width:60px;flex-shrink:0;padding-top:1px}
        .info-value{font-size:12px;font-weight:600;color:#1e293b;line-height:1.4}
        .dark .info-value{color:#e2e8f0}

        /* Score ring */
        .score-ring{position:relative;width:72px;height:72px;flex-shrink:0}
        .score-ring svg{transform:rotate(-90deg)}
        .score-ring .ring-num{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}

        /* Quiz-taking lockdown — no copy/select/drag anywhere inside the live quiz modal */
        .quiz-guard,.quiz-guard *{
            -webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none;
            -webkit-touch-callout:none;-webkit-user-drag:none;
        }
    </style>
</head>
<body class="min-h-full bg-slate-50 dark:bg-slate-950 font-sans antialiased text-slate-900 dark:text-slate-100">

{{-- ── Sticky top bar ────────────────────────────────────────────────────── --}}
<header class="sticky top-0 z-20 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800">
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 h-14 flex items-center justify-between gap-4">
        <a href="{{ route('mentee.class.progress', $class->id) }}"
           class="flex items-center gap-2 text-sm font-semibold text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 transition-colors">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            {{ $class->name }}
        </a>
        <span class="text-xs font-medium text-slate-400 dark:text-slate-500 truncate hidden sm:block">
            {{ $classModule->programModule?->name ?? 'Module Detail' }}
        </span>
        <button onclick="document.documentElement.classList.toggle('dark')" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            <svg class="w-4 h-4 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        </button>
    </div>
</header>

<main class="max-w-[1400px] mx-auto px-4 sm:px-6 py-6">

    {{-- ── Flash messages ───────────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="fade-slide mb-4 flex items-start gap-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 px-4 py-3">
            <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L9.53 12.22a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
            <p class="text-sm font-medium text-emerald-800 dark:text-emerald-200">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="fade-slide mb-4 flex items-start gap-3 rounded-xl bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 px-4 py-3">
            <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
            <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ session('error') }}</p>
        </div>
    @endif
    @if(session('info'))
        <div class="fade-slide mb-4 flex items-start gap-3 rounded-xl bg-blue-50 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-800 px-4 py-3">
            <svg class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="text-sm font-medium text-blue-800 dark:text-blue-200">{{ session('info') }}</p>
        </div>
    @endif

    {{-- ══ 3-PANE GRID ══════════════════════════════════════════════════════════ --}}
    <div class="module-grid">

        {{-- ── LEFT PANE: Mentee + Mentorship info ──────────────────────────── --}}
        <aside class="col-left sticky-side space-y-4">

            {{-- Mentee card --}}
            <div class="section-card">
                <div class="card-stripe" style="background:linear-gradient(90deg,#4f46e5,#7c3aed)"></div>
                <div class="p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#4f46e5,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#fff;flex-shrink:0">
                            @php
                                $nm = $mentee->full_name ?? $mentee->name ?? '';
                                $parts = explode(' ', trim($nm));
                                $initials = strtoupper(substr($parts[0] ?? 'M', 0, 1)) . strtoupper(substr(end($parts), 0, 1));
                            @endphp
                            {{ $initials }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Mentee</p>
                            <p class="text-sm font-bold text-slate-900 dark:text-white leading-tight truncate">{{ $nm ?: 'Unknown' }}</p>
                        </div>
                    </div>
                    <div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value" style="word-break:break-all;font-size:11px">{{ $mentee->email }}</span>
                        </div>
                        @if($mentee->cadre)
                        <div class="info-row">
                            <span class="info-label">Cadre</span>
                            <span class="info-value">{{ $mentee->cadre->name }}</span>
                        </div>
                        @endif
                        @if($mentee->facility)
                        <div class="info-row">
                            <span class="info-label">Facility</span>
                            <span class="info-value">{{ $mentee->facility->name }}</span>
                        </div>
                        @endif
                        @if($mentee->phone)
                        <div class="info-row">
                            <span class="info-label">Phone</span>
                            <span class="info-value">{{ $mentee->phone }}</span>
                        </div>
                        @endif
                        <div class="info-row">
                            <span class="info-label">Status</span>
                            <span class="info-value">
                                @php $ps = $participant->status; @endphp
                                <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:100px;font-size:10px;font-weight:700;background:{{ $ps==='enrolled'?'#fef9c3':'#dcfce7' }};color:{{ $ps==='enrolled'?'#854d0e':'#166534' }}">
                                    {{ ucfirst($ps) }}
                                </span>
                            </span>
                        </div>
                        @if($participant->enrolled_at)
                        <div class="info-row">
                            <span class="info-label">Enrolled</span>
                            <span class="info-value">{{ \Carbon\Carbon::parse($participant->enrolled_at)->format('d M Y') }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Mentorship card --}}
            <div class="section-card">
                <div class="card-stripe" style="background:linear-gradient(90deg,#0ea5e9,#6366f1)"></div>
                <div class="p-4">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Mentorship</p>
                    <div>
                        <div class="info-row">
                            <span class="info-label">Class</span>
                            <span class="info-value">{{ $class->name }}</span>
                        </div>
                        @if($class->training?->program)
                        <div class="info-row">
                            <span class="info-label">Program</span>
                            <span class="info-value">{{ $class->training->program->name }}</span>
                        </div>
                        @endif
                        @if($class->training?->mentor)
                        <div class="info-row">
                            <span class="info-label">Mentor</span>
                            <span class="info-value">{{ $class->training->mentor->full_name ?? $class->training->mentor->name }}</span>
                        </div>
                        @endif
                        @if($class->training?->facility)
                        <div class="info-row">
                            <span class="info-label">Site</span>
                            <span class="info-value">{{ $class->training->facility->name }}</span>
                        </div>
                        @endif
                        @if($class->start_date)
                        <div class="info-row">
                            <span class="info-label">Dates</span>
                            <span class="info-value">
                                {{ \Carbon\Carbon::parse($class->start_date)->format('d M Y') }}
                                @if($class->end_date) – {{ \Carbon\Carbon::parse($class->end_date)->format('d M Y') }} @endif
                            </span>
                        </div>
                        @endif
                        <div class="info-row">
                            <span class="info-label">Module</span>
                            <span class="info-value">
                                @php $mst = $classModule->status; @endphp
                                <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:100px;font-size:10px;font-weight:700;background:{{ $mst==='completed'?'#dcfce7':($mst==='in_progress'?'#fef3c7':'#f1f5f9') }};color:{{ $mst==='completed'?'#166534':($mst==='in_progress'?'#92400e':'#475569') }}">
                                    {{ ucfirst(str_replace('_',' ',$mst)) }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

        </aside>

        {{-- ── MIDDLE PANE: Main content ───────────────────────────────────── --}}
        <div class="col-main space-y-5 min-w-0">

            {{-- ── Hero header ──────────────────────────────────────────────── --}}
            <div class="section-card" style="border-radius:20px">
                {{-- Gradient band --}}
                <div style="background:linear-gradient(135deg,#1e3a8a 0%,#4f46e5 55%,#7c3aed 100%);padding:28px 28px 24px">
                    @if($classModule->programModule?->parent)
                        <p style="font-size:11px;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.1em;margin-bottom:4px">
                            {{ $classModule->programModule->parent->name }}
                        </p>
                    @endif
                    <h1 style="font-size:22px;font-weight:800;color:#fff;line-height:1.25;margin:0">
                        {{ $classModule->programModule?->name ?? 'Module' }}
                    </h1>
                    <p style="font-size:12px;color:rgba(255,255,255,.65);margin-top:6px">{{ $class->name }}</p>

                    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px">
                        @php
                            $progLabel = match($progress->status) {
                                'completed' => ['label'=>'Completed','bg'=>'rgba(52,211,153,.2)','color'=>'#6ee7b7'],
                                'in_progress' => ['label'=>'In Progress','bg'=>'rgba(251,191,36,.2)','color'=>'#fde68a'],
                                'exempted' => ['label'=>'Exempted','bg'=>'rgba(167,139,250,.2)','color'=>'#c4b5fd'],
                                default => ['label'=>'Not Started','bg'=>'rgba(255,255,255,.12)','color'=>'rgba(255,255,255,.65)'],
                            };
                        @endphp
                        <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:100px;font-size:11px;font-weight:700;background:{{ $progLabel['bg'] }};color:{{ $progLabel['color'] }}">
                            @if($progress->status === 'completed')
                                <svg style="width:12px;height:12px" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L9.53 12.22a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                            @elseif($progress->status === 'in_progress')
                                <span class="pulse-dot" style="width:7px;height:7px;border-radius:50%;background:currentColor;display:inline-block"></span>
                            @endif
                            {{ $progLabel['label'] }}
                        </span>
                        @if($isEmonc)
                            <span style="padding:4px 12px;border-radius:100px;font-size:11px;font-weight:700;background:rgba(255,255,255,.12);color:rgba(255,255,255,.7)">EmONC</span>
                        @endif
                    </div>
                </div>

                {{-- ── Progress stepper ────────────────────────────────────── --}}
                @php
                    $videoPassed = $progress->video_review_status === 'passed';
                    $steps = [];
                    // Attendance — always present, always done (they're here)
                    $steps[] = ['label'=>'Attended','done'=>true,'active'=>false,'color'=>'#10b981'];
                    // Pre-test — only if exists
                    if($preTestStatus['exists']) {
                        $steps[] = ['label'=>'Pre-test','done'=>$preTestStatus['completed'],'active'=>!$preTestStatus['completed'],'color'=>'#f59e0b'];
                    }
                    // Case scenario — only if content exists (no completion tracker, show as step if set)
                    if($caseScenarios->isNotEmpty()) {
                        $steps[] = ['label'=>'Scenarios','done'=>$preTestStatus['completed'],'active'=>$preTestStatus['completed'] && !$hasSubmittedVideo,'color'=>'#0ea5e9'];
                    }
                    // Hands-on video — EmONC only
                    if($isEmonc) {
                        $steps[] = ['label'=>'Video','done'=>$hasSubmittedVideo && $videoPassed,'active'=>$preTestStatus['completed'] && !($hasSubmittedVideo && $videoPassed),'color'=>'#8b5cf6'];
                    }
                    // Post-test — only if exists
                    if($postTestStatus['exists']) {
                        $steps[] = ['label'=>'Post-test','done'=>$postTestStatus['completed'],'active'=>$videoPassed && !$postTestStatus['completed'],'color'=>'#f43f5e'];
                    }
                    // Done
                    $steps[] = ['label'=>'Done','done'=>in_array($progress->status,['completed','exempted']),'active'=>false,'color'=>'#10b981'];

                    $totalDone = collect($steps)->where('done',true)->count();
                    $totalSteps = count($steps);
                    $pct = $totalSteps > 0 ? round(($totalDone/$totalSteps)*100) : 0;
                @endphp
                <div style="background:#fff;padding:18px 24px 20px" class="dark:bg-slate-900">
                    {{-- Bar --}}
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:12px">
                        <div style="flex:1;height:6px;border-radius:100px;background:#e2e8f0;overflow:hidden" class="dark:bg-slate-800">
                            <div style="width:{{ $pct }}%;height:100%;background:linear-gradient(90deg,#4f46e5,#10b981);border-radius:100px;transition:width .6s ease"></div>
                        </div>
                        <span style="font-size:12px;font-weight:800;color:#4f46e5;white-space:nowrap;min-width:36px;text-align:right">{{ $pct }}%</span>
                    </div>
                    {{-- Stepper --}}
                    <div style="display:flex;align-items:center">
                        @foreach($steps as $i => $step)
                            @if($i > 0)
                                <div class="step-line" style="background:{{ $steps[$i-1]['done'] ? $steps[$i-1]['color'] : '#e2e8f0' }};opacity:{{ $steps[$i-1]['done'] ? '1' : '.5' }}" class="dark:bg-slate-700"></div>
                            @endif
                            <div style="display:flex;flex-direction:column;align-items:center;gap:4px;flex-shrink:0">
                                <div style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;transition:all .2s;
                                    {{ $step['done'] ? 'background:'.$step['color'].';color:#fff;box-shadow:0 0 0 3px '.$step['color'].'22' :
                                       ($step['active'] ? 'background:#4f46e5;color:#fff;box-shadow:0 0 0 3px #4f46e510' :
                                       'background:#f1f5f9;color:#94a3b8') }}">
                                    @if($step['done'])
                                        <svg style="width:13px;height:13px" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        {{ $i + 1 }}
                                    @endif
                                </div>
                                <span style="font-size:9px;font-weight:700;white-space:nowrap;color:{{ $step['done'] ? $step['color'] : ($step['active'] ? '#4f46e5' : '#94a3b8') }}">{{ $step['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ── NO CONTENT BLOCK ────────────────────────────────────────── --}}
            @if(!$hasAnyContent)
                <div class="fade-slide section-card" style="border:2px dashed #cbd5e1">
                    <div style="padding:40px 32px;text-align:center">
                        <div style="width:56px;height:56px;border-radius:16px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                            <svg style="width:28px;height:28px;color:#94a3b8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <h3 style="font-size:16px;font-weight:800;color:#1e293b;margin-bottom:8px" class="dark:text-white">Module content not yet set up</h3>
                        <p style="font-size:13px;color:#64748b;max-width:380px;margin:0 auto 20px;line-height:1.6">
                            Your mentor hasn't added any content to this module yet — no introduction, pre/post tests, case scenarios, or activities have been configured for you.
                        </p>
                        <div style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:10px;background:linear-gradient(135deg,#eff6ff,#eef2ff);border:1px solid #bfdbfe">
                            <svg style="width:16px;height:16px;color:#1d4ed8;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            <span style="font-size:12px;font-weight:600;color:#1d4ed8">Contact your mentor to be enrolled in the module services</span>
                        </div>
                    </div>
                </div>
            @else

                {{-- ── Next action banner ───────────────────────────────────── --}}
                @if($isEmonc)
                    @php
                        if($progress->status === 'completed' || $progress->status === 'exempted') {
                            $naState='done'; $naLabel='Module complete — well done!'; $naAnchor=null;
                        } elseif($preTestStatus['exists'] && !$preTestStatus['completed']) {
                            $naState='warn'; $naLabel='Start the pre-test to unlock module content'; $naAnchor='pre-test';
                        } elseif(!$hasSubmittedVideo) {
                            $naState='warn'; $naLabel='Submit your hands-on video'; $naAnchor='submit-video';
                        } elseif(!$videoPassed) {
                            $naState='info'; $naLabel='Awaiting mentor to review your hands-on video'; $naAnchor=null;
                        } elseif($postTestStatus['exists'] && !$postTestStatus['completed']) {
                            $naState='action'; $naLabel='Take the post-test to complete this module'; $naAnchor='post-test';
                        } else {
                            $naState='info'; $naLabel='Awaiting mentor to finalise the module'; $naAnchor=null;
                        }
                    @endphp
                    @if($naState === 'done')
                        <div class="fade-slide flex items-center gap-3 rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/30 px-4 py-3">
                            <div style="width:32px;height:32px;border-radius:10px;background:#dcfce7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <svg style="width:16px;height:16px;color:#16a34a" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L9.53 12.22a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                            </div>
                            <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ $naLabel }}</p>
                        </div>
                    @elseif($naState === 'warn')
                        <div class="fade-slide flex items-center justify-between gap-3 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div style="width:32px;height:32px;border-radius:10px;background:#fef3c7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <svg style="width:15px;height:15px;color:#d97706" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-amber-700 dark:text-amber-300 uppercase tracking-wide">Next Step</p>
                                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">{{ $naLabel }}</p>
                                </div>
                            </div>
                            @if($naAnchor)<a href="#{{ $naAnchor }}" class="text-xs font-bold text-amber-700 hover:underline shrink-0">Go →</a>@endif
                        </div>
                    @elseif($naState === 'action')
                        <div class="fade-slide flex items-center justify-between gap-3 rounded-xl px-4 py-3 shadow-md" style="background:linear-gradient(135deg,#4f46e5,#7c3aed)">
                            <div class="flex items-center gap-3">
                                <div style="width:32px;height:32px;border-radius:10px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <svg style="width:15px;height:15px;color:#fff" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <p style="font-size:10px;font-weight:700;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.06em">Next Step</p>
                                    <p class="text-sm font-semibold text-white">{{ $naLabel }}</p>
                                </div>
                            </div>
                            @if($naAnchor)<a href="#{{ $naAnchor }}" style="font-size:12px;font-weight:700;color:rgba(255,255,255,.8);white-space:nowrap">Go →</a>@endif
                        </div>
                    @else
                        <div class="fade-slide flex items-center gap-3 rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30 px-4 py-3">
                            <span class="pulse-dot" style="width:10px;height:10px;border-radius:50%;background:#3b82f6;flex-shrink:0;display:inline-block"></span>
                            <p class="text-sm text-blue-700 dark:text-blue-300">{{ $naLabel }}</p>
                        </div>
                    @endif
                @endif

                {{-- ── I. Introduction to the Module ────────────────────────────── --}}
                @if($hasIntroContent)
                <div class="section-card">
                    <div class="card-stripe" style="background:#10b981"></div>
                    <div class="p-5 sm:p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div style="width:34px;height:34px;border-radius:10px;background:#d1fae5;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <svg style="width:16px;height:16px;color:#059669" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <h2 class="text-base font-bold text-slate-900 dark:text-white">I. Introduction to the Module</h2>
                        </div>
                        @if($classModule->programModule?->description)
                            <div class="prose prose-sm dark:prose-invert max-w-none">{!! Str::markdown($classModule->programModule->description) !!}</div>
                        @endif
                        @foreach($introductions as $intro)
                            <div class="{{ $loop->first && !$classModule->programModule?->description ? '' : 'mt-4 pt-4 border-t border-slate-100 dark:border-slate-800' }}">
                                <h3 class="font-semibold text-slate-900 dark:text-white mb-2 text-sm">{{ $intro->title }}</h3>
                                <div class="prose prose-sm dark:prose-invert max-w-none">{!! Str::markdown($intro->content) !!}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- ── II. Expected Learning Outcome ────────────────────────────── --}}
                @if($expectedLearningOutcomes->isNotEmpty())
                <div class="section-card">
                    <div class="card-stripe" style="background:#0ea5e9"></div>
                    <div class="p-5 sm:p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div style="width:34px;height:34px;border-radius:10px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <svg style="width:16px;height:16px;color:#0284c7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <h2 class="text-base font-bold text-slate-900 dark:text-white">II. Expected Learning Outcome</h2>
                        </div>
                        @foreach($expectedLearningOutcomes as $outcome)
                            <div class="prose prose-sm dark:prose-invert max-w-none {{ !$loop->first ? 'mt-4 pt-4 border-t border-slate-100 dark:border-slate-800' : '' }}">{!! Str::markdown($outcome->content) !!}</div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- ── III. Learning Objectives ─────────────────────────────────── --}}
                @if(!empty($objectives))
                <div class="section-card">
                    <div class="card-stripe" style="background:#6366f1"></div>
                    <div class="p-5 sm:p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div style="width:34px;height:34px;border-radius:10px;background:#e0e7ff;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <svg style="width:16px;height:16px;color:#4f46e5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <h2 class="text-base font-bold text-slate-900 dark:text-white">III. Learning Objectives</h2>
                        </div>
                        <ul class="space-y-2">
                            @foreach($objectives as $objective)
                                <li class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                                    <svg class="w-4 h-4 text-indigo-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L9.53 12.22a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                                    <span>{{ $objective }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                @endif

                {{-- ── IV. Module Workplan ──────────────────────────────────────── --}}
                @if(!empty($workplan))
                <div class="section-card">
                    <div class="card-stripe" style="background:#f97316"></div>
                    <div class="p-5 sm:p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div style="width:34px;height:34px;border-radius:10px;background:#ffedd5;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <svg style="width:16px;height:16px;color:#ea580c" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <h2 class="text-base font-bold text-slate-900 dark:text-white">IV. Module Workplan</h2>
                        </div>
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($workplan as $item)
                                <div class="flex items-center justify-between py-2 text-sm">
                                    <span class="text-slate-700 dark:text-slate-300">{{ $item['label'] ?? '' }}</span>
                                    <span class="font-semibold text-slate-500 dark:text-slate-400">{{ $item['duration'] ?? '' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                {{-- ── Equipment & Materials Needed (collapsible) ───────────────── --}}
                @if($moduleRubric && !empty($moduleRubric->equipment_supplies))
                <div class="section-card" x-data="{ open: false }">
                    <div class="card-stripe" style="background:#64748b"></div>
                    <button type="button" @click="open = !open" class="w-full flex items-center justify-between gap-3 p-5 sm:p-6 text-left">
                        <div class="flex items-center gap-3">
                            <div style="width:34px;height:34px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <svg style="width:16px;height:16px;color:#475569" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </div>
                            <h2 class="text-base font-bold text-slate-900 dark:text-white">Equipment &amp; Materials Needed</h2>
                        </div>
                        <svg class="w-4 h-4 text-slate-400 transition-transform shrink-0" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-cloak class="px-5 sm:px-6 pb-5 sm:pb-6 -mt-2">
                        <div class="flex flex-wrap gap-2">
                            @foreach($moduleRubric->equipment_supplies as $item)
                                <span class="inline-flex items-center px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-xs font-medium text-slate-600 dark:text-slate-300">{{ $item }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                {{-- ── Pre-test ──────────────────────────────────────────────── --}}
                @if($preTestStatus['exists'])
                <div id="pre-test" class="section-card">
                    <div class="card-stripe" style="background:#f59e0b"></div>
                    <div class="p-5 sm:p-6">
                        <div class="flex items-center justify-between gap-3 mb-4">
                            <div class="flex items-center gap-3">
                                <div style="width:34px;height:34px;border-radius:10px;background:#fef3c7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <svg style="width:16px;height:16px;color:#d97706" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                </div>
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">Pre-Test</h2>
                            </div>
                            @if($preTestStatus['completed'])
                                <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:700;background:#dcfce7;color:#15803d">
                                    <svg style="width:11px;height:11px" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L9.53 12.22a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                                    Done — {{ $preTestStatus['attempt']?->correct_answers }}/{{ $preTestStatus['attempt']?->total_questions }}
                                </span>
                            @endif
                        </div>
                        @if($preTestStatus['completed'])
                            @include('mentee.partials.quiz-review', ['status' => $preTestStatus, 'revealAnswers' => $postTestStatus['completed'] ?? false])
                            <div class="mt-3">
                                <form action="{{ route('mentee.class.quiz.start', [$class->id, $classModule->id, $preTestStatus['quiz']->id]) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="attempt_type" value="pre_test">
                                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-sm font-semibold hover:bg-slate-50 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                        Retake Pre-Test
                                    </button>
                                </form>
                            </div>
                        @else
                            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">Complete the pre-test to unlock module content, case scenarios, and the hands-on video section.</p>
                            <form action="{{ route('mentee.class.quiz.start', [$class->id, $classModule->id, $preTestStatus['quiz']->id]) }}" method="POST">
                                @csrf
                                <input type="hidden" name="attempt_type" value="pre_test">
                                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold transition-colors shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    Start Pre-Test
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
                @endif

                {{-- ── Content lock notice ──────────────────────────────────── --}}
                @if($isEmonc && !$canAccessContent)
                    <div class="section-card">
                        <div class="card-stripe" style="background:#94a3b8"></div>
                        <div class="p-5 flex items-start gap-4">
                            <div style="width:40px;height:40px;border-radius:12px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <svg style="width:20px;height:20px;color:#94a3b8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-700 dark:text-slate-300">Content locked</p>
                                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Complete the pre-test above to unlock case scenarios, videos, and the hands-on video submission.</p>
                            </div>
                        </div>
                    </div>
                @endif

                @if($canAccessContent)

                    {{-- ── Case Scenario (+ progression) — unlocked after the pre-test ─── --}}
                    @if($caseScenarios->isNotEmpty() || $caseScenarioProgressions->isNotEmpty())
                    <div class="section-card">
                        <div class="card-stripe" style="background:#0ea5e9"></div>
                        <div class="p-5 sm:p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div style="width:34px;height:34px;border-radius:10px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <svg style="width:16px;height:16px;color:#0284c7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                </div>
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">Case Scenario</h2>
                            </div>
                            @foreach($caseScenarios as $scenario)
                                <div class="{{ $loop->first ? '' : 'mt-4 pt-4 border-t border-slate-100 dark:border-slate-800' }}">
                                    <h3 class="font-semibold text-slate-900 dark:text-white mb-2 text-sm">{{ $scenario->title }}</h3>
                                    <div class="prose prose-sm dark:prose-invert max-w-none">{!! Str::markdown($scenario->content) !!}</div>
                                </div>
                            @endforeach
                            @foreach($caseScenarioProgressions as $progression)
                                <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                                    <h3 class="font-semibold text-slate-900 dark:text-white mb-2 text-sm">{{ $progression->title }}</h3>
                                    <div class="prose prose-sm dark:prose-invert max-w-none">{!! Str::markdown($progression->content) !!}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- ── Sessions — other tracks in this PPH sequence, for navigation ── --}}
                    @if($sessions->isNotEmpty())
                    <div class="section-card">
                        <div class="card-stripe" style="background:#0ea5e9"></div>
                        <div class="p-5 sm:p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div style="width:34px;height:34px;border-radius:10px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <svg style="width:16px;height:16px;color:#0284c7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                </div>
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">Sessions in this Sequence</h2>
                            </div>
                            <div class="space-y-2">
                                @foreach($sessions as $session)
                                    @php
                                        $sStatus = $session['status'];
                                        $sDone = in_array($sStatus, ['completed', 'exempted']);
                                    @endphp
                                    <a href="{{ $session['isCurrent'] ? '#' : $session['url'] }}"
                                       class="flex items-center justify-between gap-3 p-3 rounded-xl border transition-colors {{ $session['isCurrent'] ? 'border-brand-300 bg-brand-50 dark:bg-brand-950/30 dark:border-brand-700 cursor-default' : 'border-slate-200 dark:border-slate-700 hover:border-brand-300' }}">
                                        <span class="text-sm font-semibold {{ $session['isCurrent'] ? 'text-brand-700 dark:text-brand-300' : 'text-slate-700 dark:text-slate-300' }}">
                                            {{ $session['label'] }}
                                            @if($session['isCurrent'])
                                                <span class="text-xs font-normal text-brand-500">(you are here)</span>
                                            @endif
                                        </span>
                                        <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:100px;font-size:10px;font-weight:700;background:{{ $sDone?'#dcfce7':($sStatus==='in_progress'?'#fef3c7':'#f1f5f9') }};color:{{ $sDone?'#166534':($sStatus==='in_progress'?'#92400e':'#64748b') }}">
                                            {{ ucfirst(str_replace('_',' ',$sStatus)) }}
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- ── Practical Assessment Result ──────────────────────── --}}
                    @if($isEmonc)
                    <div class="section-card">
                        <div class="card-stripe" style="background:#7c3aed"></div>
                        <div class="p-5 sm:p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div style="width:34px;height:34px;border-radius:10px;background:#ede9fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <svg style="width:16px;height:16px;color:#7c3aed" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                </div>
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">Practical Assessment</h2>
                            </div>

                            @if($rubricAssessment ?? null)
                                @php
                                    $ra = $rubricAssessment;
                                    $raPct = $ra->scorePercentage();
                                    $raColor = $ra->passed ? '#16a34a' : ($raPct >= 60 ? '#d97706' : '#dc2626');
                                    $raBg = $ra->passed ? '#f0fdf4' : '#fff7f7';
                                    $raBdr = $ra->passed ? '#6ee7b7' : '#fca5a5';
                                @endphp
                                <div style="border-radius:14px;border:1.5px solid {{ $raBdr }};background:{{ $raBg }};overflow:hidden;">
                                    {{-- Score bar --}}
                                    <div style="padding:18px 20px;display:flex;align-items:center;gap:16px;">
                                        <div>
                                            <div style="font-size:30px;font-weight:800;color:{{ $raColor }};">
                                                {{ $ra->score }}<span style="font-size:16px;font-weight:400;color:#9ca3af;">/{{ $ra->rubric->total_marks }}</span>
                                            </div>
                                            <div style="font-size:11px;color:#6b7280;">{{ $ra->rubric->title }}</div>
                                        </div>
                                        <div style="flex:1;height:8px;background:#e5e7eb;border-radius:999px;overflow:hidden;">
                                            <div style="height:100%;border-radius:999px;background:{{ $raColor }};width:{{ $raPct }}%;"></div>
                                        </div>
                                        <div style="text-align:right;">
                                            <div style="font-size:18px;font-weight:700;color:{{ $raColor }};">{{ $raPct }}%</div>
                                            <span style="display:inline-block;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;background:{{ $ra->passed ? '#16a34a' : '#dc2626' }};color:#fff;">
                                                {{ $ra->passed ? 'PASS' : 'FAIL' }}
                                            </span>
                                        </div>
                                    </div>
                                    {{-- Meta --}}
                                    <div style="padding:10px 20px 16px;border-top:1px solid {{ $raBdr }};display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between;">
                                        <div style="font-size:12px;color:#6b7280;">
                                            Assessed by <strong style="color:#374151;">{{ $ra->mentor->full_name }}</strong>
                                            &nbsp;·&nbsp; {{ $ra->assessed_at->format('d M Y, H:i') }}
                                        </div>
                                        <a href="{{ url('/admin/rubric-assessments/' . $ra->id) }}"
                                           style="font-size:12px;font-weight:600;color:#7c3aed;text-decoration:none;">
                                            View full assessment →
                                        </a>
                                    </div>
                                </div>
                            @else
                                <div style="text-align:center;padding:32px 20px;background:#f5f3ff;border:2px dashed #ddd6fe;border-radius:14px;">
                                    <div style="width:52px;height:52px;border-radius:50%;background:#ede9fe;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                                        <svg style="width:24px;height:24px;color:#8b5cf6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1">Practical Assessment Pending</p>
                                    <p class="text-xs text-slate-500">Your mentor will conduct and record your practical assessment for this module.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- ── Instructional videos ─────────────────────────────── --}}
                    @if($videos->isNotEmpty())
                    <div class="section-card">
                        <div class="card-stripe" style="background:#8b5cf6"></div>
                        <div class="p-5 sm:p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div style="width:34px;height:34px;border-radius:10px;background:#ede9fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <svg style="width:16px;height:16px;color:#7c3aed" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">Reference Videos</h2>
                            </div>
                            <div class="space-y-5">
                                @foreach($videos as $video)
                                    <div>
                                        <h3 class="font-semibold text-slate-900 dark:text-white text-sm mb-2">{{ $video->title }}</h3>
                                        @php
                                            $embedUrl  = $video->youtubeEmbedUrl();
                                            $directUrl = $video->video_path
                                                ? \Illuminate\Support\Facades\Storage::disk('public')->url($video->video_path)
                                                : null;
                                            $isDirectMp4 = $directUrl && preg_match('/\.(mp4|webm|ogg|mov|m4v)(\?.*)?$/i', $directUrl);
                                        @endphp
                                        @if($embedUrl)
                                            <div class="w-full rounded-xl overflow-hidden bg-black" style="height:480px">
                                                <iframe src="{{ $embedUrl }}" class="w-full h-full" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                            </div>
                                        @elseif($isDirectMp4)
                                            <div class="w-full rounded-xl overflow-hidden bg-black" style="height:480px">
                                                <video controls class="w-full h-full">
                                                    <source src="{{ $directUrl }}">
                                                </video>
                                            </div>
                                        @elseif($video->video_url)
                                            {{-- External URL that isn't YouTube — open in new tab --}}
                                            <a href="{{ $video->video_url }}" target="_blank" rel="noopener"
                                               class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 hover:border-brand-300 dark:hover:border-brand-600 transition-colors group">
                                                <div style="width:40px;height:40px;border-radius:12px;background:#ede9fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                                    <svg style="width:18px;height:18px;color:#7c3aed" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $video->title ?: 'Watch Video' }}</p>
                                                    <p class="text-xs text-slate-400 truncate">{{ $video->video_url }}</p>
                                                </div>
                                                <svg class="w-4 h-4 text-slate-400 group-hover:text-brand-500 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </a>
                                        @endif
                                        @if($video->content)
                                            <div class="prose prose-sm dark:prose-invert max-w-none mt-3">{!! Str::markdown($video->content) !!}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- ── Submit Hands-on Video ────────────────────────────── --}}
                    @if($isEmonc)
                    <div id="submit-video" class="section-card" x-data="{ inputType: 'file' }">
                        <div class="card-stripe" style="background:#f59e0b"></div>
                        <div class="p-5 sm:p-6">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div class="flex items-center gap-3">
                                    <div style="width:34px;height:34px;border-radius:10px;background:#fef3c7;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                        <svg style="width:16px;height:16px;color:#d97706" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    </div>
                                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Submit Hands-on Video</h2>
                                </div>
                                @if($progress->hands_on_video_url)
                                    @php $vrs = $progress->video_review_status; @endphp
                                    @if($vrs === 'passed')
                                        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:700;background:#dcfce7;color:#15803d">
                                            <svg style="width:11px;height:11px" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L9.53 12.22a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg> Passed
                                        </span>
                                    @elseif($vrs === 'failed')
                                        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:700;background:#fee2e2;color:#b91c1c">
                                            <svg style="width:11px;height:11px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg> Needs Revision
                                        </span>
                                    @else
                                        <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:700;background:#dbeafe;color:#1d4ed8">
                                            <span class="pulse-dot" style="width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block"></span> Under Review
                                        </span>
                                    @endif
                                @endif
                            </div>

                            {{-- Existing video preview --}}
                            @if($progress->hands_on_video_url)
                                <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4 mb-5 bg-slate-50 dark:bg-slate-800/50">
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-3">Your Submitted Video</p>
                                    @if($progress->youtubeEmbedUrl())
                                        <div class="w-full rounded-xl overflow-hidden bg-black" style="height:480px">
                                            <iframe src="{{ $progress->youtubeEmbedUrl() }}" class="w-full h-full" frameborder="0" allowfullscreen></iframe>
                                        </div>
                                    @elseif($progress->isDirectVideoUrl())
                                        <video controls class="w-full rounded-lg" style="max-height:480px">
                                            <source src="{{ $progress->hands_on_video_url }}">
                                        </video>
                                    @else
                                        <div class="flex items-center gap-3 p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                                            <div style="width:36px;height:36px;border-radius:10px;background:#ede9fe;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                                <svg style="width:16px;height:16px;color:#7c3aed" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium truncate">{{ $progress->hands_on_video_url }}</p>
                                                <p class="text-xs text-slate-400">External video link</p>
                                            </div>
                                            <a href="{{ $progress->hands_on_video_url }}" target="_blank" class="px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold transition-colors">Open</a>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                                {{ $progress->hands_on_video_url ? 'Upload a replacement or paste a new link:' : 'Record yourself performing the skill and upload or share a link:' }}
                            </p>
                            <form action="{{ route('mentee.class.video.upload', [$class->id, $classModule->id]) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                                @csrf
                                <div class="flex items-center gap-4">
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                                        <input type="radio" name="video_input_type" value="file" x-model="inputType" class="text-brand-600 focus:ring-brand-500"> Upload file
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                                        <input type="radio" name="video_input_type" value="link" x-model="inputType" class="text-brand-600 focus:ring-brand-500"> Paste video link
                                    </label>
                                </div>
                                <div x-show="inputType === 'file'">
                                    <div class="border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl p-5 text-center hover:border-brand-300 transition-colors">
                                        <svg class="w-8 h-8 mx-auto mb-2 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                        <input type="file" name="hands_on_video" accept="video/*" :required="inputType === 'file'" class="block w-full text-sm text-slate-600 dark:text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
                                        <p class="text-xs text-slate-400 mt-1">MP4, MOV, AVI — max 50 MB</p>
                                    </div>
                                </div>
                                <div x-show="inputType === 'link'" x-cloak>
                                    <input type="url" name="hands_on_video_link" placeholder="https://youtube.com/..." :required="inputType === 'link'"
                                           class="block w-full rounded-xl border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm focus:border-brand-500 focus:ring-brand-500 px-4 py-2.5">
                                    <p class="text-xs text-slate-400 mt-1.5">YouTube, Vimeo, or direct video URL</p>
                                </div>
                                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold transition-colors shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    {{ $progress->hands_on_video_url ? 'Replace Video' : 'Submit Video' }}
                                </button>
                            </form>
                        </div>
                    </div>
                    @endif

                    {{-- ── Post-test ─────────────────────────────────────────── --}}
                    @if($postTestStatus['exists'])
                    <div id="post-test" class="section-card">
                        <div class="card-stripe" style="background:#f43f5e"></div>
                        <div class="p-5 sm:p-6">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div class="flex items-center gap-3">
                                    <div style="width:34px;height:34px;border-radius:10px;background:#ffe4e6;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                        <svg style="width:16px;height:16px;color:#e11d48" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <h2 class="text-base font-bold text-slate-900 dark:text-white">Post-Test</h2>
                                </div>
                                @if($postTestStatus['completed'])
                                    <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:700;background:#dcfce7;color:#15803d">
                                        <svg style="width:11px;height:11px" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L9.53 12.22a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                                        Done — {{ $postTestStatus['attempt']?->correct_answers }}/{{ $postTestStatus['attempt']?->total_questions }}
                                    </span>
                                @endif
                            </div>
                            @if($postTestStatus['completed'])
                                @include('mentee.partials.quiz-review', ['status' => $postTestStatus, 'revealAnswers' => true])
                            @endif
                            @if(!$hasSubmittedVideo || $progress->video_review_status !== 'passed')
                                <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 px-4 py-4 flex items-start gap-3">
                                    <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    <div>
                                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Post-test locked</p>
                                        <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">Submit your hands-on video and have it <strong>passed</strong> by your mentor first.</p>
                                    </div>
                                </div>
                            @else
                                <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
                                    {{ $postTestStatus['completed'] ? 'You can retake the post-test to improve your score.' : 'Your video has been approved. Take the post-test to complete this module.' }}
                                </p>
                                <form action="{{ route('mentee.class.quiz.start', [$class->id, $classModule->id, $postTestStatus['quiz']->id]) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="attempt_type" value="post_test">
                                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white text-sm font-semibold transition-colors shadow-sm" style="background:#e11d48">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        {{ $postTestStatus['completed'] ? 'Retake Post-Test' : 'Start Post-Test' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                    @endif

                @endif {{-- end $canAccessContent --}}

            @endif {{-- end $hasAnyContent --}}

            <div class="h-4"></div>
        </div>

        {{-- ── RIGHT PANE: Activities + Score ──────────────────────────────── --}}
        <aside class="col-right sticky-side space-y-4">

            {{-- Score card --}}
            @if(($preTestStatus['exists'] && $preTestStatus['completed']) || ($postTestStatus['exists'] && $postTestStatus['completed']))
            <div class="section-card">
                <div class="card-stripe" style="background:linear-gradient(90deg,#8b5cf6,#4f46e5)"></div>
                <div class="p-4">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Scores</p>
                    <div class="space-y-3">
                        @if($preTestStatus['exists'] && $preTestStatus['completed'])
                            @php
                                $preScore = $preTestStatus['attempt']->score;
                                $preCorrect = $preTestStatus['attempt']->correct_answers;
                                $preTotal = $preTestStatus['attempt']->total_questions;
                            @endphp
                            <div>
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                                    <span style="font-size:11px;font-weight:600;color:#64748b">Pre-test</span>
                                    <span style="font-size:13px;font-weight:800;color:{{ $preScore>=80?'#16a34a':($preScore>=50?'#d97706':'#dc2626') }}">{{ $preCorrect }}/{{ $preTotal }}</span>
                                </div>
                                <div style="height:5px;border-radius:100px;background:#e2e8f0;overflow:hidden">
                                    <div style="width:{{ $preScore }}%;height:100%;background:{{ $preScore>=80?'#16a34a':($preScore>=50?'#f59e0b':'#ef4444') }};border-radius:100px"></div>
                                </div>
                            </div>
                        @endif
                        @if($postTestStatus['exists'] && $postTestStatus['completed'])
                            @php
                                $postScore = $postTestStatus['attempt']->score;
                                $postCorrect = $postTestStatus['attempt']->correct_answers;
                                $postTotal = $postTestStatus['attempt']->total_questions;
                            @endphp
                            <div>
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                                    <span style="font-size:11px;font-weight:600;color:#64748b">Post-test</span>
                                    <span style="font-size:13px;font-weight:800;color:{{ $postScore>=80?'#16a34a':($postScore>=50?'#d97706':'#dc2626') }}">{{ $postCorrect }}/{{ $postTotal }}</span>
                                </div>
                                <div style="height:5px;border-radius:100px;background:#e2e8f0;overflow:hidden">
                                    <div style="width:{{ $postScore }}%;height:100%;background:{{ $postScore>=80?'#16a34a':($postScore>=50?'#f59e0b':'#ef4444') }};border-radius:100px"></div>
                                </div>
                            </div>
                        @endif
                        @if($preTestStatus['exists'] && $preTestStatus['completed'] && $postTestStatus['exists'] && $postTestStatus['completed'])
                            @php
                                $diffCount = $postCorrect - $preCorrect;
                            @endphp
                            <div style="padding:10px 12px;border-radius:10px;background:{{ $diffCount>=0?'#f0fdf4':'#fff7ed' }};border:1px solid {{ $diffCount>=0?'#bbf7d0':'#fed7aa' }};margin-top:4px">
                                <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">Improvement</div>
                                <div style="font-size:20px;font-weight:800;color:{{ $diffCount>=0?'#16a34a':'#dc2626' }}">{{ $diffCount >= 0 ? '+' : '' }}{{ $diffCount }} correct</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- Activities card --}}
            @php
                $allActivities = $classModule->programModule?->activities ?? collect();
            @endphp
            @if($allActivities->isNotEmpty())
            <div class="section-card">
                <div class="card-stripe" style="background:#3b82f6"></div>
                <div class="p-4">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Activities</p>
                    <div class="space-y-2">
                        @foreach($allActivities as $activity)
                            @php
                                $enrolled  = in_array($activity->id, $enrolledActivityIds);
                                $completed = in_array($activity->id, $completedActivityIds);
                            @endphp
                            <div style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border-radius:10px;background:{{ $completed?'#f0fdf4':($enrolled?'#eff6ff':'#f8fafc') }};border:1px solid {{ $completed?'#bbf7d0':($enrolled?'#bfdbfe':'#e2e8f0') }}">
                                <div style="width:22px;height:22px;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:{{ $completed?'#16a34a':($enrolled?'#2563eb':'#94a3b8') }}">
                                    @if($completed)
                                        <svg style="width:12px;height:12px;color:#fff" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.236 4.53L9.53 12.22a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                                    @elseif($enrolled)
                                        <span class="pulse-dot" style="width:6px;height:6px;border-radius:50%;background:#fff;display:inline-block"></span>
                                    @else
                                        <svg style="width:11px;height:11px;color:#fff" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p style="font-size:12px;font-weight:600;color:{{ $completed?'#15803d':($enrolled?'#1d4ed8':'#64748b') }};line-height:1.3">{{ $activity->name }}</p>
                                    <p style="font-size:10px;font-weight:600;color:{{ $completed?'#16a34a':($enrolled?'#2563eb':'#94a3b8') }};margin-top:2px">
                                        {{ $completed ? 'Completed' : ($enrolled ? 'Enrolled' : 'Not enrolled') }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if(!$hasActivities && !$allActivities->isEmpty())
                        <div style="margin-top:10px;padding:10px 12px;border-radius:10px;background:#fef3c7;border:1px solid #fde68a">
                            <p style="font-size:11px;font-weight:600;color:#92400e">You are not yet enrolled in any activities. Ask your mentor to enroll you.</p>
                        </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- No activities at all --}}
            @if($allActivities->isEmpty() && !$hasAnyContent)
                {{-- shown via the main block above --}}
            @elseif($allActivities->isEmpty())
                <div class="section-card">
                    <div class="card-stripe" style="background:#94a3b8"></div>
                    <div class="p-4 text-center">
                        <svg class="w-8 h-8 mx-auto mb-2 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        <p class="text-xs text-slate-400 dark:text-slate-500">No activities set for this module</p>
                    </div>
                </div>
            @endif

        </aside>

    </div>
</main>

{{-- ── Quiz modal ─────────────────────────────────────────────────────────── --}}
@if(session('quiz_attempt_id'))
    @php $attempt = App\Models\QuizAttempt::with(['quiz.questions.options'])->find(session('quiz_attempt_id')); @endphp
    @if($attempt)
        <div x-data="{ open: true }" x-show="open"
             class="quiz-guard fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-sm"
             @copy.prevent @cut.prevent @paste.prevent @contextmenu.prevent @selectstart.prevent @dragstart.prevent
             @keydown="const k=$event.key.toLowerCase();const mod=$event.ctrlKey||$event.metaKey;if((mod&&['c','x','v','a','s','p','u'].includes(k))||k==='f12'||(mod&&$event.shiftKey&&['i','j','c'].includes(k))){$event.preventDefault()}"
             x-cloak>
            <div @click.away="open = false"
                 class="bg-white dark:bg-slate-900 rounded-t-3xl sm:rounded-2xl border-t sm:border border-slate-200 dark:border-slate-800 shadow-2xl w-full sm:max-w-2xl max-h-[92vh] overflow-y-auto">
                <div class="sticky top-0 bg-white dark:bg-slate-900 border-b border-slate-100 dark:border-slate-800 px-5 py-4 flex items-center justify-between rounded-t-3xl sm:rounded-t-2xl">
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $attempt->quiz->title }}</h3>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $attempt->quiz->questions->count() }} question{{ $attempt->quiz->questions->count() === 1 ? '' : 's' }}</p>
                    </div>
                    <button @click="open = false" class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 hover:text-slate-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-5">
                    <form action="{{ route('mentee.class.quiz.submit', [$class->id, $classModule->id, $attempt->id]) }}" method="POST" class="space-y-4">
                        @csrf
                        @foreach($attempt->quiz->questions as $index => $question)
                            <div class="rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700 p-4">
                                <p class="font-semibold text-slate-900 dark:text-white mb-3 text-sm leading-relaxed">
                                    <span class="inline-flex w-6 h-6 rounded-full bg-brand-100 dark:bg-brand-900/40 text-brand-600 dark:text-brand-400 items-center justify-center text-[11px] font-bold mr-2 shrink-0 align-text-bottom">{{ $index + 1 }}</span>
                                    {!! $question->question_text !!}
                                </p>
                                <div class="space-y-2">
                                    @foreach($question->options as $option)
                                        <label class="flex items-start gap-3 p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 cursor-pointer hover:border-brand-300 transition-colors has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:has-[:checked]:bg-brand-950/30">
                                            <input type="radio" name="responses[{{ $question->id }}]" value="{{ $option->id }}" required class="mt-0.5 text-brand-600 focus:ring-brand-500 shrink-0">
                                            <span class="text-sm text-slate-700 dark:text-slate-300">{{ $option->option_text }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold transition-colors shadow-sm">
                            Submit Quiz
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endif

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>

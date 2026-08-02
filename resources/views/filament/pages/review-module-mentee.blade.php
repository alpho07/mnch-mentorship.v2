@php
$mentee        = $participant->user;
$facility      = $mentee?->facility;
$subcounty     = $facility?->subcounty;
$county        = $subcounty?->county;
$progressStatus = $progress?->status ?? 'not_started';
$isPresent     = in_array($progressStatus, ['in_progress', 'completed']);

// Avatar initials
$nameParts = array_filter(explode(' ', trim($mentee?->full_name ?? 'MN')));
$initials  = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice($nameParts, 0, 2)));

// Quiz helpers
$preHas    = $preTestStatus['exists']     ?? false;
$preDone   = $preTestStatus['completed']  ?? false;
$postHas   = $postTestStatus['exists']    ?? false;
$postDone  = $postTestStatus['completed'] ?? false;

// Video
$videoStatus   = $progress?->video_review_status ?? 'pending';

// EmONC certification
$mentorApprovedAt = $participant->mentor_approved_at;
$mentorApprovedBy = $participant->mentorApprovedBy;
$headApprovedAt   = $participant->head_drmh_approved_at ?? null;
$isApproved  = (bool) $mentorApprovedAt;
$isReady     = $isReadyForMentorApproval;
$canApprove  = $canMentorApprove;
$canRevert   = $isApproved && !$headApprovedAt && $canApprove;
$mentorLocked = $isApproved;

// Hero overall status
if ($isEmonc && $headApprovedAt)   { $overallStatus = 'certified'; }
elseif ($isEmonc && $isApproved)   { $overallStatus = 'mentor_approved'; }
elseif ($progressStatus === 'completed') { $overallStatus = 'completed'; }
elseif ($isPresent)                { $overallStatus = 'in_progress'; }
else                               { $overallStatus = 'pending'; }

// Quick-stat helpers ─────────────────────────────────────────────────────────
// Pre-test — score only, no pass/fail language (team decision 2026-07-24:
// pass/fail stays exclusive to the practical/rubric assessment; pre/post
// tests just show the score and, implicitly via pre-vs-post, improvement).
$preScoreLabel = ($preTestStatus['attempt'] ?? null)
    ? "{$preTestStatus['attempt']->correct_answers}/{$preTestStatus['attempt']->total_questions}"
    : 'Done';
$postScoreLabel = ($postTestStatus['attempt'] ?? null)
    ? "{$postTestStatus['attempt']->correct_answers}/{$postTestStatus['attempt']->total_questions}"
    : 'Done';

if (!$preHas)        { $ptC='#9ca3af'; $ptBg='#f9fafb'; $ptBdr='#e5e7eb'; $ptIco='#f3f4f6'; $ptLabel='No Pre-Test';    $ptPath='<path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>'; }
elseif (!$preDone)   { $ptC='#f59e0b'; $ptBg='#fffbeb'; $ptBdr='#fde68a'; $ptIco='#fef3c7'; $ptLabel='Not Taken';      $ptPath='<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>'; }
else                 { $ptC='#2563eb'; $ptBg='#eff6ff'; $ptBdr='#bfdbfe'; $ptIco='#dbeafe'; $ptLabel=$preScoreLabel;  $ptPath='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'; }

// Post-test — same treatment as pre-test.
if (!$postHas)       { $pstC='#9ca3af'; $pstBg='#f9fafb'; $pstBdr='#e5e7eb'; $pstIco='#f3f4f6'; $pstLabel='No Post-Test';  $pstPath='<path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>'; }
elseif (!$postDone)  { $pstC='#f59e0b'; $pstBg='#fffbeb'; $pstBdr='#fde68a'; $pstIco='#fef3c7'; $pstLabel='Not Taken';    $pstPath='<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>'; }
else                 { $pstC='#2563eb'; $pstBg='#eff6ff'; $pstBdr='#bfdbfe'; $pstIco='#dbeafe'; $pstLabel=$postScoreLabel; $pstPath='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'; }

// Video review
if ($videoStatus === 'passed')     { $vidC='#10b981'; $vidBg='#f0fdf4'; $vidBdr='#6ee7b7'; $vidIco='#d1fae5'; $vidLabel='Passed';  $vidPath='<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'; }
elseif ($videoStatus === 'failed') { $vidC='#ef4444'; $vidBg='#fef2f2'; $vidBdr='#fca5a5'; $vidIco='#fee2e2'; $vidLabel='Failed';  $vidPath='<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'; }
else                               { $vidC='#f59e0b'; $vidBg='#fffbeb'; $vidBdr='#fde68a'; $vidIco='#fef3c7'; $vidLabel='Pending'; $vidPath='<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z"/>'; }
@endphp

<x-filament-panels::page>
<div x-data="{
        open: false,
        action: '',
        title: '',
        body: '',
        confirmLabel: 'Confirm',
        confirmColor: '#059669',
        iconColor: '#059669',
        iconBg: '#d1fae5',
        iconPath: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        openConfirm(action, title, body, confirmLabel, variant) {
            this.action = action; this.title = title; this.body = body;
            this.confirmLabel = confirmLabel || 'Confirm';
            if (variant === 'danger') {
                this.confirmColor = '#dc2626'; this.iconColor = '#dc2626'; this.iconBg = '#fee2e2';
                this.iconPath = 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z';
            } else {
                this.confirmColor = '#059669'; this.iconColor = '#059669'; this.iconBg = '#d1fae5';
                this.iconPath = 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
            }
            this.open = true;
        },
        confirm() { this.open = false; $wire[this.action](); }
    }" x-on:keydown.escape.window="open = false">

    {{-- ═══ CONFIRMATION MODAL ══════════════════════════════════════════════════ --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         x-on:click="open = false"
         style="position:fixed;inset:0;background:rgba(17,24,39,0.5);z-index:9998;backdrop-filter:blur(3px);"
         x-cloak></div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:9999;width:100%;max-width:460px;padding:0 16px;"
         x-cloak>
        <div style="background:#fff;border-radius:20px;box-shadow:0 25px 80px rgba(0,0,0,0.25);overflow:hidden;">
            <div style="padding:32px 32px 24px;">
                <div style="display:flex;align-items:flex-start;gap:16px;">
                    <div :style="'flex-shrink:0;width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:'+iconBg">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" :style="'width:24px;height:24px;color:'+iconColor">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath"/>
                        </svg>
                    </div>
                    <div>
                        <h3 x-text="title" style="font-size:17px;font-weight:700;color:#111827;margin:0 0 6px;"></h3>
                        <p x-text="body" style="font-size:14px;color:#6b7280;margin:0;line-height:1.6;"></p>
                    </div>
                </div>
            </div>
            <div style="padding:0 32px 28px;display:flex;justify-content:flex-end;gap:10px;">
                <button x-on:click="open = false"
                        style="padding:10px 22px;border-radius:10px;border:1.5px solid #e5e7eb;background:#fff;color:#374151;font-size:14px;font-weight:600;cursor:pointer;">
                    Cancel
                </button>
                <button x-on:click="confirm()"
                        :style="'padding:10px 22px;border-radius:10px;border:none;background:'+confirmColor+';color:#fff;font-size:14px;font-weight:600;cursor:pointer;'">
                    <span x-text="confirmLabel"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══ HERO BANNER ════════════════════════════════════════════════════════ --}}
    <div class="rv-animate" style="animation-delay:0s;border-radius:20px;overflow:hidden;margin-bottom:20px;background:linear-gradient(135deg,#1e3a8a 0%,#3730a3 45%,#6d28d9 100%);position:relative;box-shadow:0 8px 32px rgba(30,58,138,.35);">
        <div style="position:absolute;top:-50px;right:-50px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,0.05);pointer-events:none;"></div>
        <div style="position:absolute;bottom:-70px;left:30%;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,0.04);pointer-events:none;"></div>
        <div style="position:absolute;top:0;left:0;right:0;bottom:0;background:url('data:image/svg+xml,%3Csvg width=\'40\' height=\'40\' viewBox=\'0 0 40 40\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.03\'%3E%3Ccircle cx=\'20\' cy=\'20\' r=\'1\'/%3E%3C/g%3E%3C/svg%3E');pointer-events:none;"></div>

        <div style="position:relative;padding:28px 32px;display:flex;align-items:center;gap:22px;flex-wrap:wrap;">
            {{-- Avatar --}}
            <div style="width:76px;height:76px;min-width:76px;border-radius:50%;background:rgba(255,255,255,0.15);backdrop-filter:blur(8px);border:2.5px solid rgba(255,255,255,0.35);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;color:#fff;letter-spacing:-0.5px;box-shadow:0 4px 20px rgba(0,0,0,0.2);">
                {{ $initials }}
            </div>

            {{-- Name & meta --}}
            <div style="flex:1;min-width:220px;">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:5px;">
                    <h2 style="font-size:22px;font-weight:800;color:#fff;margin:0;letter-spacing:-0.3px;">{{ $mentee?->full_name ?? '—' }}</h2>
                    @php
                        $heroBadge = match($overallStatus) {
                            'certified'       => ['Fully Certified',  '#d1fae5','#065f46'],
                            'mentor_approved' => ['Mentor Approved',  '#dbeafe','#1e40af'],
                            'completed'       => ['Completed',        '#d1fae5','#065f46'],
                            'in_progress'     => ['In Progress',      '#fef3c7','#92400e'],
                            default           => ['Pending',          'rgba(255,255,255,0.15)','rgba(255,255,255,0.9)'],
                        };
                    @endphp
                    <span style="background:{{ $heroBadge[1] }};color:{{ $heroBadge[2] }};border-radius:9999px;padding:3px 12px;font-size:12px;font-weight:700;letter-spacing:0.02em;">
                        {{ $heroBadge[0] }}
                    </span>
                </div>
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:6px;">
                    @if($mentee?->cadre?->name)
                        <span style="font-size:13px;color:rgba(255,255,255,0.9);font-weight:600;">{{ $mentee->cadre->name }}</span>
                        <span style="color:rgba(255,255,255,0.3);">·</span>
                    @endif
                    @if($facility?->name)
                        <span style="font-size:13px;color:rgba(255,255,255,0.75);">{{ $facility->name }}</span>
                    @endif
                    @if($subcounty?->name)
                        <span style="color:rgba(255,255,255,0.3);">·</span>
                        <span style="font-size:13px;color:rgba(255,255,255,0.65);">{{ $subcounty->name }}</span>
                    @endif
                    @if($county?->name)
                        <span style="color:rgba(255,255,255,0.3);">·</span>
                        <span style="font-size:13px;color:rgba(255,255,255,0.55);">{{ $county->name }} County</span>
                    @endif
                </div>
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <span style="font-size:11px;color:rgba(255,255,255,0.5);">MODULE</span>
                    <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">{{ $module->programModule?->name ?? '—' }}</span>
                    <span style="color:rgba(255,255,255,0.25);">·</span>
                    <span style="font-size:11px;color:rgba(255,255,255,0.5);">CLASS</span>
                    <span style="font-size:12px;color:rgba(255,255,255,0.9);font-weight:600;">{{ $class->name ?? '—' }}</span>
                    @if($mentee?->department?->name)
                        <span style="color:rgba(255,255,255,0.25);">·</span>
                        <span style="font-size:12px;color:rgba(255,255,255,0.6);">{{ $mentee->department->name }}</span>
                    @endif
                </div>
            </div>

            {{-- Contact --}}
            <div style="display:flex;flex-direction:column;gap:5px;align-items:flex-end;">
                @if($mentee?->email)
                    <div style="display:flex;align-items:center;gap:6px;">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="rgba(255,255,255,0.5)" style="width:13px;height:13px;"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                        <span style="font-size:12px;color:rgba(255,255,255,0.7);">{{ $mentee->email }}</span>
                    </div>
                @endif
                @if($mentee?->phone)
                    <div style="display:flex;align-items:center;gap:6px;">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="rgba(255,255,255,0.5)" style="width:13px;height:13px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                        <span style="font-size:12px;color:rgba(255,255,255,0.7);">{{ $mentee->phone }}</span>
                    </div>
                @endif
                <div style="margin-top:4px;">
                    <span style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);color:rgba(255,255,255,0.85);border-radius:9999px;padding:3px 10px;font-size:11px;font-weight:600;">
                        {{ $isPresent ? 'Attendance Confirmed' : 'Attendance Not Confirmed' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ COURSE INFORMATION (mentor-facing) ════════════════════════════════ --}}
    @if($mentorCourseIntro || $mentorMaterials || $moduleRubric?->debrief_questions || $moduleRubric?->equipment_supplies)
    <div class="rv-animate" style="animation-delay:0.04s;margin-bottom:20px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;gap:10px;">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#4f46e5" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
            <h3 style="font-size:14px;font-weight:800;color:#111827;margin:0;">Course Information</h3>
        </div>
        <div style="padding:18px 20px;display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
            @if($mentorCourseIntro)
                <div>
                    <p style="font-size:10px;text-transform:uppercase;letter-spacing:0.07em;font-weight:700;color:#9ca3af;margin:0 0 6px;">Course Introduction</p>
                    <div class="prose prose-sm max-w-none" style="font-size:13px;color:#374151;">{!! Str::markdown($mentorCourseIntro->content) !!}</div>
                </div>
            @endif
            @if($mentorMaterials)
                <div>
                    <p style="font-size:10px;text-transform:uppercase;letter-spacing:0.07em;font-weight:700;color:#9ca3af;margin:0 0 6px;">Materials Needed for the Course</p>
                    <div class="prose prose-sm max-w-none" style="font-size:13px;color:#374151;">{!! Str::markdown($mentorMaterials->content) !!}</div>
                </div>
            @endif
            @if($moduleRubric?->equipment_supplies)
                <div>
                    <p style="font-size:10px;text-transform:uppercase;letter-spacing:0.07em;font-weight:700;color:#9ca3af;margin:0 0 6px;">Equipment &amp; Supplies</p>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        @foreach($moduleRubric->equipment_supplies as $item)
                            <span style="display:inline-flex;align-items:center;padding:3px 10px;border-radius:9999px;background:#f1f5f9;font-size:11px;font-weight:600;color:#475569;">{{ $item }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
            @if($moduleRubric?->debrief_questions)
                <div>
                    <p style="font-size:10px;text-transform:uppercase;letter-spacing:0.07em;font-weight:700;color:#9ca3af;margin:0 0 6px;">Debrief Notes</p>
                    <ul style="margin:0;padding-left:16px;font-size:13px;color:#374151;list-style:disc;">
                        @foreach($moduleRubric->debrief_questions as $question)
                            <li style="margin-bottom:4px;">{{ $question }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ═══ QUICK STATS ROW ════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 rv-animate" style="animation-delay:0.08s;margin-bottom:24px;">

        {{-- Attendance --}}
        @php
            $attC = $isPresent ? '#10b981' : '#f59e0b';
            $attBg = $isPresent ? '#f0fdf4' : '#fffbeb';
            $attBdr = $isPresent ? '#6ee7b7' : '#fde68a';
            $attIco = $isPresent ? '#d1fae5' : '#fef3c7';
            $attLabel = $isPresent ? 'Confirmed' : 'Not Confirmed';
            $attPath = $isPresent
                ? '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'
                : '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>';
        @endphp
        <div class="rv-stat-card" style="background:{{ $attBg }};border:1.5px solid {{ $attBdr }};border-radius:16px;padding:18px 20px;display:flex;align-items:center;gap:14px;">
            <div style="width:46px;height:46px;min-width:46px;border-radius:13px;background:{{ $attIco }};display:flex;align-items:center;justify-content:center;">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="{{ $attC }}" style="width:22px;height:22px;">{!! $attPath !!}</svg>
            </div>
            <div>
                <p style="font-size:10px;text-transform:uppercase;letter-spacing:0.07em;font-weight:700;color:#9ca3af;margin:0 0 3px;">Attendance</p>
                <p style="font-size:14px;font-weight:700;color:#111827;margin:0;">{{ $attLabel }}</p>
            </div>
        </div>

        {{-- Pre-Test --}}
        <div class="rv-stat-card" style="background:{{ $ptBg }};border:1.5px solid {{ $ptBdr }};border-radius:16px;padding:18px 20px;display:flex;align-items:center;gap:14px;">
            <div style="width:46px;height:46px;min-width:46px;border-radius:13px;background:{{ $ptIco }};display:flex;align-items:center;justify-content:center;">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="{{ $ptC }}" style="width:22px;height:22px;">{!! $ptPath !!}</svg>
            </div>
            <div>
                <p style="font-size:10px;text-transform:uppercase;letter-spacing:0.07em;font-weight:700;color:#9ca3af;margin:0 0 3px;">Pre-Test</p>
                <p style="font-size:14px;font-weight:700;color:#111827;margin:0;">{{ $ptLabel }}</p>
            </div>
        </div>

        {{-- Post-Test --}}
        <div class="rv-stat-card" style="background:{{ $pstBg }};border:1.5px solid {{ $pstBdr }};border-radius:16px;padding:18px 20px;display:flex;align-items:center;gap:14px;">
            <div style="width:46px;height:46px;min-width:46px;border-radius:13px;background:{{ $pstIco }};display:flex;align-items:center;justify-content:center;">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="{{ $pstC }}" style="width:22px;height:22px;">{!! $pstPath !!}</svg>
            </div>
            <div>
                <p style="font-size:10px;text-transform:uppercase;letter-spacing:0.07em;font-weight:700;color:#9ca3af;margin:0 0 3px;">Post-Test</p>
                <p style="font-size:14px;font-weight:700;color:#111827;margin:0;">{{ $pstLabel }}</p>
            </div>
        </div>

        {{-- Video Review --}}
        <div class="rv-stat-card" style="background:{{ $vidBg }};border:1.5px solid {{ $vidBdr }};border-radius:16px;padding:18px 20px;display:flex;align-items:center;gap:14px;">
            <div style="width:46px;height:46px;min-width:46px;border-radius:13px;background:{{ $vidIco }};display:flex;align-items:center;justify-content:center;">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="{{ $vidC }}" style="width:22px;height:22px;">{!! $vidPath !!}</svg>
            </div>
            <div>
                <p style="font-size:10px;text-transform:uppercase;letter-spacing:0.07em;font-weight:700;color:#9ca3af;margin:0 0 3px;">Video Review</p>
                <p style="font-size:14px;font-weight:700;color:#111827;margin:0;">{{ $vidLabel }}</p>
            </div>
        </div>
    </div>

    {{-- ═══ MAIN CONTENT GRID ══════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ─── LEFT SIDEBAR ──────────────────────────────────────────────────── --}}
        <div class="lg:col-span-1 space-y-5">

            {{-- Mentorship card --}}
            <div class="rv-card rv-animate" style="animation-delay:0.12s;background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">
                <div style="padding:15px 20px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:10px;">
                    <div style="width:32px;height:32px;border-radius:9px;background:#eff6ff;display:flex;align-items:center;justify-content:center;">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#3b82f6" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg>
                    </div>
                    <h3 style="font-size:13px;font-weight:700;color:#111827;margin:0;text-transform:uppercase;letter-spacing:0.04em;">Mentorship</h3>
                </div>
                <div style="padding:16px 20px;">
                    <dl style="display:flex;flex-direction:column;gap:11px;">
                        <div>
                            <dt style="font-size:10px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;color:#9ca3af;margin:0 0 2px;">Program</dt>
                            <dd style="font-size:13px;font-weight:600;color:#111827;margin:0;">{{ $training->program?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt style="font-size:10px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;color:#9ca3af;margin:0 0 2px;">Training</dt>
                            <dd style="font-size:13px;color:#374151;margin:0;">{{ $training->title ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt style="font-size:10px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;color:#9ca3af;margin:0 0 2px;">Facility</dt>
                            <dd style="font-size:13px;color:#374151;margin:0;">{{ $training->facility?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt style="font-size:10px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;color:#9ca3af;margin:0 0 2px;">Mentor</dt>
                            <dd style="font-size:13px;color:#374151;margin:0;">{{ $training->mentor?->full_name ?? '—' }}</dd>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;background:#f9fafb;border-radius:9px;padding:8px 12px;">
                            <span style="font-size:11px;font-weight:600;color:#6b7280;">Status</span>
                            @php
                                $ts = match($training->status ?? '') {
                                    'active','ongoing' => ['#d1fae5','#065f46'],
                                    'completed'        => ['#dbeafe','#1e40af'],
                                    default            => ['#f3f4f6','#6b7280'],
                                };
                            @endphp
                            <span style="background:{{ $ts[0] }};color:{{ $ts[1] }};border-radius:9999px;padding:2px 10px;font-size:11px;font-weight:600;">{{ ucfirst($training->status ?? '—') }}</span>
                        </div>
                        @if(!$isEmonc && ($training->start_date || $training->end_date))
                            <div>
                                <dt style="font-size:10px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;color:#9ca3af;margin:0 0 2px;">Schedule</dt>
                                <dd style="font-size:13px;color:#374151;margin:0;">{{ $training->start_date?->format('d M Y') ?? '—' }} – {{ $training->end_date?->format('d M Y') ?? '—' }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Module card --}}
            <div class="rv-card rv-animate" style="animation-delay:0.16s;background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">
                <div style="padding:15px 20px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:10px;">
                    <div style="width:32px;height:32px;border-radius:9px;background:#f5f3ff;display:flex;align-items:center;justify-content:center;">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#8b5cf6" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                    </div>
                    <h3 style="font-size:13px;font-weight:700;color:#111827;margin:0;text-transform:uppercase;letter-spacing:0.04em;">Module</h3>
                </div>
                <div style="padding:16px 20px;">
                    <dl style="display:flex;flex-direction:column;gap:11px;">
                        <div>
                            <dt style="font-size:10px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;color:#9ca3af;margin:0 0 2px;">Module</dt>
                            <dd style="font-size:13px;font-weight:600;color:#111827;margin:0;">{{ $module->programModule?->name ?? '—' }}</dd>
                        </div>
                        @if($module->programModule?->parent)
                            <div>
                                <dt style="font-size:10px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;color:#9ca3af;margin:0 0 2px;">Track</dt>
                                <dd style="font-size:13px;color:#6d28d9;font-weight:500;margin:0;">{{ $module->programModule->parent->name }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt style="font-size:10px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;color:#9ca3af;margin:0 0 2px;">Class</dt>
                            <dd style="font-size:13px;color:#374151;margin:0;">{{ $class->name ?? '—' }}</dd>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;background:#f9fafb;border-radius:9px;padding:8px 12px;">
                            <span style="font-size:11px;font-weight:600;color:#6b7280;">Status</span>
                            @php
                                $ms = match($module->status ?? 'not_started') {
                                    'completed'  => ['#d1fae5','#065f46'],
                                    'in_progress'=> ['#fef3c7','#92400e'],
                                    default      => ['#f3f4f6','#6b7280'],
                                };
                            @endphp
                            <span style="background:{{ $ms[0] }};color:{{ $ms[1] }};border-radius:9999px;padding:2px 10px;font-size:11px;font-weight:600;">{{ ucfirst(str_replace('_',' ',$module->status ?? 'Not Started')) }}</span>
                        </div>
                        @if(!$isEmonc && ($module->start_date || $module->end_date))
                            <div>
                                <dt style="font-size:10px;text-transform:uppercase;letter-spacing:0.06em;font-weight:700;color:#9ca3af;margin:0 0 2px;">Dates</dt>
                                <dd style="font-size:13px;color:#374151;margin:0;">{{ $module->start_date?->format('d M Y') ?? '—' }} – {{ $module->end_date?->format('d M Y') ?? '—' }}</dd>
                            </div>
                        @endif
                        <div style="display:flex;align-items:center;justify-content:space-between;background:#f9fafb;border-radius:9px;padding:8px 12px;">
                            <span style="font-size:11px;font-weight:600;color:#6b7280;">Attendance</span>
                            <span style="font-size:13px;font-weight:700;color:#111827;">{{ $module->confirmedAttendanceCount() }} / {{ $module->enrolledMenteeCount() }}</span>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Activities card --}}
            <div class="rv-card rv-animate" style="animation-delay:0.20s;background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">
                <div style="padding:15px 20px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:32px;height:32px;border-radius:9px;background:#fff7ed;display:flex;align-items:center;justify-content:center;">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#f97316" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0118 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75"/></svg>
                        </div>
                        <h3 style="font-size:13px;font-weight:700;color:#111827;margin:0;text-transform:uppercase;letter-spacing:0.04em;">Activities</h3>
                    </div>
                    <span style="background:#fff7ed;color:#c2410c;border-radius:9999px;padding:2px 10px;font-size:12px;font-weight:700;">{{ count($activities) }}</span>
                </div>
                @forelse($activities as $activity)
                    <div style="padding:11px 20px;{{ !$loop->last ? 'border-bottom:1px solid #f9fafb;' : '' }}display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <div style="display:flex;align-items:center;gap:9px;min-width:0;">
                            <span style="width:8px;height:8px;min-width:8px;border-radius:50%;background:{{ $activity['completed'] ? '#10b981' : ($activity['enrolled'] ? '#3b82f6' : '#e5e7eb') }};"></span>
                            <span style="font-size:13px;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $activity['name'] }}</span>
                        </div>
                        @if($activity['completed'])
                            <span style="background:#d1fae5;color:#065f46;border-radius:9999px;padding:2px 9px;font-size:11px;font-weight:700;flex-shrink:0;">Done</span>
                        @elseif($activity['enrolled'])
                            <span style="background:#dbeafe;color:#1e40af;border-radius:9999px;padding:2px 9px;font-size:11px;font-weight:700;flex-shrink:0;">Enrolled</span>
                        @else
                            <span style="background:#f3f4f6;color:#9ca3af;border-radius:9999px;padding:2px 9px;font-size:11px;font-weight:600;flex-shrink:0;">—</span>
                        @endif
                    </div>
                @empty
                    <div style="padding:24px 20px;text-align:center;color:#9ca3af;font-size:13px;">No activities for this module.</div>
                @endforelse
            </div>

        </div>

        {{-- ─── RIGHT MAIN CONTENT ─────────────────────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Hands-on Video --}}
            <div class="rv-card rv-animate" style="animation-delay:0.14s;background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">
                <div style="padding:18px 24px 15px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:12px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:#fdf2f8;display:flex;align-items:center;justify-content:center;">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#ec4899" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z"/></svg>
                    </div>
                    <div>
                        <h3 style="font-size:15px;font-weight:700;color:#111827;margin:0;">Hands-on Video</h3>
                        <p style="font-size:11px;color:#9ca3af;margin:2px 0 0;">Submitted by mentee for evaluation</p>
                    </div>
                </div>
                <div style="padding:22px 24px;">
                    @if($progress && ($progress->hands_on_video_url || $progress->hands_on_video_path))
                        @include('filament.components.video-preview', [
                            'embedUrl'      => $progress->youtubeEmbedUrl(),
                            'videoUrl'      => $progress->hands_on_video_url,
                            'isDirectVideo' => $progress->isDirectVideoUrl(),
                        ])
                    @else
                        <div style="text-align:center;padding:40px 20px;background:#f9fafb;border:2px dashed #e5e7eb;border-radius:14px;">
                            <div style="width:60px;height:60px;border-radius:50%;background:#f3f4f6;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#d1d5db" style="width:30px;height:30px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z"/></svg>
                            </div>
                            <p style="font-size:14px;font-weight:600;color:#6b7280;margin:0 0 4px;">No video submitted yet</p>
                            <p style="font-size:12px;color:#9ca3af;margin:0;">The mentee has not uploaded a hands-on video for this module.</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Pre-Test --}}
            <div class="rv-card rv-animate" style="animation-delay:0.18s;background:#fff;border:1.5px solid {{ $ptBdr }};border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">
                <div style="padding:18px 24px 15px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:{{ $ptIco }};display:flex;align-items:center;justify-content:center;">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="{{ $ptC }}" style="width:18px;height:18px;">{!! $ptPath !!}</svg>
                        </div>
                        <div>
                            <h3 style="font-size:15px;font-weight:700;color:#111827;margin:0;">Pre-Test</h3>
                            <p style="font-size:11px;color:#9ca3af;margin:2px 0 0;">Knowledge assessment before training</p>
                        </div>
                    </div>
                    <span style="background:{{ $ptBg }};color:{{ $ptC }};border:1px solid {{ $ptBdr }};border-radius:9999px;padding:4px 14px;font-size:12px;font-weight:700;">{{ $ptLabel }}</span>
                </div>
                <div style="padding:22px 24px;">
                    @if($preHas && $preDone)
                        @include('mentee.partials.quiz-review', ['status' => $preTestStatus])
                    @elseif($preHas)
                        <div style="display:flex;align-items:center;gap:12px;padding:16px 18px;background:#fffbeb;border-radius:12px;border:1px solid #fde68a;">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#f59e0b" style="width:20px;height:20px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                            <p style="font-size:13px;color:#92400e;margin:0;">The mentee has not completed the pre-test yet.</p>
                        </div>
                    @else
                        <p style="font-size:13px;color:#9ca3af;margin:0;font-style:italic;">This module does not have a pre-test.</p>
                    @endif
                </div>
            </div>

            {{-- Post-Test --}}
            <div class="rv-card rv-animate" style="animation-delay:0.22s;background:#fff;border:1.5px solid {{ $pstBdr }};border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">
                <div style="padding:18px 24px 15px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:{{ $pstIco }};display:flex;align-items:center;justify-content:center;">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="{{ $pstC }}" style="width:18px;height:18px;">{!! $pstPath !!}</svg>
                        </div>
                        <div>
                            <h3 style="font-size:15px;font-weight:700;color:#111827;margin:0;">Post-Test</h3>
                            <p style="font-size:11px;color:#9ca3af;margin:2px 0 0;">Knowledge assessment after training</p>
                        </div>
                    </div>
                    <span style="background:{{ $pstBg }};color:{{ $pstC }};border:1px solid {{ $pstBdr }};border-radius:9999px;padding:4px 14px;font-size:12px;font-weight:700;">{{ $pstLabel }}</span>
                </div>
                <div style="padding:22px 24px;">
                    @if($postHas && $postDone)
                        @include('mentee.partials.quiz-review', ['status' => $postTestStatus])
                    @elseif($postHas)
                        <div style="display:flex;align-items:center;gap:12px;padding:16px 18px;background:#fffbeb;border-radius:12px;border:1px solid #fde68a;">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#f59e0b" style="width:20px;height:20px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                            <p style="font-size:13px;color:#92400e;margin:0;">The mentee has not completed the post-test yet.</p>
                        </div>
                    @else
                        <p style="font-size:13px;color:#9ca3af;margin:0;font-style:italic;">This module does not have a post-test.</p>
                    @endif
                </div>
            </div>

            {{-- Video Evaluation Form --}}
            <div class="rv-card rv-animate" style="animation-delay:0.26s;background:#fff;border:1.5px solid {{ $mentorLocked ? '#fde68a' : '#e5e7eb' }};border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);{{ $mentorLocked ? 'opacity:0.7;' : '' }}">
                <div style="padding:18px 24px 15px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:{{ $mentorLocked ? '#fef3c7' : '#f0f9ff' }};display:flex;align-items:center;justify-content:center;">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="{{ $mentorLocked ? '#f59e0b' : '#0ea5e9' }}" style="width:18px;height:18px;">
                                @if($mentorLocked)
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                                @endif
                            </svg>
                        </div>
                        <div>
                            <h3 style="font-size:15px;font-weight:700;color:#111827;margin:0;">Video Evaluation</h3>
                            <p style="font-size:11px;color:{{ $mentorLocked ? '#d97706' : '#9ca3af' }};margin:2px 0 0;">{{ $mentorLocked ? 'Locked — revert approval to edit' : 'Mentor records outcome and feedback' }}</p>
                        </div>
                    </div>
                    @if($mentorLocked)
                        <span style="display:inline-flex;align-items:center;gap:5px;background:#fef3c7;color:#92400e;border-radius:9999px;padding:4px 12px;font-size:12px;font-weight:700;">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:12px;height:12px;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                            Locked
                        </span>
                    @endif
                </div>
                <div style="padding:24px;{{ $mentorLocked ? 'pointer-events:none;user-select:none;' : '' }}">
                    <form wire:submit="saveReview">
                        {{ $this->form }}
                        <div style="margin-top:20px;padding-top:18px;border-top:1px solid #f3f4f6;display:flex;align-items:center;gap:12px;">
                            <button type="submit"
                                    @if($mentorLocked) disabled @endif
                                    style="{{ $mentorLocked
                                        ? 'display:inline-flex;align-items:center;gap:7px;background:#f3f4f6;color:#9ca3af;border:none;border-radius:10px;padding:10px 22px;font-size:14px;font-weight:600;cursor:not-allowed;'
                                        : 'display:inline-flex;align-items:center;gap:7px;background:#2563eb;color:#fff;border:none;border-radius:10px;padding:10px 22px;font-size:14px;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(37,99,235,.3);' }}">
                                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Save Review
                            </button>
                            <span style="font-size:12px;color:#9ca3af;">{{ $mentorLocked ? 'Locked after mentor approval.' : 'Saves outcome and notifies the mentee.' }}</span>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ═══ PRACTICAL ASSESSMENT ═══════════════════════════════════════ --}}
            @if($moduleRubric)
            <div class="rv-card rv-animate" style="animation-delay:0.28s;background:#fff;border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">
                <div style="padding:18px 24px 15px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:#ede9fe;display:flex;align-items:center;justify-content:center;">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="#7c3aed" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        </div>
                        <div>
                            <h3 style="font-size:15px;font-weight:700;color:#111827;margin:0;">Practical Assessment</h3>
                            <p style="font-size:11px;color:#9ca3af;margin:2px 0 0;">{{ $moduleRubric->title }}</p>
                        </div>
                    </div>
                    @if($latestRubricAssessment)
                        <span style="display:inline-flex;align-items:center;gap:5px;background:{{ $latestRubricAssessment->passed ? '#d1fae5' : '#fee2e2' }};color:{{ $latestRubricAssessment->passed ? '#065f46' : '#991b1b' }};border-radius:9999px;padding:4px 14px;font-size:12px;font-weight:700;">
                            {{ $latestRubricAssessment->passed ? 'PASS' : 'FAIL' }} &nbsp;·&nbsp; {{ $latestRubricAssessment->score }}/{{ $moduleRubric->total_marks }}
                        </span>
                    @else
                        <span style="background:#f5f3ff;color:#7c3aed;border-radius:9999px;padding:4px 14px;font-size:12px;font-weight:600;">Not Yet Assessed</span>
                    @endif
                </div>

                <div style="padding:20px 24px;">
                    @if($latestRubricAssessment)
                        @php
                            $ra = $latestRubricAssessment;
                            $raPct = $ra->scorePercentage();
                            $raFill = $ra->passed ? '#16a34a' : ($raPct >= 60 ? '#d97706' : '#dc2626');
                        @endphp
                        {{-- Score bar --}}
                        <div style="display:flex;align-items:center;gap:16px;background:#f9fafb;border-radius:12px;padding:16px 18px;margin-bottom:16px;">
                            <div>
                                <div style="font-size:28px;font-weight:800;color:{{ $raFill }};">
                                    {{ $ra->score }}<span style="font-size:16px;font-weight:400;color:#9ca3af;">/{{ $moduleRubric->total_marks }}</span>
                                </div>
                                <div style="font-size:11px;color:#6b7280;">items performed to standard</div>
                            </div>
                            <div style="flex:1;height:8px;background:#e5e7eb;border-radius:999px;overflow:hidden;">
                                <div style="height:100%;border-radius:999px;background:{{ $raFill }};width:{{ $raPct }}%;"></div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:18px;font-weight:700;color:{{ $raFill }};">{{ $raPct }}%</div>
                                <span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;background:{{ $ra->passed ? '#16a34a' : '#dc2626' }};color:#fff;">
                                    {{ $ra->passed ? 'PASS' : 'FAIL' }}
                                </span>
                            </div>
                        </div>
                        {{-- Meta & actions --}}
                        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
                            <div style="font-size:12px;color:#6b7280;">
                                Assessed by <strong style="color:#374151;">{{ $ra->mentor->full_name }}</strong>
                                &nbsp;·&nbsp; {{ $ra->assessed_at->format('d M Y, H:i') }}
                                @if($ra->notes)
                                    &nbsp;·&nbsp; <em style="color:#374151;">{{ $ra->notes }}</em>
                                @endif
                            </div>
                            <div style="display:flex;gap:8px;">
                                <a href="{{ url('/admin/rubric-assessments/' . $ra->id) }}"
                                   style="display:inline-flex;align-items:center;gap:6px;background:#f5f3ff;color:#7c3aed;border:1px solid #ddd6fe;border-radius:8px;padding:7px 14px;font-size:12px;font-weight:600;text-decoration:none;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:13px;height:13px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    View Details
                                </a>
                                <a href="{{ url('/admin/rubric-assessments/' . $ra->id . '/edit') }}"
                                   style="display:inline-flex;align-items:center;gap:6px;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;border-radius:8px;padding:7px 14px;font-size:12px;font-weight:600;text-decoration:none;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:13px;height:13px;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                                    Edit Score
                                </a>
                                <a href="{{ $conductAssessmentUrl }}"
                                   style="display:inline-flex;align-items:center;gap:6px;background:#2563eb;color:#fff;border:none;border-radius:8px;padding:7px 14px;font-size:12px;font-weight:600;text-decoration:none;cursor:pointer;">
                                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:13px;height:13px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                    New Assessment
                                </a>
                            </div>
                        </div>
                    @else
                        {{-- No assessment yet --}}
                        <div style="text-align:center;padding:32px 20px;background:#f5f3ff;border:2px dashed #ddd6fe;border-radius:14px;margin-bottom:16px;">
                            <div style="width:52px;height:52px;border-radius:50%;background:#ede9fe;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#8b5cf6" style="width:24px;height:24px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            </div>
                            <p style="font-size:14px;font-weight:600;color:#5b21b6;margin:0 0 4px;">No practical assessment recorded yet</p>
                            <p style="font-size:12px;color:#6b7280;margin:0;">Pass mark: {{ $moduleRubric->pass_marks }}/{{ $moduleRubric->total_marks }} (≥ {{ $moduleRubric->pass_percentage }}%)</p>
                        </div>
                        <div style="display:flex;justify-content:center;">
                            <a href="{{ $conductAssessmentUrl }}"
                               style="display:inline-flex;align-items:center;gap:8px;background:#7c3aed;color:#fff;border:none;border-radius:10px;padding:11px 24px;font-size:14px;font-weight:700;text-decoration:none;box-shadow:0 3px 10px rgba(124,58,237,.35);">
                                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                Conduct Practical Assessment
                            </a>
                        </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- ═══ CERTIFICATION STATUS (EmONC only) ══════════════════════════ --}}
            @if($isEmonc)
            <div class="rv-card rv-animate" style="animation-delay:0.30s;background:{{ $headApprovedAt ? '#f0fdf4' : ($isApproved ? '#f5f3ff' : '#fff') }};border:{{ $headApprovedAt ? '1.5px solid #6ee7b7' : ($isApproved ? '1.5px solid #c4b5fd' : '1px solid #e5e7eb') }};border-radius:18px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,.05);">

                {{-- Header --}}
                <div style="padding:18px 24px 15px;border-bottom:1px solid {{ $headApprovedAt ? '#d1fae5' : ($isApproved ? '#ede9fe' : '#f3f4f6') }};display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:{{ $headApprovedAt ? '#d1fae5' : ($isApproved ? '#ede9fe' : '#f3f4f6') }};display:flex;align-items:center;justify-content:center;">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="{{ $headApprovedAt ? '#10b981' : ($isApproved ? '#7c3aed' : '#9ca3af') }}" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                        </div>
                        <div>
                            <h3 style="font-size:15px;font-weight:700;color:#111827;margin:0;">Certification Status</h3>
                            <p style="font-size:11px;color:#9ca3af;margin:2px 0 0;">EmONC program — two-step approval required</p>
                        </div>
                    </div>
                    @if($headApprovedAt)
                        <span style="display:inline-flex;align-items:center;gap:5px;background:#d1fae5;color:#065f46;border-radius:9999px;padding:4px 14px;font-size:12px;font-weight:700;">
                            <svg viewBox="0 0 20 20" fill="currentColor" style="width:13px;height:13px;"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Fully Certified
                        </span>
                    @elseif($isApproved)
                        <span style="background:#ede9fe;color:#5b21b6;border-radius:9999px;padding:4px 14px;font-size:12px;font-weight:700;">Mentor Approved</span>
                    @elseif($isReady)
                        <span style="background:#dbeafe;color:#1e40af;border-radius:9999px;padding:4px 14px;font-size:12px;font-weight:700;">Ready for Approval</span>
                    @else
                        <span style="background:#fef3c7;color:#92400e;border-radius:9999px;padding:4px 14px;font-size:12px;font-weight:700;">Awaiting Completion</span>
                    @endif
                </div>

                {{-- Steps timeline --}}
                <div style="padding:24px 24px 20px;">
                    <div style="position:relative;">
                        {{-- connector --}}
                        <div style="position:absolute;left:19px;top:42px;height:calc(100% - 80px);width:2px;background:{{ $isApproved ? 'linear-gradient(to bottom, #6ee7b7, #c4b5fd)' : '#f3f4f6' }};z-index:0;border-radius:2px;"></div>

                        {{-- STEP 1: Mentor Approval --}}
                        <div style="position:relative;z-index:1;display:flex;gap:14px;margin-bottom:24px;">
                            <div style="flex-shrink:0;">
                                @if($isApproved)
                                    <div style="width:40px;height:40px;border-radius:50%;background:#10b981;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 5px #d1fae5;">
                                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="#fff" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    </div>
                                @else
                                    <div style="width:40px;height:40px;border-radius:50%;background:{{ $isReady ? '#ede9fe' : '#f9fafb' }};border:2.5px solid {{ $isReady ? '#a78bfa' : '#e5e7eb' }};display:flex;align-items:center;justify-content:center;box-shadow:{{ $isReady ? '0 0 0 4px #f5f3ff' : 'none' }};">
                                        <span style="font-size:14px;font-weight:700;color:{{ $isReady ? '#7c3aed' : '#9ca3af' }};">1</span>
                                    </div>
                                @endif
                            </div>
                            <div style="flex:1;padding-top:8px;">
                                <p style="font-size:14px;font-weight:700;color:#111827;margin:0 0 6px;">Mentor Approval</p>

                                @if($isApproved)
                                    <div style="background:#d1fae5;border:1px solid #a7f3d0;border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:12px;">
                                        <div style="width:36px;height:36px;min-width:36px;border-radius:50%;background:#6ee7b7;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:#065f46;">
                                            {{ strtoupper(substr($mentorApprovedBy?->full_name ?? 'M', 0, 1)) }}
                                        </div>
                                        <div>
                                            <p style="font-size:13px;font-weight:700;color:#065f46;margin:0;">{{ $mentorApprovedBy?->full_name ?? 'Mentor' }}</p>
                                            <p style="font-size:11px;color:#059669;margin:2px 0 0;">Approved {{ \Carbon\Carbon::parse($mentorApprovedAt)->format('d M Y \a\t H:i') }}</p>
                                        </div>
                                    </div>
                                @elseif(!$isReady)
                                    <p style="font-size:12px;color:#d97706;margin:0;line-height:1.5;">All module progress and video reviews must be completed before approval can be granted.</p>
                                @else
                                    <p style="font-size:12px;color:#7c3aed;margin:0;line-height:1.5;">All requirements met — ready for mentor approval.</p>
                                @endif

                                @if(!$isApproved && $canApprove)
                                    <div style="margin-top:14px;display:flex;flex-wrap:wrap;gap:8px;">
                                        <button @if($isReady)
                                                    x-on:click="openConfirm('mentorApprove','Approve Mentee','Approve {{ addslashes($participant->user?->full_name) }} for {{ addslashes($class->name) }}? This will notify the mentee and Head DRMH for final certification.','Approve Mentee')"
                                                @else
                                                    disabled
                                                @endif
                                                style="{{ $isReady
                                                    ? 'display:inline-flex;align-items:center;gap:8px;background:#059669;color:#fff;border:none;border-radius:10px;padding:11px 24px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 3px 10px rgba(5,150,105,.35);'
                                                    : 'display:inline-flex;align-items:center;gap:8px;background:#f3f4f6;color:#9ca3af;border:none;border-radius:10px;padding:11px 24px;font-size:14px;font-weight:600;cursor:not-allowed;' }}">
                                            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            {{ $isReady ? 'Approve Mentee' : 'Cannot Approve Yet' }}
                                        </button>
                                    </div>
                                @endif

                                @if($canRevert)
                                    <div style="margin-top:10px;">
                                        <button x-on:click="openConfirm('revertApproval','Revert Mentor Approval','Are you sure you want to revert mentor approval for {{ addslashes($participant->user?->full_name) }}? The mentee will need to be re-approved before Head DRMH certification.','Revert Approval','danger')"
                                                style="display:inline-flex;align-items:center;gap:6px;background:#fff;color:#dc2626;border:1.5px solid #fca5a5;border-radius:10px;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;">
                                            <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:14px;height:14px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                                            Revert Approval
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- STEP 2: Head DRMH --}}
                        <div style="position:relative;z-index:1;display:flex;gap:14px;">
                            <div style="flex-shrink:0;">
                                @if($headApprovedAt)
                                    <div style="width:40px;height:40px;border-radius:50%;background:#10b981;display:flex;align-items:center;justify-content:center;box-shadow:0 0 0 5px #d1fae5;">
                                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="#fff" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    </div>
                                @else
                                    <div style="width:40px;height:40px;border-radius:50%;background:{{ $isApproved ? '#ede9fe' : '#f9fafb' }};border:2.5px solid {{ $isApproved ? '#c4b5fd' : '#e5e7eb' }};display:flex;align-items:center;justify-content:center;box-shadow:{{ $isApproved ? '0 0 0 4px #f5f3ff' : 'none' }};">
                                        <span style="font-size:14px;font-weight:700;color:{{ $isApproved ? '#7c3aed' : '#d1d5db' }};">2</span>
                                    </div>
                                @endif
                            </div>
                            <div style="flex:1;padding-top:8px;">
                                <p style="font-size:14px;font-weight:700;color:{{ $isApproved ? '#111827' : '#9ca3af' }};margin:0 0 6px;">Head DRMH Certification</p>
                                @if($headApprovedAt)
                                    <div style="background:#d1fae5;border:1px solid #a7f3d0;border-radius:12px;padding:14px 16px;">
                                        <p style="font-size:13px;font-weight:700;color:#065f46;margin:0;">Certified {{ \Carbon\Carbon::parse($headApprovedAt)->format('d M Y') }}</p>
                                        <p style="font-size:12px;color:#059669;margin:4px 0 0;">Final certification complete — mentee is eligible to receive their certificate.</p>
                                    </div>
                                @elseif($isApproved)
                                    <div style="background:#ede9fe;border:1px solid #ddd6fe;border-radius:12px;padding:14px 16px;">
                                        <p style="font-size:13px;color:#5b21b6;margin:0;">Awaiting Head DRMH review and final certification.</p>
                                    </div>
                                @else
                                    <p style="font-size:12px;color:#d1d5db;margin:0;line-height:1.5;">Complete Step 1 first to unlock Head DRMH certification.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>

</div>{{-- end x-data --}}
</x-filament-panels::page>

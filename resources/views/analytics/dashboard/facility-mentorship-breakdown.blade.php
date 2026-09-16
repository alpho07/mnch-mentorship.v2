@extends('layouts.dashboard')
@section('title', $facility->name . ' — Mentorship Breakdown')

@section('content')
<style>
.breakdown-hero { background: linear-gradient(135deg, var(--teal-dark) 0%, var(--teal) 100%); padding: 1.5rem 2rem; color: #fff; margin-bottom: 0; }
.breakdown-hero h1 { font-size: 1.6rem; font-weight: 800; margin: 0 0 .25rem; }
.breakdown-hero p  { margin: 0; color: rgba(255,255,255,.8); font-size: .9rem; }
.breadcrumb-bar { background: #fff; border-bottom: 1px solid var(--gray-200); padding: .6rem 2rem; font-size: .82rem; }
.breadcrumb-bar a { color: var(--teal); text-decoration: none; }
.breadcrumb-bar a:hover { text-decoration: underline; }
.program-block { background: #fff; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.07); border: 1px solid var(--gray-200); margin: 1.5rem; }
.program-header { background: linear-gradient(90deg, var(--teal-50) 0%, #E0F2FE 100%); padding: 1rem 1.5rem; border-bottom: 2px solid var(--teal-100); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; border-radius: 14px 14px 0 0; }
.program-header h5 { margin: 0; font-weight: 800; color: var(--teal-dark); font-size: 1rem; }
.class-row { border-bottom: 1px solid var(--gray-100); }
.class-header { padding: .9rem 1.5rem; display: flex; justify-content: space-between; align-items: center; cursor: pointer; background: var(--gray-50); }
.class-header:hover { background: var(--teal-50); }
.class-header h6 { margin: 0; font-weight: 700; color: var(--gray-800); font-size: .9rem; }
.class-meta { display: flex; gap: 1rem; flex-wrap: wrap; font-size: .78rem; color: var(--gray-500); }
.mentee-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.mentee-table th { background: var(--teal-dark); color: rgba(255,255,255,.9); padding: .6rem 1rem; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; text-align: left; }
.mentee-table td { padding: .55rem 1rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); }
.mentee-table tr:hover td { background: var(--teal-50); }
.badge-status { display: inline-block; padding: .2rem .55rem; border-radius: 12px; font-size: .72rem; font-weight: 700; }
</style>

{{-- Breadcrumb --}}
<div class="breadcrumb-bar">
    @foreach($breadcrumbs as $i => $crumb)
        @if($crumb['url'])
            <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>
        @else
            <span style="color:var(--gray-700)">{{ $crumb['name'] }}</span>
        @endif
        @if(!$loop->last) <span class="mx-1" style="color:var(--gray-400)">/</span> @endif
    @endforeach
</div>

{{-- Hero --}}
<div class="breakdown-hero">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
        <div>
            <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.7);margin-bottom:.4rem;">
                <i class="fas fa-map-marker-alt me-1"></i>
                {{ $facility->subcounty->name ?? '' }}{{ ($facility->subcounty->county->name ?? '') ? ' — ' . $facility->subcounty->county->name : '' }}
            </div>
            <h1><i class="fas fa-user-friends me-2"></i>{{ $facility->name }}</h1>
            <p>{{ $mentorships->count() }} mentorship {{ $mentorships->count() === 1 ? 'programme' : 'programmes' }} &bull; Mentorship drill-down</p>
        </div>
        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
            <form method="GET" style="display:flex;gap:.5rem;align-items:center;">
                <select name="year" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="">All Years</option>
                    @foreach($availableYears as $yr)
                        <option value="{{ $yr }}" {{ $selectedYear == $yr ? 'selected' : '' }}>{{ $yr }}</option>
                    @endforeach
                </select>
            </form>
            <a href="{{ route('analytics.dashboard.index', ['mode' => 'assessment']) }}" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3);">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>
</div>

{{-- Programs --}}
@forelse($mentorships as $training)
    <div class="program-block">
        <div class="program-header">
            <div>
                <h5><i class="fas fa-graduation-cap me-2" style="color:var(--teal)"></i>{{ $training->title }}</h5>
                <div style="font-size:.8rem;color:var(--gray-600);margin-top:.25rem">
                    @if($training->program) <span><i class="fas fa-tag me-1"></i>{{ $training->program->name }}</span> @endif
                    @if($training->mentor)  <span class="ms-3"><i class="fas fa-user-tie me-1"></i>{{ $training->mentor->name }}</span> @endif
                    @if($training->start_date) <span class="ms-3"><i class="fas fa-calendar me-1"></i>{{ $training->start_date->format('M Y') }}</span> @endif
                </div>
            </div>
            @php
                $tStatus = $training->status ?? 'draft';
                $tBg = match($tStatus) { 'completed' => '#DBEAFE', 'ongoing', 'active' => '#D1FAE5', default => '#F1F5F9' };
                $tColor = match($tStatus) { 'completed' => '#1E40AF', 'ongoing', 'active' => '#065F46', default => '#475569' };
            @endphp
            <span class="badge-status" style="background:{{ $tBg }};color:{{ $tColor }}">
                {{ ucfirst($tStatus) }}
            </span>
        </div>

        @forelse($training->mentorshipClasses as $class)
            <div class="class-row" x-data="{ open: false }">
                <div class="class-header" @click="open = !open">
                    <div>
                        <h6>{{ $class->name }}</h6>
                        <div class="class-meta">
                            @if($class->start_date)
                                <span><i class="fas fa-calendar-alt me-1"></i>{{ $class->start_date->format('d M Y') }}
                                @if($class->end_date) – {{ $class->end_date->format('d M Y') }} @endif</span>
                            @endif
                            {{-- Modules hover popover --}}
                            <span
                                x-data="{ showModules: false }"
                                style="position:relative;cursor:pointer;"
                                @mouseenter="showModules = true"
                                @mouseleave="showModules = false"
                                @click.stop
                            >
                                <span style="color:var(--teal);font-weight:600;border-bottom:1px dashed var(--teal)">
                                    <i class="fas fa-cubes me-1"></i>{{ $class->classModules->count() }} modules
                                </span>

                                <div
                                    x-show="showModules"
                                    x-transition
                                    style="position:absolute;top:calc(100% + 6px);left:0;z-index:9999;background:#fff;border:1px solid var(--gray-200);border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,.15);min-width:340px;max-width:480px;"
                                >
                                    <div style="padding:.6rem 1rem;border-bottom:1px solid var(--gray-100);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--teal-dark);background:var(--teal-50);border-radius:10px 10px 0 0;">
                                        <i class="fas fa-cubes me-1"></i> Modules
                                    </div>
                                    @forelse($class->classModules->sortBy('order_sequence') as $module)
                                        <div
                                            x-data="{ showSessions: false }"
                                            style="border-bottom:1px solid var(--gray-100);"
                                        >
                                            <div
                                                @mouseenter="showSessions = true"
                                                @mouseleave="showSessions = false"
                                                @click.stop
                                                style="display:flex;justify-content:space-between;align-items:center;padding:.5rem .85rem;cursor:pointer;transition:background .15s;"
                                                :style="showSessions ? 'background:var(--teal-50)' : ''"
                                            >
                                                <div style="font-size:.8rem;color:var(--gray-800);font-weight:600;flex:1;min-width:0;">
                                                    @if($module->order_sequence)
                                                        <span style="color:var(--gray-500);font-size:.7rem;margin-right:.35rem;">{{ $module->order_sequence }}.</span>
                                                    @endif
                                                    {{ $module->programModule->name ?? 'Module' }}
                                                </div>
                                                <div style="display:flex;align-items:center;gap:.4rem;flex-shrink:0;margin-left:.5rem;">
                                                    @php
                                                        $mStatus = $module->status ?? 'not_started';
                                                        $mBg = match($mStatus) { 'completed' => '#D1FAE5', 'in_progress' => '#FEF3C7', default => '#F1F5F9' };
                                                        $mColor = match($mStatus) { 'completed' => '#065F46', 'in_progress' => '#92400E', default => '#64748B' };
                                                        $mLabel = match($mStatus) { 'completed' => 'Done', 'in_progress' => 'Active', 'not_started' => 'Pending', default => ucfirst($mStatus) };
                                                    @endphp
                                                    <span style="font-size:.68rem;font-weight:700;padding:.15rem .45rem;border-radius:8px;background:{{ $mBg }};color:{{ $mColor }}">{{ $mLabel }}</span>
                                                    @if($module->sessions->count() > 0)
                                                        <span style="font-size:.7rem;color:var(--teal);font-weight:600;">
                                                            <i class="fas fa-video me-1"></i>{{ $module->sessions->count() }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- Sessions sub-panel --}}
                                            <div
                                                x-show="showSessions"
                                                x-transition
                                                @mouseenter="showSessions = true"
                                                @mouseleave="showSessions = false"
                                                style="background:#F8FAFC;border-top:1px solid var(--gray-100);"
                                            >
                                                @forelse($module->sessions->sortBy('session_number') as $session)
                                                    @php
                                                        $sBg = match($session->status) { 'completed' => '#D1FAE5', 'in_progress' => '#FEF3C7', 'cancelled' => '#FEE2E2', default => '#F1F5F9' };
                                                        $sColor = match($session->status) { 'completed' => '#065F46', 'in_progress' => '#92400E', 'cancelled' => '#991B1B', default => '#475569' };
                                                    @endphp
                                                    <div style="display:flex;justify-content:space-between;align-items:center;padding:.4rem .85rem .4rem 1.5rem;font-size:.76rem;border-bottom:1px solid var(--gray-100);">
                                                        <div style="color:var(--gray-700);">
                                                            <span style="color:var(--gray-400);margin-right:.3rem;">S{{ $session->session_number }}</span>
                                                            {{ $session->title }}
                                                        </div>
                                                        <div style="display:flex;align-items:center;gap:.5rem;flex-shrink:0;margin-left:.5rem;">
                                                            @if($session->scheduled_date || $session->actual_date)
                                                                <span style="font-size:.68rem;color:var(--gray-500);">
                                                                    <i class="fas fa-calendar me-1"></i>{{ ($session->actual_date ?? $session->scheduled_date)?->format('d M Y') }}
                                                                </span>
                                                            @endif
                                                            <span style="font-size:.68rem;font-weight:700;padding:.12rem .4rem;border-radius:6px;background:{{ $sBg }};color:{{ $sColor }}">{{ ucfirst($session->status) }}</span>
                                                        </div>
                                                    </div>
                                                @empty
                                                    <div style="padding:.5rem 1.5rem;font-size:.75rem;color:var(--gray-400);">No sessions yet</div>
                                                @endforelse
                                            </div>
                                        </div>
                                    @empty
                                        <div style="padding:.75rem 1rem;font-size:.8rem;color:var(--gray-400);">No modules</div>
                                    @endforelse
                                </div>
                            </span>
                            <span><i class="fas fa-users me-1"></i>{{ $class->participants->count() }} mentees</span>
                            <span><i class="fas fa-calendar-check me-1"></i>{{ $class->attendance_present }}/{{ $class->attendance_total }} attendances</span>
                            <span><i class="fas fa-video me-1"></i>{{ $class->classModules->sum(fn($m) => $m->sessions->count()) }} sessions</span>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:.75rem;">
                        @php
                            $cStatus = $class->status ?? 'draft';
                            $cBg = match($cStatus) { 'completed' => '#DBEAFE', 'active' => '#D1FAE5', default => '#F1F5F9' };
                            $cColor = match($cStatus) { 'completed' => '#1E40AF', 'active' => '#065F46', default => '#475569' };
                        @endphp
                        <span class="badge-status" style="background:{{ $cBg }};color:{{ $cColor }}">
                            {{ ucfirst($cStatus) }}
                        </span>
                        <i class="fas" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'" style="color:var(--teal)"></i>
                    </div>
                </div>

                <div x-show="open" x-transition style="border-top:1px solid var(--gray-100);">
                    @if($class->participants->isEmpty())
                        <div style="padding:1.5rem;text-align:center;color:var(--gray-500)">No mentees enrolled in this class.</div>
                    @else
                        <div style="overflow-x:auto;">
                            <table class="mentee-table">
                                <thead>
                                    <tr>
                                        <th>Mentee</th>
                                        <th>Cadre</th>
                                        <th>Department</th>
                                        <th>Modules Attended</th>
                                        <th>Attendance</th>
                                        <th>Class Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($class->participants as $participant)
                                        <tr>
                                            <td style="font-weight:600">{{ $participant->user->name ?? '—' }}</td>
                                            <td>{{ $participant->user->cadre->name ?? '—' }}</td>
                                            <td>{{ $participant->user->department->name ?? '—' }}</td>
                                            <td>{{ $participant->modules_attended }}/{{ $participant->modules_total }}</td>
                                            <td>
                                                @php
                                                    $pct = $participant->attendance_pct;
                                                    $attStyle = $pct >= 80 ? 'background:#D1FAE5;color:#065F46' : ($pct >= 50 ? 'background:#FEF3C7;color:#92400E' : 'background:#FEE2E2;color:#991B1B');
                                                @endphp
                                                <span class="badge-status" style="{{ $attStyle }}">
                                                    {{ $participant->attendance_label }} ({{ $pct }}%)
                                                </span>
                                            </td>
                                            <td>
                                                @php
                                                    $pStatus = $participant->status ?? 'enrolled';
                                                    $stStyle = match($pStatus) {
                                                        'completed' => 'background:#DBEAFE;color:#1E40AF',
                                                        'dropped'   => 'background:#FEE2E2;color:#991B1B',
                                                        default     => 'background:#F1F5F9;color:#475569',
                                                    };
                                                @endphp
                                                <span class="badge-status" style="{{ $stStyle }}">{{ ucfirst($pStatus) }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div style="padding:1.5rem;text-align:center;color:var(--gray-500)">No classes found for this mentorship.</div>
        @endforelse
    </div>
@empty
    <div style="margin:2rem;text-align:center;padding:3rem;background:#fff;border-radius:14px;color:var(--gray-500);">
        <i class="fas fa-user-friends fa-3x mb-3 d-block" style="color:var(--gray-300)"></i>
        <h5 style="color:var(--gray-700)">No mentorship programs found</h5>
        <p>{{ $facility->name }} has not hosted any mentorship programs yet.</p>
    </div>
@endforelse

@endsection

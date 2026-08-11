<x-filament-panels::page>
<style>
.ra-wrap{max-width:920px;margin:0 auto;}
.ra-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;margin-bottom:20px;}
.dark .ra-card{background:#1e293b;border-color:#334155;}
.ra-card-head{padding:18px 24px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:12px;}
.dark .ra-card-head{border-color:#334155;}
.ra-card-title{font-size:15px;font-weight:700;color:#111827;}
.dark .ra-card-title{color:#f1f5f9;}
.ra-card-body{padding:24px;}
.ra-field{margin-bottom:18px;}
.ra-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;margin-bottom:6px;}
.ra-select,.ra-input{width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;color:#111827;background:#fff;outline:none;}
.dark .ra-select,.dark .ra-input{background:#0f172a;border-color:#334155;color:#f1f5f9;}
.ra-select:focus,.ra-input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);}
/* Filament's global form styles add their own chevron to <select> — override with a single
   explicit one so it doesn't double up with the browser's native arrow. */
select.ra-select{
    appearance:none;-webkit-appearance:none;-moz-appearance:none;
    padding-right:36px;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%236b7280'%3E%3Cpath fill-rule='evenodd' d='M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.23 8.29a.75.75 0 01.02-1.08z' clip-rule='evenodd'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 10px center;background-size:16px;
}
.ra-btn{padding:10px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;}
.ra-btn-primary{background:#2563eb;color:#fff;}
.ra-btn-primary:hover{background:#1d4ed8;}
.ra-btn-ghost{background:#f1f5f9;color:#374151;border:1px solid #e5e7eb;}
.ra-btn-ghost:hover{background:#e5e7eb;}

/* rubric checklist */
.ra-scenario{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;margin-bottom:20px;font-size:13px;color:#1e40af;white-space:pre-line;}
.dark .ra-scenario{background:#1e3a5f;border-color:#3b82f6;color:#bfdbfe;}
.ra-items{display:flex;flex-direction:column;gap:2px;}
.ra-item{display:flex;align-items:flex-start;gap:12px;padding:10px 12px;border-radius:8px;cursor:pointer;transition:background .12s;}
.ra-item:hover{background:#f1f5f9;}
.dark .ra-item:hover{background:#0f172a;}
.ra-item.done{background:#f0fdf4;}
.dark .ra-item.done{background:#052e16;}
.ra-item-num{flex-shrink:0;width:26px;height:26px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#6b7280;margin-top:1px;}
.ra-item.done .ra-item-num{background:#16a34a;color:#fff;}
.ra-item-text{font-size:13px;color:#374151;line-height:1.5;}
.dark .ra-item-text{color:#cbd5e1;}
.ra-item.done .ra-item-text{color:#15803d;}
.dark .ra-item.done .ra-item-text{color:#86efac;}

/* score bar */
.ra-score-bar{background:#f1f5f9;border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:16px;margin-bottom:20px;}
.dark .ra-score-bar{background:#0f172a;}
.ra-score-num{font-size:28px;font-weight:800;color:#111827;}
.dark .ra-score-num{color:#f1f5f9;}
.ra-score-label{font-size:12px;color:#6b7280;}
.ra-progress{flex:1;height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden;}
.ra-progress-fill{height:100%;border-radius:999px;transition:width .3s;}
.ra-badge{padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;letter-spacing:.3px;}
.ra-badge-pass{background:#dcfce7;color:#15803d;}
.ra-badge-fail{background:#fee2e2;color:#dc2626;}
.dark .ra-badge-pass{background:#052e16;color:#86efac;}
.dark .ra-badge-fail{background:#450a0a;color:#fca5a5;}
</style>

<div class="ra-wrap" wire:key="ra-root">

@if($isModuleLocked)
<div class="ra-card">
    <div style="padding:20px 24px;display:flex;align-items:flex-start;gap:12px;background:#f0fdf4;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#16a34a" style="width:22px;height:22px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
        <div>
            <p style="font-size:14px;font-weight:700;color:#15803d;margin:0;">Module Completed — Locked</p>
            <p style="font-size:13px;color:#166534;margin:4px 0 0;">This mentee's module is already complete. A new practical assessment can't be conducted until a mentor unlocks the module from the Review page.</p>
        </div>
    </div>
</div>
@endif

@if($step === 1)
{{-- ── STEP 1: SETUP ─────────────────────────────────────────────────────── --}}
<div class="ra-card">
    <div class="ra-card-head">
        <x-filament::icon icon="heroicon-o-clipboard-document-list" class="w-5 h-5 text-blue-600"/>
        <span class="ra-card-title">Assessment Setup</span>
    </div>
    <div class="ra-card-body">

        <div class="ra-field">
            <div class="ra-label">Rubric / Skill Being Assessed *</div>
            <select class="ra-select" wire:model.live="module_rubric_id">
                <option value="">— Select rubric —</option>
                @foreach($this->getRubrics() as $r)
                    <option value="{{ $r['id'] }}">{{ $r['label'] }}</option>
                @endforeach
            </select>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="ra-field">
                <div class="ra-label">Mentee Being Assessed *</div>
                <select class="ra-select" wire:model="mentee_id">
                    <option value="">— Select mentee —</option>
                    @foreach($this->getMentees() as $m)
                        <option value="{{ $m['id'] }}">{{ $m['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ra-field">
                <div class="ra-label">Assessor (Mentor) *</div>
                <select class="ra-select" wire:model="mentor_id">
                    <option value="">— Select mentor —</option>
                    @foreach($this->getMentors() as $m)
                        <option value="{{ $m['id'] }}">{{ $m['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ra-field">
                <div class="ra-label">Date & Time of Assessment *</div>
                <input type="datetime-local" class="ra-input" wire:model="assessed_at">
            </div>
            <div class="ra-field">
                <div class="ra-label">Notes / Observations</div>
                <input type="text" class="ra-input" wire:model="notes" placeholder="Optional — e.g. 2nd attempt, post-debrief">
            </div>
        </div>

        <div style="margin-top:8px;">
            <button class="ra-btn ra-btn-primary" wire:click="proceedToScoring" wire:loading.attr="disabled">
                <span wire:loading.remove>Continue to Scoring →</span>
                <span wire:loading>Loading rubric…</span>
            </button>
        </div>

    </div>
</div>

@elseif($step === 2 && $rubric)
{{-- ── STEP 2: SCORING ───────────────────────────────────────────────────── --}}
@php
    $score = $this->getScore();
    $total = $rubric->total_marks;
    $pct   = $total > 0 ? round(($score / $total) * 100, 1) : 0;
    $started = $score > 0;
    $pass  = $score >= $rubric->pass_marks;
    $fillColor = ! $started ? '#94a3b8' : ($pass ? '#16a34a' : ($pct >= 60 ? '#d97706' : '#dc2626'));
@endphp

{{-- Header info --}}
<div class="ra-card">
    <div class="ra-card-head" style="background:linear-gradient(135deg,#0f172a,#1e3a8a,#2563eb);border-radius:14px 14px 0 0;">
        <x-filament::icon icon="heroicon-o-clipboard-document-check" class="w-5 h-5" style="color:#93c5fd"/>
        <div>
            <div style="font-size:15px;font-weight:700;color:#fff;">{{ $rubric->title }}</div>
            <div style="font-size:12px;color:rgba(255,255,255,.65);">
                {{ $rubric->programModule->name }} &nbsp;·&nbsp;
                Pass: {{ $rubric->pass_marks }}/{{ $rubric->total_marks }} (≥ {{ $rubric->pass_percentage }}%)
                @if($menteeUser)
                    &nbsp;·&nbsp; Assessing: {{ $menteeUser->full_name }}
                @endif
            </div>
        </div>
    </div>
    <div class="ra-card-body" style="padding-bottom:16px;">

        {{-- Case Scenario --}}
        @if($rubric->case_scenario)
        <div style="margin-bottom:16px;">
            <div class="ra-label">Case Scenario</div>
            <div class="ra-scenario">{{ $rubric->case_scenario }}</div>
        </div>
        @endif

        {{-- Live score bar --}}
        <div class="ra-score-bar">
            <div>
                <div class="ra-score-num">{{ $score }}<span style="font-size:16px;font-weight:400;color:#6b7280;">/{{ $total }}</span></div>
                <div class="ra-score-label">items performed to standard</div>
            </div>
            <div class="ra-progress">
                <div class="ra-progress-fill" style="width:{{ $pct }}%;background:{{ $fillColor }};"></div>
            </div>
            <div>
                <div style="font-size:18px;font-weight:700;color:{{ $fillColor }};">{{ $pct }}%</div>
                @if($started)
                    <span class="ra-badge {{ $pass ? 'ra-badge-pass' : 'ra-badge-fail' }}">
                        {{ $pass ? 'PASS' : 'FAIL' }}
                    </span>
                @else
                    <span class="ra-badge" style="background:#f1f5f9;color:#64748b;">
                        NOT STARTED
                    </span>
                @endif
            </div>
        </div>

        {{-- Rubric checklist --}}
        <div class="ra-items">
            @foreach($rubric->items as $item)
            @php $done = $responses[$item->id] ?? false; @endphp
            <div class="ra-item {{ $done ? 'done' : '' }}" wire:click="toggleItem({{ $item->id }})">
                <div class="ra-item-num">
                    @if($done)
                        <x-filament::icon icon="heroicon-s-check" class="w-3 h-3"/>
                    @else
                        {{ $item->order_sequence }}
                    @endif
                </div>
                <div class="ra-item-text">{{ $item->description }}</div>
            </div>
            @endforeach
        </div>

        {{-- Debrief questions (mentor reference) --}}
        @if($rubric->debrief_questions)
        <div style="margin-top:24px;padding:14px;background:#fafafa;border:1px solid #e5e7eb;border-radius:10px;">
            <div class="ra-label" style="margin-bottom:8px;">Debrief Questions (Mentor Reference)</div>
            @foreach($rubric->debrief_questions as $i => $q)
            <div style="font-size:13px;color:#374151;padding:4px 0;">{{ $i + 1 }}. {{ $q }}</div>
            @endforeach
        </div>
        @endif

    </div>
</div>

{{-- Actions --}}
<div style="display:flex;gap:12px;margin-bottom:20px;">
    <button class="ra-btn ra-btn-primary" wire:click="submitAssessment" wire:loading.attr="disabled">
        <span wire:loading.remove>Save Assessment ({{ $started ? ($pass ? 'PASS' : 'FAIL') : 'NOT STARTED' }} · {{ $score }}/{{ $total }})</span>
        <span wire:loading>Saving…</span>
    </button>
    <button class="ra-btn ra-btn-ghost" wire:click="backToStep1">← Back</button>
</div>

@endif

</div>
</x-filament-panels::page>

<x-filament-panels::page>
<style>
.era-wrap{max-width:920px;margin:0 auto;}
.era-hero{background:linear-gradient(135deg,#0f172a,#1e3a8a,#2563eb);border-radius:16px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;gap:16px;}
.era-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;margin-bottom:20px;}
.dark .era-card{background:#1e293b;border-color:#334155;}
.era-head{padding:16px 24px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;gap:12px;}
.dark .era-head{border-color:#334155;}
.era-title{font-size:15px;font-weight:700;color:#111827;}
.dark .era-title{color:#f1f5f9;}
.era-body{padding:24px;}
.era-field{margin-bottom:18px;}
.era-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;margin-bottom:6px;}
.era-input{width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;color:#111827;background:#fff;outline:none;}
.dark .era-input{background:#0f172a;border-color:#334155;color:#f1f5f9;}
.era-input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);}
.era-btn{padding:10px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;}
.era-btn-primary{background:#2563eb;color:#fff;}
.era-btn-primary:hover{background:#1d4ed8;}
.era-btn-ghost{background:#f1f5f9;color:#374151;border:1px solid #e5e7eb;}
.era-btn-ghost:hover{background:#e5e7eb;}
.era-scenario{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;margin-bottom:20px;font-size:13px;color:#1e40af;white-space:pre-line;}
.era-items{display:flex;flex-direction:column;gap:2px;}
.era-item{display:flex;align-items:flex-start;gap:12px;padding:10px 12px;border-radius:8px;cursor:pointer;transition:background .12s;}
.era-item:hover{background:#f1f5f9;}
.dark .era-item:hover{background:#0f172a;}
.era-item.done{background:#f0fdf4;}
.dark .era-item.done{background:#052e16;}
.era-item-num{flex-shrink:0;width:26px;height:26px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#6b7280;margin-top:1px;}
.era-item.done .era-item-num{background:#16a34a;color:#fff;}
.era-item-text{font-size:13px;color:#374151;line-height:1.5;}
.dark .era-item-text{color:#cbd5e1;}
.era-item.done .era-item-text{color:#15803d;}
.dark .era-item.done .era-item-text{color:#86efac;}
.era-score-bar{background:#f1f5f9;border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:16px;margin-bottom:20px;}
.dark .era-score-bar{background:#0f172a;}
.era-score-num{font-size:28px;font-weight:800;color:#111827;}
.dark .era-score-num{color:#f1f5f9;}
.era-score-label{font-size:12px;color:#6b7280;}
.era-progress{flex:1;height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden;}
.era-progress-fill{height:100%;border-radius:999px;transition:width .3s;}
.era-badge{padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;letter-spacing:.3px;}
.era-badge-pass{background:#dcfce7;color:#15803d;}
.era-badge-fail{background:#fee2e2;color:#dc2626;}
</style>

<div class="era-wrap" wire:key="era-root">

    {{-- Hero --}}
    <div class="era-hero">
        <div style="width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <x-filament::icon icon="heroicon-o-pencil-square" class="w-6 h-6" style="color:#93c5fd"/>
        </div>
        <div style="flex:1;">
            <div style="font-size:16px;font-weight:700;color:#fff;">Editing Assessment</div>
            <div style="font-size:12px;color:rgba(255,255,255,.7);">
                {{ $record->mentee->full_name }} &nbsp;·&nbsp; {{ $rubric?->title }}
            </div>
            <div style="font-size:11px;color:rgba(255,255,255,.5);margin-top:3px;">
                Assessed by {{ $record->mentor->full_name }} &nbsp;·&nbsp; Original date: {{ $record->assessed_at->format('d M Y, H:i') }}
            </div>
        </div>
    </div>

    @if($rubric)
    @php
        $score = $this->getScore();
        $total = $rubric->total_marks;
        $pct   = $total > 0 ? round(($score / $total) * 100, 1) : 0;
        $started = $score > 0;
        $pass  = $score >= $rubric->pass_marks;
        $fillColor = ! $started ? '#94a3b8' : ($pass ? '#16a34a' : ($pct >= 60 ? '#d97706' : '#dc2626'));
    @endphp

    {{-- Notes & date --}}
    <div class="era-card">
        <div class="era-head">
            <x-filament::icon icon="heroicon-o-calendar-days" class="w-5 h-5 text-blue-500"/>
            <span class="era-title">Assessment Details</span>
        </div>
        <div class="era-body" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="era-field">
                <div class="era-label">Date & Time of Assessment</div>
                <input type="datetime-local" class="era-input" wire:model="assessed_at">
            </div>
            <div class="era-field">
                <div class="era-label">Notes / Observations</div>
                <input type="text" class="era-input" wire:model="notes" placeholder="Optional">
            </div>
        </div>
    </div>

    {{-- Header info --}}
    <div class="era-card">
        <div class="era-head" style="background:linear-gradient(135deg,#0f172a,#1e3a8a,#2563eb);border-radius:14px 14px 0 0;">
            <x-filament::icon icon="heroicon-o-clipboard-document-check" class="w-5 h-5" style="color:#93c5fd"/>
            <div>
                <div style="font-size:15px;font-weight:700;color:#fff;">{{ $rubric->title }}</div>
                <div style="font-size:12px;color:rgba(255,255,255,.65);">
                    {{ $rubric->programModule->name }} &nbsp;·&nbsp;
                    Pass: {{ $rubric->pass_marks }}/{{ $rubric->total_marks }} (≥ {{ $rubric->pass_percentage }}%)
                </div>
            </div>
        </div>
        <div class="era-body" style="padding-bottom:16px;">

            @if($rubric->case_scenario)
            <div style="margin-bottom:16px;">
                <div class="era-label">Case Scenario</div>
                <div class="era-scenario">{{ $rubric->case_scenario }}</div>
            </div>
            @endif

            {{-- Live score bar --}}
            <div class="era-score-bar">
                <div>
                    <div class="era-score-num">{{ $score }}<span style="font-size:16px;font-weight:400;color:#6b7280;">/{{ $total }}</span></div>
                    <div class="era-score-label">items performed to standard</div>
                </div>
                <div class="era-progress">
                    <div class="era-progress-fill" style="width:{{ $pct }}%;background:{{ $fillColor }};"></div>
                </div>
                <div>
                    <div style="font-size:18px;font-weight:700;color:{{ $fillColor }};">{{ $pct }}%</div>
                    @if($started)
                        <span class="era-badge {{ $pass ? 'era-badge-pass' : 'era-badge-fail' }}">
                            {{ $pass ? 'PASS' : 'FAIL' }}
                        </span>
                    @else
                        <span class="era-badge" style="background:#f1f5f9;color:#64748b;">
                            NOT STARTED
                        </span>
                    @endif
                </div>
            </div>

            {{-- Rubric checklist --}}
            <div class="era-items">
                @foreach($rubric->items as $item)
                @php $done = $responses[$item->id] ?? false; @endphp
                <div class="era-item {{ $done ? 'done' : '' }}" wire:click="toggleItem({{ $item->id }})">
                    <div class="era-item-num">
                        @if($done)
                            <x-filament::icon icon="heroicon-s-check" class="w-3 h-3"/>
                        @else
                            {{ $item->order_sequence }}
                        @endif
                    </div>
                    <div class="era-item-text">{{ $item->description }}</div>
                </div>
                @endforeach
            </div>

            @if($rubric->debrief_questions)
            <div style="margin-top:24px;padding:14px;background:#fafafa;border:1px solid #e5e7eb;border-radius:10px;">
                <div class="era-label" style="margin-bottom:8px;">Debrief Questions (Mentor Reference)</div>
                @foreach($rubric->debrief_questions as $i => $q)
                <div style="font-size:13px;color:#374151;padding:4px 0;">{{ $i + 1 }}. {{ $q }}</div>
                @endforeach
            </div>
            @endif

        </div>
    </div>

    {{-- Actions --}}
    <div style="display:flex;gap:12px;margin-bottom:20px;">
        <button class="era-btn era-btn-primary" wire:click="saveAssessment" wire:loading.attr="disabled">
            <span wire:loading.remove>Update Assessment ({{ $started ? ($pass ? 'PASS' : 'FAIL') : 'NOT STARTED' }} · {{ $score }}/{{ $total }})</span>
            <span wire:loading>Saving…</span>
        </button>
        <a href="{{ \App\Filament\Resources\RubricAssessmentResource::getUrl('view', ['record' => $record->id]) }}"
           class="era-btn era-btn-ghost" style="text-decoration:none;display:inline-flex;align-items:center;">
            ← Cancel
        </a>
    </div>

    @endif

</div>
</x-filament-panels::page>

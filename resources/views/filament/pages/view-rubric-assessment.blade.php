<x-filament-panels::page>
@php
$assessment = $this->record;
$rubric     = $assessment->rubric;
$pct        = $assessment->scorePercentage();
$fillColor  = $assessment->passed ? '#16a34a' : ($pct >= 60 ? '#d97706' : '#dc2626');
$responses  = $assessment->itemResponses->keyBy('rubric_item_id');
@endphp

<style>
.rv-wrap{max-width:860px;margin:0 auto;}
.rv-hero{background:linear-gradient(135deg,#0f172a,#1e3a8a,#2563eb);border-radius:16px;padding:24px;margin-bottom:20px;}
.rv-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;margin-bottom:16px;}
.dark .rv-card{background:#1e293b;border-color:#334155;}
.rv-head{padding:14px 20px;border-bottom:1px solid #e5e7eb;font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.4px;}
.dark .rv-head{border-color:#334155;color:#94a3b8;}
.rv-body{padding:20px;}
.rv-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.rv-field{display:flex;flex-direction:column;gap:3px;}
.rv-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:#9ca3af;}
.rv-value{font-size:14px;color:#111827;font-weight:500;}
.dark .rv-value{color:#f1f5f9;}
.rv-item{display:flex;align-items:flex-start;gap:10px;padding:8px 10px;border-radius:6px;}
.rv-item.done{background:#f0fdf4;}
.rv-item.miss{background:#fff7f7;}
.dark .rv-item.done{background:#052e16;}
.dark .rv-item.miss{background:#2d0a0a;}
.rv-dot{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;margin-top:2px;}
.rv-dot-yes{background:#16a34a;color:#fff;}
.rv-dot-no{background:#e5e7eb;color:#6b7280;}
.rv-dot-no-val{background:#dc2626;color:#fff;}
</style>

<div class="rv-wrap">

    {{-- Hero --}}
    <div class="rv-hero">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;">
            <div style="width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <x-filament::icon icon="heroicon-o-clipboard-document-check" class="w-7 h-7" style="color:#93c5fd"/>
            </div>
            <div>
                <div style="font-size:17px;font-weight:700;color:#fff;">{{ $assessment->mentee->full_name }}</div>
                <div style="font-size:13px;color:rgba(255,255,255,.7);">{{ $rubric->programModule->name }}</div>
                <div style="font-size:12px;color:rgba(255,255,255,.55);">Assessed by {{ $assessment->mentor->full_name }} · {{ $assessment->assessed_at->format('d M Y, H:i') }}</div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:16px;">
            <div style="font-size:36px;font-weight:800;color:#fff;">
                {{ $assessment->score }}<span style="font-size:18px;font-weight:400;color:rgba(255,255,255,.6);">/{{ $rubric->total_marks }}</span>
            </div>
            <div style="flex:1;height:10px;background:rgba(255,255,255,.15);border-radius:999px;overflow:hidden;">
                <div style="height:100%;border-radius:999px;background:{{ $fillColor }};width:{{ $pct }}%;"></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:20px;font-weight:700;color:{{ $fillColor }};">{{ $pct }}%</div>
                <span style="padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;background:{{ $assessment->passed ? '#16a34a' : '#dc2626' }};color:#fff;">
                    {{ $assessment->passed ? 'PASS' : 'FAIL' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Meta --}}
    <div class="rv-card">
        <div class="rv-head">Assessment Details</div>
        <div class="rv-body">
            <div class="rv-grid">
                <div class="rv-field">
                    <div class="rv-label">Rubric</div>
                    <div class="rv-value">{{ $rubric->title }}</div>
                </div>
                <div class="rv-field">
                    <div class="rv-label">Pass Mark</div>
                    <div class="rv-value">{{ $rubric->pass_marks }}/{{ $rubric->total_marks }} (≥ {{ $rubric->pass_percentage }}%)</div>
                </div>
                <div class="rv-field">
                    <div class="rv-label">Score Achieved</div>
                    <div class="rv-value" style="color:{{ $fillColor }};">{{ $assessment->score }}/{{ $rubric->total_marks }} ({{ $pct }}%)</div>
                </div>
                <div class="rv-field">
                    <div class="rv-label">Result</div>
                    <div class="rv-value" style="color:{{ $assessment->passed ? '#16a34a' : '#dc2626' }};font-weight:700;">
                        {{ $assessment->passed ? 'PASS' : 'FAIL' }}
                    </div>
                </div>
                @if($assessment->notes)
                <div class="rv-field" style="grid-column:span 2;">
                    <div class="rv-label">Notes</div>
                    <div class="rv-value">{{ $assessment->notes }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Rubric item responses --}}
    <div class="rv-card">
        <div class="rv-head">Item-by-Item Results</div>
        <div class="rv-body" style="display:flex;flex-direction:column;gap:4px;">
            @foreach($rubric->items as $item)
            @php $resp = $responses->get($item->id); $performed = $resp?->performed ?? false; @endphp
            <div class="rv-item {{ $performed ? 'done' : 'miss' }}">
                <div class="rv-dot {{ $performed ? 'rv-dot-yes' : 'rv-dot-no-val' }}">
                    @if($performed)
                        <x-filament::icon icon="heroicon-s-check" class="w-3 h-3"/>
                    @else
                        <x-filament::icon icon="heroicon-s-x-mark" class="w-3 h-3"/>
                    @endif
                </div>
                <div style="font-size:13px;color:{{ $performed ? '#15803d' : '#991b1b' }};line-height:1.5;">
                    <span style="font-weight:600;color:#9ca3af;margin-right:6px;">{{ $item->order_sequence }}.</span>
                    {{ $item->description }}
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Debrief --}}
    @if($rubric->debrief_questions)
    <div class="rv-card">
        <div class="rv-head">Debrief Questions</div>
        <div class="rv-body" style="display:flex;flex-direction:column;gap:8px;">
            @foreach($rubric->debrief_questions as $i => $q)
            <div style="font-size:13px;color:#374151;display:flex;gap:8px;">
                <span style="font-weight:700;color:#6b7280;">{{ $i + 1 }}.</span>
                <span>{{ $q }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
</x-filament-panels::page>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Executive Assessment Report — {{ $assessment->facility?->name ?? 'Unknown facility' }}</title>
@php
    $teal = '#0e7490';
    $navy = '#0f172a';
    $grade = $assessment->overall_grade ?? 'red';
    $gradeHex = ['green' => '#047857', 'yellow' => '#b45309', 'red' => '#b91c1c'][$grade] ?? '#b91c1c';
    $gradeLabel = strtoupper($assessment->overall_grade ?? 'Incomplete');
    $hex = fn ($g) => ['green' => '#047857', 'yellow' => '#b45309'][$g ?? 'red'] ?? '#b91c1c';
    $barHex = fn ($pct) => $pct >= 75 ? '#059669' : ($pct >= 50 ? '#d97706' : '#dc2626');
    $textHex = fn ($pct) => $pct >= 75 ? '#047857' : ($pct >= 50 ? '#b45309' : '#b91c1c');
    $tone = [
        'success' => ['bg' => '#ecfdf5', 'bar' => '#059669', 'txt' => '#064e3b', 'lbl' => '#047857'],
        'warning' => ['bg' => '#fffbeb', 'bar' => '#d97706', 'txt' => '#78350f', 'lbl' => '#b45309'],
        'danger'  => ['bg' => '#fef2f2', 'bar' => '#dc2626', 'txt' => '#7f1d1d', 'lbl' => '#b91c1c'],
        'info'    => ['bg' => '#eff6ff', 'bar' => '#2563eb', 'txt' => '#1e3a8a', 'lbl' => '#1d4ed8'],
    ];
    $facility = $assessment->facility;
@endphp
<style>
@page { margin: 70px 36px 55px 36px; }
* { box-sizing: border-box; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1e293b; line-height: 1.45; margin: 0; }
table { border-collapse: collapse; width: 100%; }
td, th { vertical-align: middle; }

/* running header / footer */
.run-header { position: fixed; top: -52px; left: 0; right: 0; height: 36px; border-bottom: 1.5px solid {{ $teal }}; }
.run-header td { font-size: 8px; color: #475569; padding-bottom: 5px; }
.run-footer { position: fixed; bottom: -38px; left: 0; right: 0; height: 24px; border-top: 1px solid #cbd5e1; padding-top: 6px; font-size: 7.5px; color: #475569; }

/* cover */
.cover { background: {{ $navy }}; color: #fff; padding: 22px 24px 18px; border-bottom: 5px solid {{ $teal }}; }
.cover-eyebrow { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #67e8f9; font-weight: bold; }
.cover-title { font-size: 20px; font-weight: bold; margin: 5px 0 3px; color: #ffffff; }
.cover-sub { font-size: 10.5px; color: #e2e8f0; }
.cover-meta td { font-size: 8.5px; color: #e2e8f0; padding-top: 12px; }
.cover-meta b { color: #ffffff; }
.grade-box { text-align: center; background: #ffffff; border-radius: 6px; padding: 10px 6px; }
.grade-pct { font-size: 25px; font-weight: bold; }
.grade-lbl { font-size: 7.5px; letter-spacing: 1px; text-transform: uppercase; color: #475569; margin-top: 2px; }
.grade-badge { font-size: 8.5px; font-weight: bold; color: #fff; padding: 2px 10px; border-radius: 10px; }

/* score strip */
.strip td.cell { background: #f8fafc; border: 1px solid #cbd5e1; border-top: 4px solid; text-align: center; padding: 8px 4px; }
.strip .pct { font-size: 15px; font-weight: bold; }
.strip .nm { font-size: 7.5px; text-transform: uppercase; letter-spacing: .4px; color: #334155; margin-top: 2px; }

/* sections */
.section { margin-top: 18px; }
.sec-head { background: #f1f5f9; border-left: 5px solid {{ $teal }}; padding: 7px 10px; page-break-after: avoid; }
.sec-title { font-size: 12.5px; font-weight: bold; color: {{ $navy }}; }
.sec-sub { font-size: 8px; color: #475569; margin-top: 1px; }
.sec-pill { font-size: 9px; font-weight: bold; color: #fff; padding: 3px 9px; border-radius: 10px; }
.sub-title { font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: .7px; color: #334155; margin: 12px 0 5px; page-break-after: avoid; }

/* insights */
.insight { margin-bottom: 6px; page-break-inside: avoid; }
.insight td { padding: 7px 10px; }
.insight .area { font-size: 7.5px; font-weight: bold; text-transform: uppercase; letter-spacing: .6px; }
.insight .txt { font-size: 9px; margin-top: 2px; line-height: 1.5; }

/* data tables */
.dt { margin-top: 2px; }
.dt thead { display: table-header-group; }
.dt th { background: {{ $navy }}; color: #fff; font-size: 8px; text-transform: uppercase; letter-spacing: .4px; padding: 6px 7px; text-align: left; }
.dt td { padding: 5px 7px; border-bottom: 1px solid #e2e8f0; font-size: 9px; color: #1e293b; }
.dt tr { page-break-inside: avoid; }
.dt tbody tr.alt td { background: #f8fafc; }
.r { text-align: right !important; }
.c { text-align: center !important; }
.pill { font-size: 8px; font-weight: bold; padding: 2px 8px; border-radius: 9px; }
.p-green { background: #d1fae5; color: #065f46; }
.p-red { background: #fee2e2; color: #991b1b; }
.p-gray { background: #e2e8f0; color: #334155; }

/* bars */
.bar-bg { background: #e2e8f0; height: 9px; border-radius: 5px; }
.bar-fill { height: 9px; border-radius: 5px; }
.bars td { padding: 3px 0; font-size: 9px; }

/* stat chips */
.chip { border: 1px solid #cbd5e1; background: #f8fafc; text-align: center; padding: 7px 4px; }
.chip .v { font-size: 15px; font-weight: bold; }
.chip .l { font-size: 7.5px; text-transform: uppercase; letter-spacing: .4px; color: #334155; }

.note { font-size: 8.5px; color: #475569; font-style: italic; padding: 10px 0; }
.pb { page-break-before: always; }
</style>
</head>
<body>

{{-- Running header / footer --}}
<div class="run-header">
    <table><tr>
        <td style="font-weight:bold;color:{{ $teal }};">MNCH MENTORSHIP PROGRAMME &middot; KENYA</td>
        <td class="r">{{ $facility?->name ?? 'Unknown facility' }} &middot; Executive Assessment Report</td>
    </tr></table>
</div>
<div class="run-footer">
    <table><tr>
        <td>Confidential &middot; Generated {{ now()->format('d M Y, H:i') }}</td>
        <td class="r"></td>
    </tr></table>
</div>
<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
        $w = $fontMetrics->getTextWidth($text, $font, 7.5);
        $pdf->page_text($pdf->get_width() - 36 - $w - 6, $pdf->get_height() - 38, $text, $font, 7.5, [0.28, 0.33, 0.41]);
    }
</script>

{{-- ── Cover ── --}}
<div class="cover">
    <table>
        <tr>
            <td style="width:73%;">
                <div class="cover-eyebrow">Executive Assessment Report</div>
                <div class="cover-title">{{ $facility?->name ?? 'Unknown facility' }}</div>
                <div class="cover-sub">{{ ucfirst($assessment->assessment_type) }} Assessment &middot; {{ $assessment->assessment_date->format('d M Y') }}</div>
            </td>
            <td style="width:27%;">
                <div class="grade-box">
                    <div class="grade-pct" style="color:{{ $gradeHex }};">{{ number_format($assessment->overall_percentage ?? 0, 1) }}%</div>
                    <div class="grade-lbl">Overall Score</div>
                    <div style="margin-top:6px;"><span class="grade-badge" style="background:{{ $gradeHex }};">{{ $gradeLabel }}</span></div>
                </div>
            </td>
        </tr>
    </table>
    <table class="cover-meta">
        <tr>
            <td><b>County</b><br>{{ $facility?->subcounty?->county?->name ?? '—' }}</td>
            <td><b>Subcounty</b><br>{{ $facility?->subcounty?->name ?? '—' }}</td>
            <td><b>Facility level</b><br>{{ $facility?->facilityLevel?->name ?? '—' }}</td>
            <td><b>Assessor</b><br>{{ $assessment->assessor_name ?? '—' }}</td>
            <td><b>Sections scored</b><br>{{ $sectionScores->count() }}</td>
        </tr>
    </table>
</div>

{{-- ── Section score strip ── --}}
@if($sectionScores->isNotEmpty())
@php $perRow = min(5, $sectionScores->count()); $stripRows = $sectionScores->values()->chunk($perRow); $cw = round(100 / $perRow, 2); @endphp
<div style="margin-top:12px;">
@foreach($stripRows as $chunk)
<table class="strip" style="margin-bottom:0;">
    <tr>
        @foreach($chunk as $ss)
        <td class="cell" style="border-top-color:{{ $hex($ss->grade) }};width:{{ $cw }}%;">
            <div class="pct" style="color:{{ $hex($ss->grade) }};">{{ number_format($ss->percentage, 1) }}%</div>
            <div class="nm">{{ \Illuminate\Support\Str::limit($ss->name, 32) }}</div>
        </td>
        @endforeach
        @for($i = $chunk->count(); $i < $perRow; $i++)<td style="width:{{ $cw }}%;"></td>@endfor
    </tr>
</table>
@endforeach
</div>
@endif

{{-- ── Executive insights ── --}}
@php
    $renderInsights = function ($list) use ($tone) {
        foreach ($list as $i) {
            $t = $tone[$i['type']] ?? $tone['info'];
            echo '<table class="insight" style="background:'.$t['bg'].';border-left:4px solid '.$t['bar'].';"><tr><td>'
                .'<div class="area" style="color:'.$t['lbl'].';">'.e($i['area']).'</div>'
                .'<div class="txt" style="color:'.$t['txt'].';">'.e($i['text']).'</div>'
                .'</td></tr></table>';
        }
    };
@endphp
<div class="section">
    <div class="sec-head">
        <table><tr>
            <td><div class="sec-title">Executive Insights</div><div class="sec-sub">Strategic findings for leadership decision-making</div></td>
        </tr></table>
    </div>
    <div style="margin-top:8px;">
        @php $renderInsights($insights); @endphp
        @if(!empty($execIndicatorInsights))
        <div class="sub-title">Paediatric Indicators</div>
        @php $renderInsights($execIndicatorInsights); @endphp
        @endif
    </div>
</div>

{{-- ── Skills Lab ── --}}
<div class="section">
    <div class="sec-head">
        <table><tr>
            <td><div class="sec-title">Skills Lab</div><div class="sec-sub">Simulation &amp; training equipment readiness</div></td>
            @if($sectionScores->has('skills_lab'))
            @php $ss = $sectionScores->get('skills_lab'); @endphp
            <td class="r" style="width:80px;"><span class="sec-pill" style="background:{{ $hex($ss->grade) }};">{{ number_format($ss->percentage, 1) }}%</span></td>
            @endif
        </tr></table>
    </div>
    <div style="margin-top:8px;">
        <table style="background:{{ $hasDedicatedLab ? '#ecfdf5' : '#fef2f2' }};border-left:4px solid {{ $hasDedicatedLab ? '#059669' : '#dc2626' }};margin-bottom:8px;">
            <tr><td style="padding:7px 10px;font-weight:bold;font-size:9.5px;color:{{ $hasDedicatedLab ? '#064e3b' : '#7f1d1d' }};">
                Dedicated Skills Lab: {{ $hasDedicatedLab ? 'Present' : 'Not Present' }}
                @if(!$hasDedicatedLab)
                @php $hasRoom = $skillsResponses->firstWhere('question_code', 'SKILLS_NO_ROOM_SPACE'); @endphp
                <span style="font-weight:normal;"> &middot; Alternative room/space: {{ ($hasRoom && $hasRoom->response_value === 'Yes') ? 'Available' : 'Not available' }}</span>
                @endif
            </td></tr>
        </table>
        @if($skillsResponses->isNotEmpty())
        <table class="dt">
            <thead><tr><th>Equipment / Item</th><th class="c" style="width:90px;">Status</th></tr></thead>
            <tbody>
                @foreach($skillsResponses as $item)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td>{{ $item->question_text }}</td>
                    <td class="c">
                        @if($item->response_value === 'Yes') <span class="pill p-green">Yes</span>
                        @elseif($item->response_value === 'No') <span class="pill p-red">No</span>
                        @else <span class="pill p-gray">{{ $item->response_value ?? 'Not answered' }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

{{-- ── Human Resources ── --}}
<div class="section">
    <div class="sec-head">
        <table><tr>
            <td><div class="sec-title">Human Resources</div><div class="sec-sub">Staff composition and specialist training coverage</div></td>
            @php $hrGrade = $hrCoverage >= 80 ? 'green' : ($hrCoverage >= 50 ? 'yellow' : 'red'); @endphp
            <td class="r" style="width:110px;"><span class="sec-pill" style="background:{{ $hex($hrGrade) }};">{{ $hrCoverage }}% trained</span></td>
        </tr></table>
    </div>
    <div style="margin-top:8px;">
        @if($hrRows->isNotEmpty())
        <table style="margin-bottom:10px;">
            <tr>
                <td class="chip" style="width:33%;"><div class="v" style="color:{{ $teal }};">{{ number_format($totalStaff) }}</div><div class="l">Total staff</div></td>
                <td class="chip" style="width:33%;"><div class="v" style="color:#047857;">{{ number_format($totalTrained) }}</div><div class="l">Trained</div></td>
                <td class="chip" style="width:33%;"><div class="v" style="color:{{ $textHex($hrCoverage) }};">{{ $hrCoverage }}%</div><div class="l">Coverage</div></td>
            </tr>
        </table>

        <table class="dt">
            <thead>
                <tr>
                    <th>Cadre</th>
                    <th class="r">Total</th><th class="r">ETAT+</th><th class="r">Comp. NB</th>
                    <th class="r">IMNCI</th><th class="r">T1 DM</th><th class="r">Ess. NB</th><th class="r">Coverage</th>
                </tr>
            </thead>
            <tbody>
                @foreach($hrRows as $row)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td style="font-weight:bold;">{{ $row->cadre }}</td>
                    <td class="r">{{ $row->total_in_facility }}</td>
                    <td class="r">{{ $row->etat_plus }}</td>
                    <td class="r">{{ $row->comprehensive_newborn_care }}</td>
                    <td class="r">{{ $row->imnci }}</td>
                    <td class="r">{{ $row->type_1_diabetes }}</td>
                    <td class="r">{{ $row->essential_newborn_care }}</td>
                    <td class="r" style="font-weight:bold;color:{{ $row->coverage_pct >= 60 ? '#047857' : ($row->coverage_pct >= 30 ? '#b45309' : '#b91c1c') }};">{{ $row->coverage_pct }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="font-size:7.5px;color:#475569;margin-top:4px;">NB = Newborn &middot; DM = Diabetes Mellitus</div>
        @else
        <div class="note">No HR data recorded for this assessment.</div>
        @endif
    </div>
</div>

{{-- ── Health Products ── --}}
<div class="section">
    <div class="sec-head">
        <table><tr>
            <td><div class="sec-title">Health Products &amp; Technologies</div><div class="sec-sub">Commodity availability across clinical departments</div></td>
            @php $hpGrade = $overallCommodityPct >= 80 ? 'green' : ($overallCommodityPct >= 50 ? 'yellow' : 'red'); @endphp
            <td class="r" style="width:110px;"><span class="sec-pill" style="background:{{ $hex($hpGrade) }};">{{ $overallCommodityPct }}% overall</span></td>
        </tr></table>
    </div>
    <div style="margin-top:4px;">
        @if($deptScores->isNotEmpty())
        @foreach([['Availability by Department', $deptScores, 'department', 30], ['Availability by Commodity Category', $categoryScores, 'category', 26]] as [$ttl, $coll, $key, $lim])
        @if($coll->isNotEmpty())
        <div class="sub-title">{{ $ttl }}</div>
        <table class="bars">
            @foreach($coll as $r)
            @php $p = max(0, min(100, (float) $r->percentage)); @endphp
            <tr>
                <td style="width:30%;color:#1e293b;">{{ \Illuminate\Support\Str::limit($r->{$key}, $lim) }}</td>
                <td style="width:56%;padding-right:8px;"><div class="bar-bg"><div class="bar-fill" style="width:{{ $p }}%;background:{{ $barHex($p) }};"></div></div></td>
                <td class="r" style="width:14%;font-weight:bold;color:{{ $textHex($p) }};">{{ $p }}%</td>
            </tr>
            @endforeach
        </table>
        @endif
        @endforeach
        @else
        <div class="note">No commodity data recorded for this assessment.</div>
        @endif
    </div>
</div>

{{-- ── Quality of Care (Death Audits) ── --}}
@php
    $auditItems = $qocAll->keys()
        ->filter(fn ($c) => preg_match('/^QOC_(NEONATAL|CHILD)_|^QOC_AUDIT_/', $c) === 1)
        ->values()->all();
    $newbornStats = ['QOC_NEWBORN_ADMISSIONS','QOC_PRETERMS_34','QOC_PRETERM_ASPHYXIA','QOC_CPAP_PLACED','QOC_APNOEA','QOC_CAFFEINE','QOC_NEWBORNS_34PLUS','QOC_ASPHYXIA_34PLUS','QOC_HYPOTHERMIA','QOC_O2_SAT_TAKEN','QOC_RBS_TAKEN','QOC_HEAD_TO_TOE'];
    $paedStats = ['QOC_PAED_ADMISSIONS','QOC_PAED_O2_SAT','QOC_PAED_LOW_O2','QOC_PAED_O2_STARTED','QOC_PAED_RBS','QOC_PAED_HYPERGLYCEMIC'];
    $hasNewborn = collect($newbornStats)->first(fn ($c) => $qocAll->has($c) && $qocAll->get($c)->response_value !== null);
    $hasPaed = collect($paedStats)->first(fn ($c) => $qocAll->has($c) && $qocAll->get($c)->response_value !== null);
    $shownCodes = array_merge($auditItems, $newbornStats, $paedStats);
    $otherAnswered = $qocAll->filter(fn ($i) => ! in_array($i->question_code, $shownCodes, true) && $i->response_value !== null && $i->response_value !== '');
    $yesNo = function ($val, $code = null) {
        if ($val === 'Yes') return '<span class="pill p-green">Yes</span>';
        if ($val === 'No') return '<span class="pill p-red">No</span>';
        if ($val !== null && $val !== '') return '<span class="pill p-gray">'.e($val).'</span>';
        return '<span class="pill p-gray">Not answered</span>';
    };
@endphp
<div class="section" style="page-break-inside:avoid;">
    <div class="sec-head">
        <table><tr>
            <td><div class="sec-title">Quality of Care (Death Audits)</div><div class="sec-sub">Death audits and clinical outcome indicators</div></td>
            @if($sectionScores->has('quality_of_care'))
            @php $ss = $sectionScores->get('quality_of_care'); @endphp
            <td class="r" style="width:80px;"><span class="sec-pill" style="background:{{ $hex($ss->grade) }};">{{ number_format($ss->percentage, 1) }}%</span></td>
            @endif
        </tr></table>
    </div>
    <div style="margin-top:4px;">
        @if(!empty($auditItems))
        <div class="sub-title">Audit Practices</div>
        <table class="dt">
            <thead><tr><th>Question</th><th class="c" style="width:90px;">Response</th></tr></thead>
            <tbody>
                @php $n = 0; @endphp
                @foreach($auditItems as $code)
                @if($qocAll->has($code))
                @php $item = $qocAll->get($code); @endphp
                <tr class="{{ ($n++ % 2) ? 'alt' : '' }}">
                    <td>{{ $item->question_text }}</td>
                    <td class="c">{!! $yesNo($item->response_value) !!}</td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
        @endif

        @foreach([[$hasNewborn, 'Newborn Clinical Indicators (3-month snapshot)', $newbornStats], [$hasPaed, 'Paediatric Clinical Indicators', $paedStats]] as [$has, $ttl, $codes])
        @if($has)
        <div class="sub-title">{{ $ttl }}</div>
        <table class="dt">
            <thead><tr><th>Indicator</th><th class="r" style="width:70px;">Count</th></tr></thead>
            <tbody>
                @php $n = 0; @endphp
                @foreach($codes as $code)
                @if($qocAll->has($code))
                @php $item = $qocAll->get($code); @endphp
                <tr class="{{ ($n++ % 2) ? 'alt' : '' }}">
                    <td>{{ \Illuminate\Support\Str::limit($item->question_text, 100) }}</td>
                    <td class="r" style="font-weight:bold;">{{ $item->response_value ?? '—' }}</td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
        @endif
        @endforeach

        @if($otherAnswered->isNotEmpty())
        <div class="sub-title">Other Responses</div>
        <table class="dt">
            <thead><tr><th>Question</th><th class="c" style="width:110px;">Response</th></tr></thead>
            <tbody>
                @foreach($otherAnswered as $item)
                <tr class="{{ $loop->even ? 'alt' : '' }}">
                    <td>{{ $item->question_text }}</td>
                    <td class="c">@if(in_array($item->response_value, ['Yes', 'No'], true)) {!! $yesNo($item->response_value) !!} @else <b>{{ $item->response_value }}</b> @endif</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

{{-- ── Newborn & Paediatric Indicators ── --}}
@if($indicatorMetrics->isNotEmpty())
<div class="section">
    <div class="sec-head">
        <div class="sec-title">Newborn &amp; Paediatric Indicators</div>
        <div class="sec-sub">Proportions calculated from the file-review counts in the assessment summary</div>
    </div>
    @foreach(['Newborn' => 'Newborn Indicators', 'Paediatric' => 'Paediatric Indicators'] as $group => $heading)
    @php $rows = $indicatorMetrics->where('group', $group); @endphp
    @if($rows->isNotEmpty())
    <div class="sub-title">{{ $heading }}</div>
    <table class="dt">
        <thead>
            <tr>
                <th>Indicator</th>
                <th class="r" style="width:62px;">Count</th>
                <th style="width:130px;">Performance</th>
                @if($previousRoundLabel)<th class="r" style="width:70px;">vs prev.</th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $m)
            @php
                $color = ['good' => '#059669', 'warning' => '#d97706', 'danger' => '#dc2626'][$m['status']] ?? '#64748b';
                $txtColor = ['good' => '#047857', 'warning' => '#b45309', 'danger' => '#b91c1c'][$m['status']] ?? '#475569';
                $lower = $m['direction'] === 'lower';
            @endphp
            <tr class="{{ $loop->even ? 'alt' : '' }}">
                <td>{{ $m['short'] }}@if($lower) <span style="color:#475569;font-size:7.5px;">(lower is better)</span>@endif</td>
                <td class="r">@if($m['pct'] !== null){{ number_format($m['numerator']) }}/{{ number_format($m['denominator']) }}@else — @endif</td>
                <td>
                    @if($m['pct'] !== null)
                    <table><tr>
                        <td style="padding:0;width:62%;"><div class="bar-bg"><div class="bar-fill" style="width:{{ min($m['pct'], 100) }}%;background:{{ $color }};"></div></div></td>
                        <td class="r" style="padding:0 0 0 4px;border:0;font-weight:bold;color:{{ $txtColor }};">{{ $m['pct'] }}%</td>
                    </tr></table>
                    @else
                    <span class="pill p-gray">N/A</span>
                    @endif
                </td>
                @if($previousRoundLabel)
                @php
                    $d = $m['delta'] ?? null;
                    $improving = $d !== null && ($lower ? $d < 0 : $d > 0);
                    $dColor = $d === null || abs($d) < 2 ? '#475569' : ($improving ? '#047857' : '#b91c1c');
                @endphp
                <td class="r" style="font-weight:bold;color:{{ $dColor }};white-space:nowrap;">
                    @if($d === null) — @else {{ $d > 0 ? '+' : '' }}{{ $d }} pts @endif
                </td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
    @endforeach
    @if($previousRoundLabel)
    <div style="font-size:7.5px;color:#475569;margin-top:4px;">Change compared with {{ $previousRoundLabel }}.</div>
    @endif
</div>
@endif

</body>
</html>

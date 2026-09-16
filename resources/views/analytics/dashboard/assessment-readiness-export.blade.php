<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1f2937; background: #fff; }

    /* Header */
    .report-header { border-bottom: 3px solid #0097A7; padding-bottom: 10px; margin-bottom: 14px; }
    .report-header-inner { display: table; width: 100%; }
    .header-left  { display: table-cell; vertical-align: middle; }
    .header-right { display: table-cell; vertical-align: middle; text-align: right; white-space: nowrap; }
    .report-title { font-size: 16pt; font-weight: bold; color: #0097A7; line-height: 1.2; }
    .report-subtitle { font-size: 9pt; color: #6b7280; margin-top: 2px; }
    .header-meta { font-size: 8pt; color: #6b7280; line-height: 1.6; }
    .filter-badge { display: inline-block; background: #e0f7fa; color: #006064; border-radius: 4px; padding: 1px 6px; font-size: 7.5pt; margin-left: 4px; }

    /* KPI strip */
    .kpi-row { display: table; width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .kpi-cell { display: table-cell; width: 16.66%; padding: 8px 6px; text-align: center; background: #f0fdfe; border: 1px solid #cffafe; border-radius: 4px; }
    .kpi-value { font-size: 14pt; font-weight: bold; color: #0097A7; }
    .kpi-label { font-size: 7pt; color: #6b7280; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.4px; }
    .kpi-sub   { font-size: 7pt; color: #10b981; margin-top: 1px; }

    /* Section heading */
    .section-heading { font-size: 10pt; font-weight: bold; color: #0097A7; border-left: 3px solid #0097A7; padding-left: 8px; margin-bottom: 8px; }

    /* Table */
    table.main { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
    table.main thead tr { background: #0097A7; color: #fff; }
    table.main thead th { padding: 5px 6px; text-align: left; font-weight: 600; font-size: 7.5pt; }
    table.main tbody tr:nth-child(even) { background: #f0fdfe; }
    table.main tbody tr:nth-child(odd)  { background: #ffffff; }
    table.main tbody td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    .facility-name { font-weight: 600; color: #111827; }
    .mfl-code { font-size: 7pt; color: #6b7280; }
    .geo-sub  { font-size: 7.5pt; color: #374151; }
    .geo-county { font-size: 7pt; color: #9ca3af; }

    /* Badges */
    .badge { display: inline-block; padding: 1px 5px; border-radius: 4px; font-size: 7pt; font-weight: 600; }
    .badge-teal   { background: #cffafe; color: #0e7490; }
    .badge-base   { background: #cffafe; color: #0e7490; }
    .badge-mid    { background: #fef3c7; color: #92400e; }
    .badge-end    { background: #ede9fe; color: #5b21b6; }
    .badge-yes    { background: #d1fae5; color: #065f46; }
    .badge-no     { background: #fee2e2; color: #991b1b; }
    .badge-eligible     { background: #d1fae5; color: #065f46; }
    .badge-partial      { background: #fef3c7; color: #92400e; }
    .badge-not_eligible { background: #fee2e2; color: #991b1b; }
    .badge-feedback { background: #d1fae5; color: #065f46; }
    .badge-pending  { background: #fef3c7; color: #92400e; }
    .mentorship-count { font-weight: 700; color: #0097A7; }

    /* Footer */
    .report-footer { margin-top: 14px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 7pt; color: #9ca3af; display: table; width: 100%; }
    .footer-left  { display: table-cell; }
    .footer-right { display: table-cell; text-align: right; }

    .no-data { text-align: center; padding: 20px; color: #9ca3af; font-style: italic; }
    .page-break { page-break-before: always; }
</style>
</head>
<body>

{{-- ── HEADER ── --}}
<div class="report-header">
    <div class="report-header-inner">
        <div class="header-left">
            <div class="report-title">Facilities Readiness &amp; Mentorship Eligibility</div>
            <div class="report-subtitle">MNCH Mentorship Platform — Assessment Analytics Report</div>
        </div>
        <div class="header-right">
            <div class="header-meta">
                <strong>Generated:</strong> {{ $generatedAt }}<br>
                <strong>Filters applied:</strong>
                @if($selectedYear)
                    <span class="filter-badge">Year: {{ $selectedYear }}</span>
                @else
                    <span class="filter-badge">All Years</span>
                @endif
                @if($selectedCounty)
                    <span class="filter-badge">{{ $selectedCounty }} County</span>
                @endif
                @if($selectedAssessmentType)
                    <span class="filter-badge">{{ ucfirst($selectedAssessmentType) }}</span>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── KPI SUMMARY ── --}}
@php
    $uniqueAssessments  = $summaryStats['uniqueAssessments']        ?? 0;
    $facilitiesAssessed = $summaryStats['facilitiesAssessed']        ?? 0;
    $avgScore           = $summaryStats['avgScore']                  ?? 0;
    $withSkillsLab      = $summaryStats['withSkillsLab']             ?? 0;
    $eligible           = $summaryStats['eligible']                  ?? 0;
    $withMentorships    = $summaryStats['withMentorships']           ?? 0;
    $mentorshipCoverage = $summaryStats['mentorshipCoverage']        ?? 0;
    $facilityCoverage   = $summaryStats['facilityCoveragePercent']   ?? 0;
@endphp

<div class="kpi-row">
    <div class="kpi-cell">
        <div class="kpi-value">{{ number_format($uniqueAssessments) }}</div>
        <div class="kpi-label">Assessments</div>
        <div class="kpi-sub">{{ $facilityCoverage }}% coverage</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-value">{{ number_format($facilitiesAssessed) }}</div>
        <div class="kpi-label">Facilities Assessed</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-value">{{ $avgScore }}%</div>
        <div class="kpi-label">Avg Score</div>
        <div class="kpi-sub">{{ $avgScore >= 80 ? 'Good' : ($avgScore >= 50 ? 'Fair' : 'Needs Work') }}</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-value">{{ number_format($withSkillsLab) }}</div>
        <div class="kpi-label">Have Skills Lab</div>
        @if($facilitiesAssessed > 0)
            <div class="kpi-sub">{{ round(($withSkillsLab / $facilitiesAssessed) * 100) }}% of assessed</div>
        @endif
    </div>
    <div class="kpi-cell">
        <div class="kpi-value">{{ number_format($eligible) }}</div>
        <div class="kpi-label">Eligible</div>
        <div class="kpi-sub">for mentorship</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-value">{{ number_format($withMentorships) }}</div>
        <div class="kpi-label">With Mentorships</div>
        <div class="kpi-sub">{{ $mentorshipCoverage }}% coverage</div>
    </div>
</div>

{{-- ── TABLE ── --}}
<div class="section-heading">Facility-Level Readiness Detail ({{ $facilitiesReadiness->count() }} facilities)</div>

@if($facilitiesReadiness->isEmpty())
    <div class="no-data">No assessed facilities found for the selected filters.</div>
@else
<table class="main">
    <thead>
        <tr>
            <th style="width:15%">Facility</th>
            <th style="width:10%">Subcounty / County</th>
            <th style="width:6%">Level</th>
            <th style="width:9%">Assessment Date</th>
            <th style="width:6%">Type</th>
            <th style="width:9%">Assessed By</th>
            <th style="width:6%">Skills Lab</th>
            <th style="width:6%">Room</th>
            <th style="width:9%">Assessment Feedback</th>
            <th style="width:8%">Training Eligibility</th>
            <th style="width:11%">Has Been Trained</th>
            <th style="width:5%">Mentorships</th>
        </tr>
    </thead>
    <tbody>
        @foreach($facilitiesReadiness as $assessment)
        @php
            $typeClass = match($assessment->assessment_type) {
                'baseline' => 'badge-base',
                'midline'  => 'badge-mid',
                'endline'  => 'badge-end',
                default    => 'badge-teal',
            };
            $eligLabel = match($assessment->eligibility_status) {
                'eligible'     => 'Eligible',
                'partial'      => 'Partial',
                default        => 'Not Eligible',
            };
            $eligClass = 'badge-' . $assessment->eligibility_status;
        @endphp
        <tr>
            <td>
                <div class="facility-name">{{ $assessment->facility->name }}</div>
                <div class="mfl-code">MFL: {{ $assessment->facility->mfl_code ?? '—' }}</div>
            </td>
            <td>
                <div class="geo-sub">{{ $assessment->facility->subcounty->name ?? '—' }}</div>
                <div class="geo-county">{{ $assessment->facility->subcounty->county->name ?? '—' }}</div>
            </td>
            <td>
                @if($assessment->facility->facilityLevel)
                    <span class="badge badge-teal">{{ $assessment->facility->facilityLevel->name }}</span>
                @else
                    —
                @endif
            </td>
            <td>{{ $assessment->assessment_date->format('d M Y') }}</td>
            <td><span class="badge {{ $typeClass }}">{{ ucfirst($assessment->assessment_type) }}</span></td>
            <td>{{ $assessment->assessor_name ?? '—' }}</td>
            <td>
                @if($assessment->has_skills_lab)
                    <span class="badge badge-yes">Yes</span>
                @else
                    <span class="badge badge-no">No</span>
                @endif
            </td>
            <td>
                @if($assessment->has_skills_lab)
                    <span style="color:#9ca3af">N/A</span>
                @elseif($assessment->has_room)
                    <span class="badge badge-yes">Yes</span>
                @else
                    <span class="badge badge-no">No</span>
                @endif
            </td>
            <td>
                @if($assessment->feedback_given)
                    <span class="badge badge-feedback">Given</span>
                    @if($assessment->feedback_given_at)
                        <div class="mfl-code">{{ $assessment->feedback_given_at->format('d M Y') }}</div>
                    @endif
                @else
                    <span class="badge badge-pending">Pending</span>
                @endif
            </td>
            <td><span class="badge {{ $eligClass }}">{{ $eligLabel }}</span></td>
            <td>
                @if($assessment->eligibility_status !== 'eligible')
                    <span class="badge" style="background:#e5e7eb;color:#6b7280">Not Eligible</span>
                @elseif($assessment->has_prior_training)
                    <span class="badge badge-yes">Yes</span>
                @else
                    <span class="badge badge-no">No</span>
                @endif
            </td>
            <td>
                @if($assessment->mentorship_count > 0)
                    <span class="mentorship-count">{{ $assessment->mentorship_count }}</span>
                @else
                    <span style="color:#9ca3af">0</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ── FOOTER ── --}}
<div class="report-footer">
    <div class="footer-left">MNCH Mentorship Platform &nbsp;|&nbsp; Facilities Readiness Report</div>
    <div class="footer-right">Generated {{ $generatedAt }} &nbsp;|&nbsp; Confidential</div>
</div>

</body>
</html>

{{-- resources/views/reports/assessment-html-report.blade.php --}}


<div class="report-container">
    {{-- Header --}}
    <div class="report-header" style="text-align: center; margin-bottom: 30px;">
        <h1 style="color: #1f2937; margin-bottom: 8px;">MNCH BASELINE ASSESSMENT</h1>
        <h2 style="color: #4b5563; font-size: 18px; font-weight: normal;">{{ $facilityInfo['name'] }}</h2>
        <p style="color: #6b7280; margin-top: 8px;">Assessment Date: {{ $assessment->assessment_date->format('F d, Y') }}</p>
    </div>



    {{-- Facility Information --}}
    <div class="section" style="margin-bottom: 32px;">
        <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Facility Information</h2>
        <div style="background: #f9fafb; padding: 16px; border-radius: 6px;">
            <div class="info-row" style="display: flex; padding: 6px 0;">
                <span class="info-label" style="font-weight: 600; width: 200px; color: #6b7280;">Facility Name:</span>
                <span class="info-value" style="color: #1f2937;">{{ $facilityInfo['name'] }}</span>
            </div>
            <div class="info-row" style="display: flex; padding: 6px 0;">
                <span class="info-label" style="font-weight: 600; width: 200px; color: #6b7280;">MFL Code:</span>
                <span class="info-value" style="color: #1f2937;">{{ $facilityInfo['mfl_code'] ?? 'N/A' }}</span>
            </div>
            <div class="info-row" style="display: flex; padding: 6px 0;">
                <span class="info-label" style="font-weight: 600; width: 200px; color: #6b7280;">County:</span>
                <span class="info-value" style="color: #1f2937;">{{ $facilityInfo['county'] }}</span>
            </div>
            <div class="info-row" style="display: flex; padding: 6px 0;">
                <span class="info-label" style="font-weight: 600; width: 200px; color: #6b7280;">Sub-County:</span>
                <span class="info-value" style="color: #1f2937;">{{ $facilityInfo['subcounty'] }}</span>
            </div>
            <!--            <div class="info-row" style="display: flex; padding: 6px 0;">
                            <span class="info-label" style="font-weight: 600; width: 200px; color: #6b7280;">Level:</span>
                            <span class="info-value" style="color: #1f2937;">{{ $facilityInfo['level'] ?? 'N/A' }}</span>
                        </div>-->
            <div class="info-row" style="display: flex; padding: 6px 0;">
                <span class="info-label" style="font-weight: 600; width: 200px; color: #6b7280;">Assessor:</span>
                <span class="info-value" style="color: #1f2937;">{{ $assessment->assessor_name }}</span>
            </div>
        </div>
    </div>

    @php
$percentage=0;
@endphp
    {{-- Section Scores --}}
    <div class="section" style="margin-bottom: 32px;">
        <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Section Performance</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
            @foreach($sectionScores as $score)
                <div class="section-score" style="background: #f9fafb; padding: 16px; border-left: 4px solid {{ $score['percentage'] >= 70 ? '#10b981' : ($score['percentage'] >= 50 ? '#f59e0b' : '#ef4444') }}; border-radius: 4px;">
                    <h3 style="margin: 0 0 8px 0; color: #374151;">{{ $score['section_name'] }}</h3>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 24px; font-weight: bold; color: #1f2937;">{{ number_format($score['percentage'], 1) }}%</span>
                        <span style="color: #6b7280;">{{ $score['score'] }} / {{ $score['max_score'] }}</span>
                    </div>
                </div>
                @php 
               
               $percentage += $score['percentage']; 
            @endphp
            @endforeach

        </div>
    </div>

    @php
    $np = (int) ($percentage/4);
    $color =$np >= 80 ? 'green' : ( $np >= 50 ? 'yellow' : 'red');
@endphp

    @php
        // Defined here (not only in the later @php block below) because
        // this Overall Score block renders *before* that block in the
        // file — Blade has no block scoping, but top-to-bottom order
        // still matters for when a variable first exists.
        $comparisonRounds = $comparison['rounds'] ?? [['id' => $assessment->id, 'label' => $assessment->round_display]];
    @endphp

    {{-- Overall Score Summary --}}
    <div class="overall-score" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 24px; border-radius: 8px; margin-bottom: 24px;">
        @if($comparison)
            <h3 style="margin: 0 0 16px 0; font-size: 16px; opacity: 0.9;">Overall Score by Round</h3>
            <div style="display: flex; gap: 24px; flex-wrap: wrap;">
                @foreach($comparisonRounds as $round)
                    @php $roundScore = $comparison['overallScore'][$round['id']] ?? null; @endphp
                    <div>
                        <p style="margin: 0; font-size: 13px; opacity: 0.85;">{{ $round['label'] }}</p>
                        <p style="font-size: 28px; font-weight: bold; margin: 4px 0;">{{ number_format($roundScore['percentage'] ?? 0, 1) }}%</p>
                        <span class="badge badge-{{ $roundScore['grade_color'] ?? 'gray' }}" style="font-size: 14px; padding: 4px 12px;">
                            {{ strtoupper($roundScore['grade'] ?? 'N/A') }}
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; font-size: 16px; opacity: 0.9;">Overall Score</h3>
                    <p style="font-size: 36px; font-weight: bold; margin: 8px 0;"> {{number_format($percentage/4,1) }}%</p>
                </div>
                <div style="text-align: right;">
                    <span class="badge badge-{{ $color }}" style="font-size: 24px; padding: 8px 20px;">
                        {{ strtoupper($color) }}
                    </span>
                </div>
            </div>
        @endif
    </div>

    @php
        $comparisonRounds = $comparison['rounds'] ?? [['id' => $assessment->id, 'label' => $assessment->round_display]];

        $infraRows = $comparison['infrastructure'] ?? collect($infrastructureDetails['responses'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
        $infraBedsRows = $comparison['infrastructureBeds'] ?? collect($infrastructureDetails['beds_table'] ?? [])
            ->map(fn ($d) => ['label' => $d['unit'], 'values' => [$assessment->id => $d]])->all();
    @endphp

    {{-- Infrastructure Details --}}
    @if(!empty($infraRows) || !empty($infraBedsRows))
        <div class="section" style="margin-bottom: 32px; page-break-inside: avoid;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Infrastructure</h2>

            @if(!empty($infraRows))
                @include('reports.partials.comparison-rows', ['rows' => $infraRows, 'rounds' => $comparisonRounds, 'field' => 'response', 'badge' => true])
            @endif

            @if(!empty($infraBedsRows))
                <h3 style="color: #374151; margin-bottom: 12px;">Bed Capacity</h3>
                @include('reports.partials.comparison-rows', ['rows' => $infraBedsRows, 'rounds' => $comparisonRounds, 'fields' => ['functional' => 'Functional', 'non_functional' => 'Non-Functional', 'total' => 'Total'], 'labelHeader' => 'Unit'])
            @endif
        </div>
    @endif

    @php
        $skillsLabRows = $comparison['skillsLab'] ?? collect($skillsLabDetails['responses'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
    @endphp

    {{-- Skills Lab Details --}}
    @if(!empty($skillsLabRows))
        <div class="section" style="margin-bottom: 32px; page-break-inside: avoid;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Skills Lab</h2>
            @include('reports.partials.comparison-rows', ['rows' => $skillsLabRows, 'rounds' => $comparisonRounds, 'field' => 'response', 'badge' => true, 'labelHeader' => 'Equipment/Item'])
        </div>
    @endif

    @php
        $infoSysRows = $comparison['informationSystems'] ?? collect($informationSystemsDetails['responses'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
        $infoSysToolsRows = $comparison['informationSystemsDataTools'] ?? collect($informationSystemsDetails['data_tools_table'] ?? [])
            ->map(fn ($d) => ['label' => $d['form'], 'values' => [$assessment->id => $d]])->all();
    @endphp

    {{-- Information Systems Details --}}
    @if(!empty($infoSysRows) || !empty($infoSysToolsRows))
        <div class="section" style="margin-bottom: 32px; page-break-inside: avoid;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Information Systems</h2>

            @if(!empty($infoSysRows))
                @include('reports.partials.comparison-rows', ['rows' => $infoSysRows, 'rounds' => $comparisonRounds, 'field' => 'response', 'badge' => true])
            @endif

            @if(!empty($infoSysToolsRows))
                <h3 style="color: #374151; margin-top: 20px; margin-bottom: 12px;">Data Collection Tools & Registers — Availability &amp; Completeness</h3>
                @include('reports.partials.comparison-rows', ['rows' => $infoSysToolsRows, 'rounds' => $comparisonRounds, 'fields' => ['available' => 'Available', 'completeness' => 'Complete'], 'badge' => true, 'labelHeader' => 'Form / Register'])
            @endif
        </div>
    @endif

    @php
        $hrRows = $comparison['humanResources'] ?? collect($humanResourcesDetails['responses'] ?? [])
            ->map(fn ($d) => ['label' => $d['cadre'], 'values' => [$assessment->id => $d]])->all();
        $hrColumns = [
            'total_in_facility' => 'Available',
            'etat_plus' => 'ETAT+',
            'comprehensive_newborn_care' => 'Comp. NB',
            'imnci' => 'IMNCI',
            'type_1_diabetes' => 'Diabetes',
            'essential_newborn_care' => 'Ess. NB',
        ];
    @endphp

    {{-- Human Resources --}}
    @if(!empty($hrRows))
        <div class="section" style="margin-bottom: 32px; page-break-inside: avoid;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Human Resources</h2>

            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr>
                        <th rowspan="2" style="background:#f3f4f6;padding:12px;text-align:left;border:1px solid #d1d5db;vertical-align:bottom;">Cadre</th>
                        @foreach($comparisonRounds as $round)
                            <th colspan="{{ count($hrColumns) }}" style="background:#e5e7eb;padding:8px;text-align:center;border:1px solid #d1d5db;">{{ $round['label'] }}</th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach($comparisonRounds as $round)
                            @foreach($hrColumns as $label)
                                <th style="background:#f3f4f6;padding:8px;text-align:center;border:1px solid #d1d5db;font-size:12px;">{{ $label }}</th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($hrRows as $row)
                        <tr>
                            <td style="padding:10px 12px;border:1px solid #e5e7eb;font-weight:600;">{{ $row['label'] ?? '-' }}</td>
                            @foreach($comparisonRounds as $round)
                                @foreach($hrColumns as $field => $label)
                                    @php $value = $row['values'][$round['id']][$field] ?? null; @endphp
                                    <td style="padding:10px 12px;border:1px solid #e5e7eb;text-align:center;">
                                        {{ $value === null ? '—' : $value }}
                                    </td>
                                @endforeach
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @php
        $healthProductsData = $comparison['healthProducts'] ?? $healthProductsDetails;
    @endphp

    {{-- Health Products Summary --}}
    @if(!empty($healthProductsData))
        <div class="section" style="margin-bottom: 32px;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Health Products & Commodities</h2>
            @foreach($healthProductsData as $departmentName => $dept)
                <h3 style="color: #374151; margin-top: 24px; margin-bottom: 12px;">{{ $departmentName }}</h3>
                @foreach($dept['categories'] as $category)
                    <h4 style="color: #4b5563; font-size: 14px; margin-bottom: 8px;">{{ $category['name'] }}</h4>
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 16px;">
                        <thead>
                            <tr>
                                <th style="background: #f3f4f6; padding: 8px 12px; text-align: left; border: 1px solid #d1d5db;">Item</th>
                                @foreach($comparisonRounds as $round)
                                    <th style="background: #f3f4f6; padding: 8px 12px; text-align: center; border: 1px solid #d1d5db;">{{ $round['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($category['items'] as $item)
                                <tr>
                                    <td style="padding: 8px 12px; border: 1px solid #e5e7eb;">{{ $item['name'] }}</td>
                                    @foreach($comparisonRounds as $round)
                                        @php
                                            $available = $comparison
                                                ? ($item['values'][$round['id']] ?? null)
                                                : $item['available'];
                                        @endphp
                                        <td style="padding: 8px 12px; border: 1px solid #e5e7eb; text-align: center;">
                                            @if($available === null)
                                                <span style="color:#9ca3af;">&mdash;</span>
                                            @else
                                                <span class="badge badge-{{ $available ? 'green' : 'red' }}" style="font-size: 12px;">
                                                    {{ $available ? 'Yes' : 'No' }}
                                                </span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            @endforeach
        </div>
    @endif

    @php
        $qualityYesNoRows = $comparison['qualityYesNo'] ?? collect($qualityOfCareDetails['yes_no_array'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
        $qualityStatsRows = $comparison
            ? array_merge($comparison['qualityNewbornStats'] ?? [], $comparison['qualityPaedStats'] ?? [])
            : collect(array_merge($qualityOfCareDetails['newborn_stats_array'] ?? [], $qualityOfCareDetails['paed_stats_array'] ?? []))
                ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
    @endphp

    {{-- Quality of Care --}}
    @if(!empty($qualityYesNoRows) || !empty($qualityStatsRows))
        <div class="section" style="margin-bottom: 32px;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Quality of Care</h2>

            {{-- Audit Questions --}}
            @if(!empty($qualityYesNoRows))
                <h3 style="color: #374151; margin-bottom: 12px;">Audit & Process Compliance</h3>
                @include('reports.partials.comparison-rows', ['rows' => $qualityYesNoRows, 'rounds' => $comparisonRounds, 'field' => 'response', 'badge' => true])
            @endif

            {{-- Statistical Data --}}
            @if(!empty($qualityStatsRows))
                <h3 style="color: #374151; margin-bottom: 12px;">Care Statistics</h3>
                @include('reports.partials.comparison-rows', ['rows' => $qualityStatsRows, 'rounds' => $comparisonRounds, 'field' => 'response'])
            @endif
        </div>
    @endif

    @php
        $indicatorsNewbornRows = $comparison['indicatorsNewborn'] ?? collect($indicatorsDetails['newborn_array'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
        $indicatorsPaediatricRows = $comparison['indicatorsPaediatric'] ?? collect($indicatorsDetails['paediatric_array'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
        $indicatorsNewbornProportionsRows = $comparison['indicatorsNewbornProportions'] ?? collect($indicatorsDetails['newborn_proportions_array'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
        $indicatorsPaediatricProportionsRows = $comparison['indicatorsPaediatricProportions'] ?? collect($indicatorsDetails['paediatric_proportions_array'] ?? [])
            ->map(fn ($d) => ['label' => $d['question'], 'values' => [$assessment->id => $d]])->all();
    @endphp

    {{-- Newborn & Paediatric Indicators --}}
    @if(!empty($indicatorsNewbornRows) || !empty($indicatorsPaediatricRows) || !empty($indicatorsNewbornProportionsRows) || !empty($indicatorsPaediatricProportionsRows))
        <div class="section" style="margin-bottom: 32px;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Newborn & Paediatric Indicators</h2>

            @if(!empty($indicatorsNewbornRows))
                <h3 style="color: #374151; margin-bottom: 12px;">Newborn Indicators</h3>
                @include('reports.partials.comparison-rows', ['rows' => $indicatorsNewbornRows, 'rounds' => $comparisonRounds, 'field' => 'response'])
            @endif

            @if(!empty($indicatorsPaediatricRows))
                <h3 style="color: #374151; margin-bottom: 12px;">Paediatric Indicators</h3>
                @include('reports.partials.comparison-rows', ['rows' => $indicatorsPaediatricRows, 'rounds' => $comparisonRounds, 'field' => 'response'])
            @endif

            @if(!empty($indicatorsNewbornProportionsRows))
                <h3 style="color: #374151; margin-bottom: 12px;">Newborn Proportions</h3>
                @include('reports.partials.comparison-rows', ['rows' => $indicatorsNewbornProportionsRows, 'rounds' => $comparisonRounds, 'field' => 'response'])
            @endif

            @if(!empty($indicatorsPaediatricProportionsRows))
                <h3 style="color: #374151; margin-bottom: 12px;">Paediatric Proportions</h3>
                @include('reports.partials.comparison-rows', ['rows' => $indicatorsPaediatricProportionsRows, 'rounds' => $comparisonRounds, 'field' => 'response'])
            @endif
        </div>
    @endif

    {{-- Footer --}}
    <div style="margin-top: 48px; padding-top: 16px; border-top: 2px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px;">
        <p>Generated on {{ now()->format('F d, Y \a\t H:i') }}</p>
        <p>MNCH Baseline Assessment System</p>
    </div>
</div>
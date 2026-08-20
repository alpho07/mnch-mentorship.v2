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

    {{-- Overall Score Summary --}}
    <div class="overall-score" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 24px; border-radius: 8px; margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0; font-size: 16px; opacity: 0.9;">Overall Score</h3>
                <p style="font-size: 36px; font-weight: bold; margin: 8px 0;"> {{number_format($percentage/4,1) }}%</p>
            </div>
            <div style="text-align: right;">
                <span class="badge badge-{{ $color }}" style="font-size: 24px; padding: 8px 20px;">
                    {{ strtoupper($color) }}
                </span>
<!--                <p style="margin: 8px 0 0 0; font-size: 14px; opacity: 0.9;">{{ $overallScore['score'] }} / {{ $overallScore['max_score'] }} points</p>-->
            </div>
        </div>
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

    {{-- Human Resources --}}
 @if(!empty($humanResourcesDetails['responses']))

        @php
$totalAvailable = 0;
$totalEtat = 0;
$totalCompNB = 0;
$totalImnci = 0;
$totalDiabetes = 0;
$totalEssNB = 0;
@endphp

        <div class="section" style="margin-bottom: 32px; page-break-inside: avoid;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">
            Human Resources
            </h2>

            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <thead>
                    <tr>
                        <th style="background:#f3f4f6;padding:12px;text-align:left;border:1px solid #d1d5db;">Cadre</th>
                        <th style="background:#f3f4f6;padding:12px;text-align:left;border:1px solid #d1d5db;">No. Available</th>
                        <th style="background:#f3f4f6;padding:12px;text-align:center;border:1px solid #d1d5db;">ETAT+</th>
                        <th style="background:#f3f4f6;padding:12px;text-align:center;border:1px solid #d1d5db;">Comp. NB</th>
                        <th style="background:#f3f4f6;padding:12px;text-align:center;border:1px solid #d1d5db;">IMNCI</th>
                        <th style="background:#f3f4f6;padding:12px;text-align:center;border:1px solid #d1d5db;">Diabetes</th>
                        <th style="background:#f3f4f6;padding:12px;text-align:center;border:1px solid #d1d5db;">Ess. NB</th>
                    </tr>
                </thead>

                <tbody>

                    @foreach($humanResourcesDetails['responses'] as $hr)

                        @php
                // hrCell() returns the string 'N/A' for a cadre's inapplicable
                // training columns — keep that for display, but sum only the
                // numeric ones (is_numeric('N/A') is false), or "int + string"
                // throws the moment any cadre has an N/A column.
                $totalDisplay = $hr['total_in_facility'] ?? 0;
                $etatDisplay = $hr['etat_plus'] ?? 0;
                $compNBDisplay = $hr['comprehensive_newborn_care'] ?? 0;
                $imnciDisplay = $hr['imnci'] ?? 0;
                $diabetesDisplay = $hr['type_1_diabetes'] ?? 0;
                $essNBDisplay = $hr['essential_newborn_care'] ?? 0;

                // No. Available is the cadre's own independently-entered
                // headcount, never the sum of the training columns — the
                // same worker can be trained in more than one area, so
                // adding those together double-counts them.
                $available = is_numeric($totalDisplay) ? $totalDisplay : 0;
                $etat = is_numeric($etatDisplay) ? $etatDisplay : 0;
                $compNB = is_numeric($compNBDisplay) ? $compNBDisplay : 0;
                $imnci = is_numeric($imnciDisplay) ? $imnciDisplay : 0;
                $diabetes = is_numeric($diabetesDisplay) ? $diabetesDisplay : 0;
                $essNB = is_numeric($essNBDisplay) ? $essNBDisplay : 0;

                $totalAvailable += $available;
                $totalEtat += $etat;
                $totalCompNB += $compNB;
                $totalImnci += $imnci;
                $totalDiabetes += $diabetes;
                $totalEssNB += $essNB;
            @endphp

                        <tr>
                            <td style="padding:10px 12px;border:1px solid #e5e7eb;font-weight:600;">
                                {{ $hr['cadre'] ?? '-' }}
                            </td>

                            <td style="padding:10px 12px;border:1px solid #e5e7eb;font-weight:600;">
                                {{ $totalDisplay }}
                            </td>

                            <td style="padding:10px 12px;border:1px solid #e5e7eb;text-align:center;">
                                {{ $etatDisplay }}
                            </td>

                            <td style="padding:10px 12px;border:1px solid #e5e7eb;text-align:center;">
                                {{ $compNBDisplay }}
                            </td>

                            <td style="padding:10px 12px;border:1px solid #e5e7eb;text-align:center;">
                                {{ $imnciDisplay }}
                            </td>

                            <td style="padding:10px 12px;border:1px solid #e5e7eb;text-align:center;">
                                {{ $diabetesDisplay }}
                            </td>

                            <td style="padding:10px 12px;border:1px solid #e5e7eb;text-align:center;">
                                {{ $essNBDisplay }}
                            </td>
                        </tr>

                    @endforeach

                    {{-- TOTAL ROW --}}
                    <tr style="background:#111827;color:#ffffff;font-weight:700;">
                        <td style="padding:14px;border:2px solid #111827;">
                        TOTAL
                        </td>

                        <td style="padding:14px;border:2px solid #111827;text-align:center;">
                            {{ $totalAvailable }}
                        </td>

                        <td style="padding:14px;border:2px solid #111827;text-align:center;">
                            {{ $totalEtat }}
                        </td>

                        <td style="padding:14px;border:2px solid #111827;text-align:center;">
                            {{ $totalCompNB }}
                        </td>

                        <td style="padding:14px;border:2px solid #111827;text-align:center;">
                            {{ $totalImnci }}
                        </td>

                        <td style="padding:14px;border:2px solid #111827;text-align:center;">
                            {{ $totalDiabetes }}
                        </td>

                        <td style="padding:14px;border:2px solid #111827;text-align:center;">
                            {{ $totalEssNB }}
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>

    @endif

    {{-- Health Products Summary --}}
    @if(!empty($healthProductsDetails))
        <div class="section" style="margin-bottom: 32px;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Health Products & Commodities</h2>
        @foreach($healthProductsDetails as $departmentName => $dept)
                <h3 style="color: #374151; margin-top: 24px; margin-bottom: 12px;">{{ $departmentName }}</h3>
            @foreach($dept['categories'] as $category)
                    <div style="margin-bottom: 16px;">
                        <h4 style="color: #4b5563; font-size: 14px; margin-bottom: 8px;">{{ $category['name'] }} ({{ $category['available'] }}/{{ $category['total'] }} available)</h4>
                        <div style="background: #f9fafb; padding: 12px; border-radius: 4px;">
                            @foreach($category['items'] as $item)
                                <div style="display: inline-block; margin: 4px 8px 4px 0;">
                                    <span class="badge badge-{{ $item['available'] ? 'green' : 'red' }}" style="font-size: 12px;">
                                        {{ $item['name'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
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

    {{-- Newborn & Paediatric Indicators --}}
    @if(!empty($indicatorsDetails['newborn_array']) || !empty($indicatorsDetails['paediatric_array']))
        <div class="section" style="margin-bottom: 32px;">
            <h2 style="color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; margin-bottom: 16px;">Newborn & Paediatric Indicators</h2>

            @if(!empty($indicatorsDetails['newborn_array']))
                <h3 style="color: #374151; margin-bottom: 12px;">Newborn Indicators</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    @foreach($indicatorsDetails['newborn_array'] as $stat)
                        <div style="background: #f9fafb; padding: 12px; border-radius: 4px;">
                            <p style="color: #6b7280; font-size: 12px; margin: 0;">{{ $stat['question'] }}</p>
                            <p style="color: #1f2937; font-size: 20px; font-weight: bold; margin: 4px 0 0 0;">{{ $stat['response'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(!empty($indicatorsDetails['paediatric_array']))
                <h3 style="color: #374151; margin-bottom: 12px;">Paediatric Indicators</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px;">
                    @foreach($indicatorsDetails['paediatric_array'] as $stat)
                        <div style="background: #f9fafb; padding: 12px; border-radius: 4px;">
                            <p style="color: #6b7280; font-size: 12px; margin: 0;">{{ $stat['question'] }}</p>
                            <p style="color: #1f2937; font-size: 20px; font-weight: bold; margin: 4px 0 0 0;">{{ $stat['response'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(!empty($indicatorsDetails['newborn_proportions_array']))
                <h3 style="color: #374151; margin-bottom: 12px;">Newborn Proportions</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 14px; margin-bottom: 24px;">
                    <tbody>
                        @foreach($indicatorsDetails['newborn_proportions_array'] as $item)
                            <tr>
                                <td style="padding: 10px 12px; border: 1px solid #e5e7eb;">{{ $item['question'] }}</td>
                                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; text-align: center; width: 160px; font-weight: 600;">{{ $item['response'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($indicatorsDetails['paediatric_proportions_array']))
                <h3 style="color: #374151; margin-bottom: 12px;">Paediatric Proportions</h3>
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <tbody>
                        @foreach($indicatorsDetails['paediatric_proportions_array'] as $item)
                            <tr>
                                <td style="padding: 10px 12px; border: 1px solid #e5e7eb;">{{ $item['question'] }}</td>
                                <td style="padding: 10px 12px; border: 1px solid #e5e7eb; text-align: center; width: 160px; font-weight: 600;">{{ $item['response'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- Footer --}}
    <div style="margin-top: 48px; padding-top: 16px; border-top: 2px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px;">
        <p>Generated on {{ now()->format('F d, Y \a\t H:i') }}</p>
        <p>MNCH Baseline Assessment System</p>
    </div>
</div>
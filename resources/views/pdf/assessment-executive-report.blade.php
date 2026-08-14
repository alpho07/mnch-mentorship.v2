{{-- resources/views/pdf/assessment-executive-report.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>MNCH Baseline Assessment Report</title>
    <style>
        @page {
            margin: 0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            color: #1f2937;
            line-height: 1.4;
            padding: 25mm 20mm;
        }
        
        .report-container {
            width: 100%;
            max-width: 100%;
        }
        
        /* Header */
        .report-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 3px solid #667eea;
        }
        
        .report-header h1 {
            color: #1f2937;
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .report-header h2 {
            color: #4b5563;
            font-size: 13pt;
            font-weight: normal;
            margin-bottom: 5px;
        }
        
        .report-header p {
            color: #6b7280;
            font-size: 9pt;
        }
        
        /* Section Headers */
        h2.section-title {
            color: #1f2937;
            font-size: 12pt;
            font-weight: bold;
            padding: 8px 10px;
            margin-bottom: 10px;
            margin-top: 15px;
            background-color: #f3f4f6;
            border-left: 4px solid #667eea;
        }
        
        h3.subsection-title {
            color: #374151;
            font-size: 10pt;
            font-weight: bold;
            margin-top: 12px;
            margin-bottom: 8px;
            padding: 5px 8px;
            background-color: #f9fafb;
            border-left: 3px solid #9ca3af;
        }
        
        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 9pt;
        }
        
        /* Facility Info Table */
        table.info-table td {
            padding: 7px 10px;
            border: 1px solid #d1d5db;
        }
        
        table.info-table td.label {
            background-color: #f9fafb;
            font-weight: bold;
            color: #4b5563;
            width: 30%;
        }
        
        table.info-table td.value {
            color: #1f2937;
            background-color: #ffffff;
        }
        
        /* Section Scores Table */
        table.scores-table {
            margin-bottom: 15px;
        }
        
        table.scores-table thead th {
            background-color: #374151;
            color: white;
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #374151;
            font-weight: bold;
            font-size: 9pt;
        }
        
        table.scores-table thead th.center {
            text-align: center;
        }
        
        table.scores-table tbody td {
            padding: 8px 10px;
            border: 1px solid #d1d5db;
        }
        
        table.scores-table tbody td.section-name {
            font-weight: bold;
            color: #1f2937;
        }
        
        table.scores-table tbody td.percentage {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            color: #1f2937;
        }
        
        table.scores-table tbody td.fraction {
            text-align: center;
            color: #6b7280;
            font-size: 9pt;
        }
        
        table.scores-table tbody tr.green-row {
            background-color: #f0fdf4;
            border-left: 4px solid #10b981;
        }
        
        table.scores-table tbody tr.yellow-row {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
        }
        
        table.scores-table tbody tr.red-row {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
        }
        
        /* Overall Score Table */
        table.overall-table {
            background-color: #667eea;
            color: white;
            margin-bottom: 15px;
        }
        
        table.overall-table td {
            padding: 12px 15px;
            border: none;
        }
        
        table.overall-table td.label {
            font-size: 10pt;
            font-weight: normal;
            width: 50%;
        }
        
        table.overall-table td.percentage {
            font-size: 28pt;
            font-weight: bold;
            text-align: center;
            width: 30%;
        }
        
        table.overall-table td.badge {
            text-align: right;
            width: 20%;
        }
        
        .overall-badge {
            display: inline-block;
            font-size: 12pt;
            font-weight: bold;
            padding: 8px 16px;
            border-radius: 3px;
        }
        
        .overall-badge.green {
            background-color: #10b981;
        }
        
        .overall-badge.yellow {
            background-color: #f59e0b;
        }
        
        .overall-badge.red {
            background-color: #ef4444;
        }
        
        /* Data Tables (Infrastructure, Skills Lab, etc.) */
        table.data-table thead th {
            background-color: #374151;
            color: white;
            padding: 8px 10px;
            text-align: left;
            border: 1px solid #374151;
            font-weight: bold;
            font-size: 9pt;
        }
        
        table.data-table thead th.center {
            text-align: center;
        }
        
        table.data-table tbody td {
            padding: 7px 10px;
            border: 1px solid #d1d5db;
            background-color: #ffffff;
        }
        
        table.data-table tbody td.center {
            text-align: center;
        }
        
        table.data-table tbody td.bold {
            font-weight: bold;
        }
        
        table.data-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        /* HR Table Footer (Totals) - CRITICAL for visibility */
        table.data-table tfoot {
            background-color: #1f2937;
            color: white;
            font-weight: bold;
        }
        
        table.data-table tfoot tr {
            background-color: #1f2937;
            color: white;
        }
        
        table.data-table tfoot td {
            padding: 10px;
            border: 1px solid #1f2937;
            background-color: #1f2937;
            color: white;
            font-weight: bold;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
            color: white;
        }
        
        .badge-green {
            background-color: #10b981;
        }
        
        .badge-red {
            background-color: #ef4444;
        }
        
        .badge-yellow {
            background-color: #f59e0b;
        }

        .badge-gray {
            background-color: #9ca3af;
        }
        
        /* Health Products Table */
        table.commodity-table thead th {
            background-color: #4b5563;
            color: white;
            padding: 6px 8px;
            border: 1px solid #4b5563;
            font-weight: bold;
            font-size: 8pt;
        }
        
        table.commodity-table tbody td {
            padding: 5px 8px;
            border: 1px solid #d1d5db;
            font-size: 8pt;
        }
        
        table.commodity-table tbody td.center {
            text-align: center;
        }
        
        table.commodity-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        /* Quality of Care Stats Table */
        table.stats-table {
            margin-bottom: 12px;
        }
        
        table.stats-table thead th {
            background-color: #4b5563;
            color: white;
            padding: 7px 10px;
            border: 1px solid #4b5563;
            font-weight: bold;
            font-size: 9pt;
        }
        
        table.stats-table tbody td {
            padding: 8px 10px;
            border: 1px solid #d1d5db;
        }
        
        table.stats-table tbody td.stat-label {
            color: #6b7280;
            font-size: 8pt;
            background-color: #f9fafb;
            width: 60%;
        }
        
        table.stats-table tbody td.stat-value {
            color: #1f2937;
            font-size: 12pt;
            font-weight: bold;
            background-color: #ffffff;
            text-align: center;
        }
        
        /* Footer */
        .report-footer {
            margin-top: 25px;
            padding-top: 12px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 8pt;
        }
        
        .report-footer p {
            margin: 3px 0;
        }
        
        /* Page Break Control */
        .section {
            page-break-inside: avoid;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="report-container">
        {{-- Header --}}
        <div class="report-header">
            <h1>MNCH BASELINE ASSESSMENT</h1>
            <h2>{{ $facilityInfo['name'] }}</h2>
            <p>Assessment Date: {{ $assessment->assessment_date->format('F d, Y') }}</p>
        </div>

        {{-- Facility Information --}}
        <div class="section">
            <h2 class="section-title">Facility Information</h2>
            <table class="info-table">
                <tr>
                    <td class="label">Facility Name</td>
                    <td class="value">{{ $facilityInfo['name'] }}</td>
                </tr>
                <tr>
                    <td class="label">MFL Code</td>
                    <td class="value">{{ $facilityInfo['mfl_code'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">County</td>
                    <td class="value">{{ $facilityInfo['county'] }}</td>
                </tr>
                <tr>
                    <td class="label">Sub-County</td>
                    <td class="value">{{ $facilityInfo['subcounty'] }}</td>
                </tr>
                <tr>
                    <td class="label">Assessor</td>
                    <td class="value">{{ $assessment->assessor_name }}</td>
                </tr>
            </table>
        </div>

        @php
            $percentage = 0;
        @endphp

        {{-- Section Scores --}}
        <div class="section">
            <h2 class="section-title">Section Performance</h2>
            <table class="scores-table">
                <thead>
                    <tr>
                        <th>Section</th>
                        <th class="center">Score (%)</th>
                        <th class="center">Points</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sectionScores as $score)
                        @php
                            $rowClass = $score['percentage'] >= 70 ? 'green-row' : ($score['percentage'] >= 50 ? 'yellow-row' : 'red-row');
                            $percentage += $score['percentage'];
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="section-name">{{ $score['section_name'] }}</td>
                            <td class="percentage">{{ number_format($score['percentage'], 1) }}%</td>
                            <td class="fraction">{{ $score['score'] }} / {{ $score['max_score'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @php
            $np = (int) ($percentage/4);
            $color = $np >= 80 ? 'green' : ($np >= 50 ? 'yellow' : 'red');
        @endphp

        {{-- Overall Score Summary --}}
        <div class="section">
            <table class="overall-table">
                <tr>
                    <td class="label">Overall Assessment Score</td>
                    <td class="percentage">{{ number_format($percentage/4, 1) }}%</td>
                    <td class="badge">
                        <span class="overall-badge {{ $color }}">{{ strtoupper($color) }}</span>
                    </td>
                </tr>
            </table>
        </div>

        {{-- Infrastructure Details --}}
        @if(!empty($infrastructureDetails['responses']) || !empty($infrastructureDetails['beds_table']))
            <div class="section">
                <h2 class="section-title">Infrastructure</h2>

                @if(!empty($infrastructureDetails['responses']))
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th class="center" style="width: 15%;">Response</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($infrastructureDetails['responses'] as $detail)
                                <tr>
                                    <td>{{ $detail['question'] }}</td>
                                    <td class="center">
                                        @if($detail['is_number'] ?? false)
                                            {{ $detail['response'] }}
                                        @else
                                            <span class="badge badge-{{ $detail['response'] === 'Yes' ? 'green' : 'red' }}">
                                                {{ $detail['response'] }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if(!empty($infrastructureDetails['beds_table']))
                    <h3 class="subsection-title">Bed Capacity</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Unit</th>
                                <th class="center" style="width: 18%;">No. Functional</th>
                                <th class="center" style="width: 18%;">No. Non-Functional</th>
                                <th class="center" style="width: 14%;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($infrastructureDetails['beds_table'] as $bed)
                                <tr>
                                    <td>{{ $bed['unit'] }}</td>
                                    <td class="center">{{ $bed['functional'] }}</td>
                                    <td class="center">{{ $bed['non_functional'] }}</td>
                                    <td class="center bold">{{ $bed['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

        {{-- Skills Lab Details --}}
        @if(!empty($skillsLabDetails['responses']))
            <div class="section">
                <h2 class="section-title">Skills Lab</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Equipment/Item</th>
                            <th class="center" style="width: 15%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($skillsLabDetails['responses'] as $detail)
                            <tr>
                                <td>{{ $detail['question'] }}</td>
                                <td class="center">
                                    <span class="badge badge-{{ $detail['response'] === 'Yes' ? 'green' : 'red' }}">
                                        {{ $detail['response'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Information Systems Details --}}
        @if(!empty($informationSystemsDetails['responses']) || !empty($informationSystemsDetails['data_tools_table']))
            <div class="section">
                <h2 class="section-title">Information Systems</h2>
                @if(!empty($informationSystemsDetails['responses']))
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th class="center" style="width: 15%;">Response</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($informationSystemsDetails['responses'] as $detail)
                                <tr>
                                    <td>{{ $detail['question'] }}</td>
                                    <td class="center">
                                        <span class="badge badge-{{ $detail['response'] === 'Yes' ? 'green' : 'red' }}">
                                            {{ $detail['response'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if(!empty($informationSystemsDetails['data_tools_table']))
                    <h3 class="subsection-title">Data Collection Tools & Registers — Availability &amp; Completeness</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Form / Register</th>
                                <th class="center" style="width: 15%;">Available</th>
                                <th class="center" style="width: 15%;">Complete</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($informationSystemsDetails['data_tools_table'] as $row)
                                <tr>
                                    <td>{{ $row['form'] }}</td>
                                    <td class="center">
                                        <span class="badge badge-{{ $row['available'] === 'Yes' ? 'green' : 'red' }}">
                                            {{ $row['available'] }}
                                        </span>
                                    </td>
                                    <td class="center">
                                        <span class="badge badge-{{ $row['completeness'] === 'Yes' ? 'green' : 'red' }}">
                                            {{ $row['completeness'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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

            <div class="section">
                <h2 class="section-title">Human Resources</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cadre</th>
                            <th class="center" style="width: 12%;">Available</th>
                            <th class="center" style="width: 11%;">ETAT+</th>
                            <th class="center" style="width: 11%;">Comp. NB</th>
                            <th class="center" style="width: 11%;">IMNCI</th>
                            <th class="center" style="width: 11%;">Diabetes</th>
                            <th class="center" style="width: 11%;">Ess. NB</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($humanResourcesDetails['responses'] as $hr)
                            @php
                                // hrCell() returns the string 'N/A' for a cadre's
                                // inapplicable training columns — keep that for
                                // display, but sum only the numeric ones
                                // (is_numeric('N/A') is false), or "int + string"
                                // throws the moment any cadre has an N/A column.
                                $totalDisplay = $hr['total_in_facility'] ?? 0;
                                $etatDisplay = $hr['etat_plus'] ?? 0;
                                $compNBDisplay = $hr['comprehensive_newborn_care'] ?? 0;
                                $imnciDisplay = $hr['imnci'] ?? 0;
                                $diabetesDisplay = $hr['type_1_diabetes'] ?? 0;
                                $essNBDisplay = $hr['essential_newborn_care'] ?? 0;

                                // No. Available is the cadre's own independently-
                                // entered headcount, never the sum of the training
                                // columns — the same worker can be trained in more
                                // than one area, so adding those together
                                // double-counts them.
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
                                <td class="bold">{{ $hr['cadre'] ?? '-' }}</td>
                                <td class="center bold">{{ $totalDisplay }}</td>
                                <td class="center">{{ $etatDisplay }}</td>
                                <td class="center">{{ $compNBDisplay }}</td>
                                <td class="center">{{ $imnciDisplay }}</td>
                                <td class="center">{{ $diabetesDisplay }}</td>
                                <td class="center">{{ $essNBDisplay }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>TOTAL</td>
                            <td class="center">{{ $totalAvailable }}</td>
                            <td class="center">{{ $totalEtat }}</td>
                            <td class="center">{{ $totalCompNB }}</td>
                            <td class="center">{{ $totalImnci }}</td>
                            <td class="center">{{ $totalDiabetes }}</td>
                            <td class="center">{{ $totalEssNB }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        {{-- Health Products Summary --}}
        @if(!empty($healthProductsDetails))
            <div class="section">
                <h2 class="section-title">Health Products & Commodities</h2>
                @foreach($healthProductsDetails as $departmentName => $dept)
                    <h3 class="subsection-title">{{ $departmentName }}</h3>
                    @foreach($dept['categories'] as $category)
                        <table class="commodity-table">
                            <thead>
                                <tr>
                                    <th colspan="2">{{ $category['name'] }} ({{ $category['available'] }}/{{ $category['total'] }} available)</th>
                                </tr>
                                <tr>
                                    <th>Item</th>
                                    <th class="center" style="width: 15%;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($category['items'] as $item)
                                    <tr>
                                        <td>{{ $item['name'] }}</td>
                                        <td class="center">
                                            @if($item['not_applicable'] ?? false)
                                                <span class="badge badge-gray">N/A</span>
                                            @else
                                                <span class="badge badge-{{ $item['available'] ? 'green' : 'red' }}">
                                                    {{ $item['available'] ? 'Available' : 'Not Available' }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endforeach
                @endforeach
            </div>
        @endif

        {{-- Quality of Care --}}
        @if(!empty($qualityOfCareDetails))
            <div class="section">
                <h2 class="section-title">Quality of Care</h2>

                {{-- Audit Questions --}}
                @if(!empty($qualityOfCareDetails['yes_no_array']))
                    <h3 class="subsection-title">Audit & Process Compliance</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th class="center" style="width: 15%;">Response</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($qualityOfCareDetails['yes_no_array'] as $item)
                                <tr>
                                    <td>{{ $item['question'] }}</td>
                                    <td class="center">
                                        <span class="badge badge-{{ $item['response'] === 'Yes' ? 'green' : 'red' }}">
                                            {{ $item['response'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                {{-- Statistical Data --}}
                @if(!empty($qualityOfCareDetails['newborn_stats_array']) || !empty($qualityOfCareDetails['paed_stats_array']))
                    <h3 class="subsection-title">Care Statistics</h3>
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Statistic</th>
                                <th class="center" style="width: 25%;">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_merge($qualityOfCareDetails['newborn_stats_array'] ?? [], $qualityOfCareDetails['paed_stats_array'] ?? []) as $stat)
                                <tr>
                                    <td class="stat-label">{{ $stat['question'] }}</td>
                                    <td class="stat-value">{{ $stat['response'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

        {{-- Newborn & Paediatric Indicators --}}
        @if(!empty($indicatorsDetails['newborn_array']) || !empty($indicatorsDetails['paediatric_array']))
            <div class="section">
                <h2 class="section-title">Newborn & Paediatric Indicators</h2>

                @if(!empty($indicatorsDetails['newborn_array']))
                    <h3 class="subsection-title">Newborn Indicators</h3>
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Indicator</th>
                                <th class="center" style="width: 25%;">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($indicatorsDetails['newborn_array'] as $stat)
                                <tr>
                                    <td class="stat-label">{{ $stat['question'] }}</td>
                                    <td class="stat-value">{{ $stat['response'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if(!empty($indicatorsDetails['paediatric_array']))
                    <h3 class="subsection-title">Paediatric Indicators</h3>
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Indicator</th>
                                <th class="center" style="width: 25%;">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($indicatorsDetails['paediatric_array'] as $stat)
                                <tr>
                                    <td class="stat-label">{{ $stat['question'] }}</td>
                                    <td class="stat-value">{{ $stat['response'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if(!empty($indicatorsDetails['newborn_proportions_array']))
                    <h3 class="subsection-title">Newborn Proportions</h3>
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Proportion</th>
                                <th class="center" style="width: 25%;">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($indicatorsDetails['newborn_proportions_array'] as $item)
                                <tr>
                                    <td class="stat-label">{{ $item['question'] }}</td>
                                    <td class="stat-value">{{ $item['response'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if(!empty($indicatorsDetails['paediatric_proportions_array']))
                    <h3 class="subsection-title">Paediatric Proportions</h3>
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Proportion</th>
                                <th class="center" style="width: 25%;">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($indicatorsDetails['paediatric_proportions_array'] as $item)
                                <tr>
                                    <td class="stat-label">{{ $item['question'] }}</td>
                                    <td class="stat-value">{{ $item['response'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

        {{-- Footer --}}
        <div class="report-footer">
            <p>Generated on {{ now()->format('F d, Y \a\t H:i') }}</p>
            <p>MNCH Baseline Assessment System</p>
        </div>
    </div>
</body>
</html>
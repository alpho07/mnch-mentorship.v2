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
        @if(!empty($infrastructureDetails['responses']))
            <div class="section">
                <h2 class="section-title">Infrastructure</h2>
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
        @if(!empty($informationSystemsDetails['responses']))
            <div class="section">
                <h2 class="section-title">Information Systems</h2>
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
                                $etat = $hr['etat_plus'] ?? 0;
                                $compNB = $hr['comprehensive_newborn_care'] ?? 0;
                                $imnci = $hr['imnci'] ?? 0;
                                $diabetes = $hr['type_1_diabetes'] ?? 0;
                                $essNB = $hr['essential_newborn_care'] ?? 0;
                                $available = $etat + $compNB + $imnci + $diabetes + $essNB;

                                $totalAvailable += $available;
                                $totalEtat += $etat;
                                $totalCompNB += $compNB;
                                $totalImnci += $imnci;
                                $totalDiabetes += $diabetes;
                                $totalEssNB += $essNB;
                            @endphp
                            <tr>
                                <td class="bold">{{ $hr['cadre'] ?? '-' }}</td>
                                <td class="center bold">{{ $available }}</td>
                                <td class="center">{{ $etat }}</td>
                                <td class="center">{{ $compNB }}</td>
                                <td class="center">{{ $imnci }}</td>
                                <td class="center">{{ $diabetes }}</td>
                                <td class="center">{{ $essNB }}</td>
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
                                            <span class="badge badge-{{ $item['available'] ? 'green' : 'red' }}">
                                                {{ $item['available'] ? 'Available' : 'Not Available' }}
                                            </span>
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

        {{-- Footer --}}
        <div class="report-footer">
            <p>Generated on {{ now()->format('F d, Y \a\t H:i') }}</p>
            <p>MNCH Baseline Assessment System</p>
        </div>
    </div>
</body>
</html>
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Report Preview --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900">Assessment Report Preview</h2>
                <p class="text-sm text-gray-600 mt-1">This is a web preview of the downloadable PDF report</p>
            </div>
            
            <div class="p-6">
                {{-- Inject the HTML report here --}}
                <div class="assessment-report-preview">
                    {!! $this->getReportHtml() !!}
                </div>
            </div>
        </div>
    </div>

    {{-- Add print-friendly styles --}}
    <style>
        .assessment-report-preview {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        
        .assessment-report-preview h1 {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 16px;
            color: #1f2937;
        }
        
        .assessment-report-preview h2 {
            font-size: 20px;
            font-weight: bold;
            margin-top: 24px;
            margin-bottom: 12px;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 8px;
        }
        
        .assessment-report-preview h3 {
            font-size: 16px;
            font-weight: 600;
            margin-top: 16px;
            margin-bottom: 8px;
            color: #4b5563;
        }
        
        .assessment-report-preview table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            font-size: 14px;
        }
        
        .assessment-report-preview table th {
            background-color: #f3f4f6;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #d1d5db;
        }
        
        .assessment-report-preview table td {
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
        }
        
        .assessment-report-preview table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        .assessment-report-preview .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .assessment-report-preview .badge-green {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .assessment-report-preview .badge-yellow {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .assessment-report-preview .badge-red {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .assessment-report-preview .section-score {
            margin: 8px 0;
            padding: 12px;
            background-color: #f9fafb;
            border-left: 4px solid #3b82f6;
            border-radius: 4px;
        }
        
        .assessment-report-preview .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .assessment-report-preview .info-label {
            font-weight: 600;
            width: 200px;
            color: #6b7280;
        }
        
        .assessment-report-preview .info-value {
            flex: 1;
            color: #1f2937;
        }
        
        @media print {
            .assessment-report-preview {
                font-size: 12px;
            }
        }
    </style>
</x-filament-panels::page>
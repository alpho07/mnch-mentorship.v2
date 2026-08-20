{{-- resources/views/pdf/assessment-html-report-wrapper.blade.php --}}
{{-- Wraps reports/assessment-html-report.blade.php (the same partial the
     summary page renders) in a standalone document for DomPDF. The
     Filament page normally supplies the .badge/.info-row/etc. CSS via its
     own <style> block (resources/views/filament/pages/view-assessment-summary.blade.php)
     — that doesn't exist for a PDF rendered outside the panel, so the
     same rules are reproduced here, unscoped, so the PDF looks like the
     summary page instead of unstyled. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>MNCH Assessment Report</title>
    <style>
        @page {
            margin: 15mm 12mm;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.6;
            color: #333;
        }

        h1 {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 16px;
            color: #1f2937;
        }

        h2 {
            font-size: 20px;
            font-weight: bold;
            margin-top: 24px;
            margin-bottom: 12px;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 8px;
        }

        h3 {
            font-size: 16px;
            font-weight: 600;
            margin-top: 16px;
            margin-bottom: 8px;
            color: #4b5563;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
            font-size: 14px;
        }

        table th {
            background-color: #f3f4f6;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #d1d5db;
        }

        table td {
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-green {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-yellow {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-red {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .section-score {
            margin: 8px 0;
            padding: 12px;
            background-color: #f9fafb;
            border-left: 4px solid #3b82f6;
            border-radius: 4px;
        }

        .info-row {
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .info-label {
            font-weight: 600;
            width: 200px;
            color: #6b7280;
            display: inline-block;
        }

        .info-value {
            color: #1f2937;
        }
    </style>
</head>
<body>
    @include('reports.assessment-html-report')
</body>
</html>

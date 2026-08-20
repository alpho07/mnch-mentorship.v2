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
            font-size: 8pt;
            line-height: 1.5;
            color: #333;
        }

        /*
         * The report partial sets its own font-size (13px/14px/etc.) inline
         * on most tables — inline styles normally beat a stylesheet, but
         * !important flips that, so this is what actually shrinks them for
         * print. table-layout: fixed + break-word keeps every table inside
         * the page width instead of overflowing it (the live HTML page has
         * no such width constraint, so the partial itself doesn't set this).
         */
        table, table th, table td {
            font-size: 8pt !important;
        }

        table {
            table-layout: fixed !important;
        }

        table th, table td {
            padding: 5px 6px !important;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /*
         * DomPDF has no CSS Grid support, so the report partial's
         * `display: grid` on .section-score-grid falls back to plain
         * block/inline flow — every card stacked in one column instead of
         * a grid. Floats are what DomPDF actually supports, so this
         * reproduces a 3-column grid with them, PDF-only (the live HTML
         * page never loads this stylesheet, so it keeps its real grid).
         */
        .section-score-grid {
            display: block !important;
        }

        .section-score-grid::after {
            content: "";
            display: table;
            clear: both;
        }

        .section-score {
            float: left !important;
            width: 31.33% !important;
            margin: 0 3% 12px 0 !important;
            box-sizing: border-box;
        }

        .section-score:nth-child(3n) {
            margin-right: 0 !important;
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

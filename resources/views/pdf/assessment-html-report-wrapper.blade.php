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
         * Compact spacing so Facility Information, Section Performance,
         * and Overall Score fit on page 1 together instead of Overall
         * Score alone spilling to page 2 — the partial's own inline
         * margins/padding (32px section gaps, 16px facility-info padding,
         * 6px info-rows) were sized for the web page, which has no such
         * one-page constraint.
         */
        .report-header {
            margin-bottom: 14px !important;
        }

        .section {
            margin-bottom: 14px !important;
        }

        .section h2 {
            margin-bottom: 8px !important;
            padding-bottom: 4px !important;
        }

        .info-row {
            padding: 3px 0 !important;
        }

        .facility-info-box {
            padding: 8px 12px !important;
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
         * DomPDF has no CSS Grid support, and its float/flex/::after
         * support is unreliable enough (confirmed: a floated grid with a
         * ::after clearfix silently dropped a 4th card under the box
         * below it) that Section Performance and the Overall-Score-by-
         * Round row use real <table> markup for PDF instead — see the
         * `$isPdf` branches in reports/assessment-html-report.blade.php.
         * The .section-score-grid/.section-score/etc. classes below are
         * only reached by the live HTML page's own grid, which this
         * stylesheet never loads for, so no PDF-specific override of them
         * is needed here.
         *
         * .overall-score's background/text-color are set inline in the
         * report partial itself (per $isPdf), not here as a class rule —
         * simpler to reason about and immune to any future edit elsewhere
         * in this stylesheet accidentally breaking it again.
         */

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

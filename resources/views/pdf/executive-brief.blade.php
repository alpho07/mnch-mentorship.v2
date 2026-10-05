{{--
    PDF wrapper. The brief itself lives in reports.executive-brief so the
    Filament page and the download render byte-identical markup — the same
    arrangement AssessmentPdfReportService uses for the assessment report.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $brief['meta']['programme'] }}</title>
</head>
<body style="margin: 0;">
@include('reports.executive-brief', ['brief' => $brief])
</body>
</html> 

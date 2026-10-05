{{--
    Public wrapper for the executive brief.

    Same partial the Filament page and the PDF render, shown as the A4 sheet
    it is so the link the Division circulates matches the printed brief. No
    panel chrome: this is served outside the admin panel to readers who have
    no account on the platform.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $brief['meta']['programme'] }}</title>
    <style>
        body { margin: 0; background: #e9eef0; font-family: "DejaVu Sans", system-ui, sans-serif; }
        .stage { padding: 24px 12px 40px; }
        .sheet {
            width: 794px;            /* A4 at 96dpi */
            margin: 0 auto 18px;
            padding: 29px 37px 24px;
            background: #fff;
            box-shadow: 0 6px 28px rgba(15, 43, 56, .18);
        }
        .bar { width: 794px; margin: 0 auto 12px; text-align: right; }
        .bar a {
            display: inline-block;
            background: #0097A7;
            color: #fff;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            padding: 8px 16px;
        }
        @media (max-width: 900px) {
            .sheet, .bar { width: 100%; }
            .sheet { padding: 18px; }
        }
        @media print {
            body { background: #fff; }
            .stage { padding: 0; }
            .sheet { width: auto; box-shadow: none; padding: 0; margin: 0; }
            .bar { display: none; }
        }
    </style>
</head>
<body>
<div class="stage">
    <div class="bar"><a href="{{ route('analytics.dashboard.executive-brief.pdf') }}">Download PDF</a></div>
    <div class="sheet">
        @include('reports.executive-brief', ['brief' => $brief])
    </div>
</div>
</body>
</html>

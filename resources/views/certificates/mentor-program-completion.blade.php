<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Facilitation</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: 'DejaVu Sans', sans-serif;
            background: #f2f7f9;
        }
        .page-outer {
            width: 297mm;
            @if(!($isPdf ?? false))
            margin: 10mm auto;
            @endif
        }
        .wrap {
            width: 297mm;
            height: 210mm;
            position: relative;
            overflow: hidden;
            background: #ffffff;
        }
        @if(!($isPdf ?? false))
        .no-print-bar {
            width: 297mm;
            margin: 0 auto 6mm;
            text-align: center;
        }
        .no-print-bar button {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10.5pt;
            font-weight: bold;
            padding: 9px 26px;
            border-radius: 8px;
            border: none;
            background: #00796B;
            color: #fff;
            cursor: pointer;
        }
        @media print {
            .no-print-bar { display: none; }
            body { background: #fff; }
            .page-outer { margin: 0; }
        }
        @endif
    </style>
</head>
<body>

@if(!($isPdf ?? false))
<div class="no-print-bar">
    <button onclick="window.print()">Print / Save as PDF</button>
</div>
@endif

<div class="page-outer">
<div class="wrap">

    <div style="position:absolute;top:0;left:0;right:0;bottom:0;border:3mm solid #009688;pointer-events:none;"></div>
    <div style="position:absolute;top:6mm;left:6mm;right:6mm;bottom:6mm;border:1px dashed #004D40;pointer-events:none;"></div>

    <table style="width:297mm;height:210mm;border-collapse:collapse;">
        <tr>
            <td style="vertical-align:top;text-align:center;padding:22mm 28mm 0;">

                <div style="font-family:'DejaVu Sans',sans-serif;font-size:8pt;letter-spacing:3px;text-transform:uppercase;color:#00796B;font-weight:bold;margin-bottom:6mm;">
                    MNCH Mentorship Platform &bull; Ministry of Health, Kenya
                </div>

                <div style="font-family:'DejaVu Sans',sans-serif;font-size:24pt;font-weight:bold;color:#00796B;margin-bottom:2mm;">
                    Certificate of Facilitation
                </div>

                <div style="font-family:'DejaVu Sans',sans-serif;font-size:10pt;color:#555555;margin-bottom:4mm;">
                    This certifies that
                </div>

                <div style="font-family:'DejaVu Sans',sans-serif;font-size:26pt;font-weight:bold;color:#004D40;margin-bottom:5mm;">
                    {{ $mentor->full_name ?? $mentor->name ?? 'Lead Mentor' }}
                </div>

                <div style="font-family:'DejaVu Sans',sans-serif;font-size:11pt;color:#374151;margin-bottom:1.5mm;">
                    has successfully facilitated every module of
                </div>
                <div style="font-family:'DejaVu Sans',sans-serif;font-size:15pt;font-weight:bold;color:#00796B;margin-bottom:5mm;">
                    {{ $program->name }}
                </div>

                @php $year = now()->format('Y'); @endphp
                <table style="margin:0 auto 6mm;border-collapse:collapse;">
                    <tr>
                        <td style="padding:0 8mm;text-align:center;border-right:1px solid #d1e7e5;">
                            <div style="font-family:'DejaVu Sans',sans-serif;font-size:16pt;font-weight:bold;color:#004D40;">{{ $modules->count() }}</div>
                            <div style="font-family:'DejaVu Sans',sans-serif;font-size:7pt;letter-spacing:1px;text-transform:uppercase;color:#6b7280;">Modules Facilitated</div>
                        </td>
                        <td style="padding:0 8mm;text-align:center;border-right:1px solid #d1e7e5;">
                            <div style="font-family:'DejaVu Sans',sans-serif;font-size:16pt;font-weight:bold;color:#004D40;">{{ isset($cpd) ? $cpd['total'] : 0 }}</div>
                            <div style="font-family:'DejaVu Sans',sans-serif;font-size:7pt;letter-spacing:1px;text-transform:uppercase;color:#6b7280;">CPD Points</div>
                        </td>
                        <td style="padding:0 8mm;text-align:center;">
                            <div style="font-family:'DejaVu Sans',sans-serif;font-size:16pt;font-weight:bold;color:#004D40;">{{ $year }}</div>
                            <div style="font-family:'DejaVu Sans',sans-serif;font-size:7pt;letter-spacing:1px;text-transform:uppercase;color:#6b7280;">Year Issued</div>
                        </td>
                    </tr>
                </table>

                <div style="font-family:'DejaVu Sans',sans-serif;font-size:8.5pt;color:#9ca3af;margin-bottom:6mm;">
                    Issued on {{ now()->format('d F Y') }}
                </div>

                {{-- Footer: signature + QR --}}
                <table style="width:200mm;margin:4mm auto 0;border-collapse:collapse;">
                    <tr>
                        <td style="width:40%;vertical-align:bottom;text-align:center;">
                            <div style="width:55mm;height:1px;background:#004D40;margin:0 auto 2mm;"></div>
                            <div style="font-family:'DejaVu Sans',sans-serif;font-size:10pt;font-weight:bold;color:#111827;">Director, MNCH Division</div>
                            <div style="font-family:'DejaVu Sans',sans-serif;font-size:7pt;color:#6b7280;">Authorized Signature</div>
                        </td>
                        <td style="width:20%;vertical-align:middle;text-align:center;">
                            @if(!empty($qr))
                            <img src="{{ $qr }}" alt="Verify" style="width:20mm;height:20mm;">
                            <div style="font-family:'DejaVu Sans',sans-serif;font-size:6pt;letter-spacing:1px;text-transform:uppercase;color:#9ca3af;margin-top:1mm;">Scan to Verify</div>
                            @endif
                        </td>
                        <td style="width:40%;vertical-align:bottom;text-align:center;">
                            <div style="width:55mm;height:1px;background:#004D40;margin:0 auto 2mm;"></div>
                            <div style="font-family:'DejaVu Sans',sans-serif;font-size:10pt;font-weight:bold;color:#111827;">MNCH-MP-{{ str_pad($program->id, 3, '0', STR_PAD_LEFT) }}-{{ str_pad($mentor->id, 5, '0', STR_PAD_LEFT) }}-{{ $year }}</div>
                            <div style="font-family:'DejaVu Sans',sans-serif;font-size:7pt;color:#6b7280;">Certificate Number</div>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</div>
</div>

</body>
</html>

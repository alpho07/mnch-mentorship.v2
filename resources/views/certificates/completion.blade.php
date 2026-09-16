<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Completion</title>
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
            background: #e8e0d0;
        }
        .page-outer {
            width: 297mm;
            @if(!($isPdf ?? false))
            margin: 10mm auto;
            box-shadow: 0 8px 40px rgba(0,0,0,.2);
            @endif
        }
        .wrap {
            width: 297mm;
            height: 210mm;
            position: relative;
            overflow: hidden;
            background: #faf6ed;
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
            border-radius: 999px;
            border: none;
            background: #2a1f14;
            color: #f5f0e8;
            cursor: pointer;
        }
        @media print {
            .no-print-bar { display: none; }
            body { background: #fff; }
            .page-outer { margin: 0; box-shadow: none; }
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

    {{-- ── Decorative frame ───────────────────────────────────────────────── --}}
    <div style="position:absolute;top:9mm;left:9mm;right:9mm;bottom:9mm;border:2px solid #9a7a50;"></div>
    <div style="position:absolute;top:12mm;left:12mm;right:12mm;bottom:12mm;border:1px solid #c8a87a;"></div>

    {{-- ── Corner ornaments ───────────────────────────────────────────────── --}}
    @php
        $cornerPath = 'M8 8 L8 52 M8 8 L52 8 M8 20 L20 8 M8 32 L32 8 M8 44 L44 8';
    @endphp
    <div style="position:absolute;top:9mm;left:9mm;width:20mm;height:20mm;">
        <svg viewBox="0 0 60 60" width="100%" height="100%"><path d="{{ $cornerPath }}" stroke="#9a7a50" stroke-width="1.2" fill="none" opacity="0.6"/><rect x="4" y="4" width="16" height="16" stroke="#9a7a50" stroke-width="1.5" fill="none"/></svg>
    </div>
    <div style="position:absolute;top:9mm;right:9mm;width:20mm;height:20mm;">
        <svg viewBox="0 0 60 60" width="100%" height="100%" style="transform:scaleX(-1);"><path d="{{ $cornerPath }}" stroke="#9a7a50" stroke-width="1.2" fill="none" opacity="0.6"/><rect x="4" y="4" width="16" height="16" stroke="#9a7a50" stroke-width="1.5" fill="none"/></svg>
    </div>
    <div style="position:absolute;bottom:9mm;left:9mm;width:20mm;height:20mm;">
        <svg viewBox="0 0 60 60" width="100%" height="100%" style="transform:scaleY(-1);"><path d="{{ $cornerPath }}" stroke="#9a7a50" stroke-width="1.2" fill="none" opacity="0.6"/><rect x="4" y="4" width="16" height="16" stroke="#9a7a50" stroke-width="1.5" fill="none"/></svg>
    </div>
    <div style="position:absolute;bottom:9mm;right:9mm;width:20mm;height:20mm;">
        <svg viewBox="0 0 60 60" width="100%" height="100%" style="transform:scale(-1);"><path d="{{ $cornerPath }}" stroke="#9a7a50" stroke-width="1.2" fill="none" opacity="0.6"/><rect x="4" y="4" width="16" height="16" stroke="#9a7a50" stroke-width="1.5" fill="none"/></svg>
    </div>

    {{-- ── Vertically centered content ──────────────────────────────────────── --}}
    <table style="width:297mm;height:210mm;border-collapse:collapse;">
        <tr>
            <td style="vertical-align:middle;text-align:center;padding:0 30mm;">

                <div style="font-family:'DejaVu Sans',sans-serif;font-size:8pt;letter-spacing:4px;text-transform:uppercase;color:#8a6a30;font-weight:bold;margin-bottom:3mm;">
                    MNCH &bull; Ministry of Health, Kenya
                </div>

                <div style="font-family:'DejaVu Serif',serif;font-size:34pt;font-weight:bold;color:#2a1f0a;letter-spacing:3px;text-transform:uppercase;line-height:1;margin-bottom:1.5mm;">
                    Certificate
                </div>
                <div style="font-family:'DejaVu Serif',serif;font-style:italic;font-size:13pt;color:#6b5020;margin-bottom:5mm;">
                    of Completion
                </div>

                <div style="display:block;margin:3mm auto;width:70mm;height:1px;background:#9a7a50;position:relative;">
                    <span style="position:absolute;top:-3.5mm;left:50%;margin-left:-3mm;width:6mm;text-align:center;background:#faf6ed;color:#9a7a50;font-size:12pt;">&#10022;</span>
                </div>

                <div style="font-family:'DejaVu Sans',sans-serif;font-size:8pt;letter-spacing:2px;text-transform:uppercase;color:#8a7a60;margin-bottom:3mm;">
                    This certificate is proudly presented to
                </div>

                <div style="font-family:'DejaVu Serif',serif;font-style:italic;font-size:24pt;color:#1a1005;line-height:1.1;margin-bottom:3mm;display:inline-block;border-bottom:1.5px solid #9a7a50;padding-bottom:1.5mm;">
                    {{ $participant->user?->full_name ?? trim(($participant->user?->first_name ?? '').' '.($participant->user?->last_name ?? '')) }}
                </div>

                <div style="font-family:'DejaVu Sans',sans-serif;font-size:8pt;letter-spacing:2px;text-transform:uppercase;color:#8a7a60;margin-top:3mm;margin-bottom:2mm;">
                    in recognition of successful completion of
                </div>

                @php
                    $moduleCount = $modules->count();
                    $year = $participant->head_drmh_approved_at
                        ? \Carbon\Carbon::parse($participant->head_drmh_approved_at)->format('Y')
                        : now()->format('Y');
                @endphp
                <div style="font-family:'DejaVu Serif',serif;font-size:16pt;font-weight:bold;color:#2a1f0a;margin-bottom:1.5mm;">
                    {{ $class->training?->program?->name ?? 'MNCH Mentorship Program' }}
                </div>
                <div style="font-family:'DejaVu Sans',sans-serif;font-size:9pt;color:#6b5b45;letter-spacing:0.5px;margin-bottom:5mm;">
                    {{ $moduleCount }} module{{ $moduleCount === 1 ? '' : 's' }} completed &nbsp;&bull;&nbsp; {{ $year }}
                    @if($class->training?->facility?->name)&nbsp;&bull;&nbsp; {{ $class->training->facility->name }}@endif
                </div>

                {{-- QR verification code — centered --}}
                @if(!empty($qr))
                <div style="margin:0 auto 5mm;width:22mm;">
                    <img src="{{ $qr }}" alt="Verify" style="width:22mm;height:22mm;display:block;margin:0 auto;">
                    <div style="font-family:'DejaVu Sans',sans-serif;font-size:6pt;letter-spacing:1px;text-transform:uppercase;color:#9a8a70;margin-top:1.5mm;">Scan to Verify</div>
                </div>
                @endif

                <div style="display:block;margin:2mm auto 6mm;width:70mm;height:1px;background:#9a7a50;position:relative;">
                    <span style="position:absolute;top:-3.5mm;left:50%;margin-left:-3mm;width:6mm;text-align:center;background:#faf6ed;color:#9a7a50;font-size:12pt;">&#10022;</span>
                </div>

                {{-- Signatures --}}
                @php
                    $programName = strtolower($class->training?->program?->name ?? '');
                    $isEmonc = str_contains($programName, 'maternal') && str_contains($programName, 'emonc');
                    $headLabel = $isEmonc ? 'Head DRMH' : 'Head MNCH';
                @endphp
                <table style="width:190mm;margin:0 auto;border-collapse:collapse;">
                    <tr>
                        <td style="width:33%;text-align:center;">
                            <div style="width:50mm;height:1px;background:#9a7a50;margin:0 auto 2mm;"></div>
                            <div style="font-family:'DejaVu Serif',serif;font-style:italic;font-size:11pt;color:#2a1f0a;">{{ $participant->mentorApprovedBy?->full_name ?? ($class->training?->mentor?->full_name ?? 'Lead Mentor') }}</div>
                            <div style="font-family:'DejaVu Sans',sans-serif;font-size:6.5pt;letter-spacing:1px;text-transform:uppercase;color:#9a8a70;">Facility Mentor</div>
                        </td>
                        <td style="width:33%;text-align:center;">
                            <div style="width:50mm;height:1px;background:#9a7a50;margin:0 auto 2mm;"></div>
                            <div style="font-family:'DejaVu Serif',serif;font-style:italic;font-size:11pt;color:#2a1f0a;">{{ $participant->headDrmhApprovedBy?->full_name ?? 'Director, MNCH Division' }}</div>
                            <div style="font-family:'DejaVu Sans',sans-serif;font-size:6.5pt;letter-spacing:1px;text-transform:uppercase;color:#9a8a70;">{{ $headLabel }}</div>
                        </td>
                        <td style="width:33%;text-align:center;">
                            <div style="width:50mm;height:1px;background:#9a7a50;margin:0 auto 2mm;"></div>
                            <div style="font-family:'DejaVu Serif',serif;font-style:italic;font-size:11pt;color:#2a1f0a;">
                                {{ $participant->head_drmh_approved_at ? \Carbon\Carbon::parse($participant->head_drmh_approved_at)->format('d M Y') : now()->format('d M Y') }}
                            </div>
                            <div style="font-family:'DejaVu Sans',sans-serif;font-size:6.5pt;letter-spacing:1px;text-transform:uppercase;color:#9a8a70;">Date of Issue</div>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

    {{-- ── Certificate number + CPD (bottom-left, subtle) ──────────────────── --}}
    <div style="position:absolute;bottom:14mm;left:20mm;font-family:'DejaVu Sans',sans-serif;font-size:6.5pt;color:#9a8a70;letter-spacing:0.5px;">
        CERT NO. MNCH-{{ str_pad($participant->id, 6, '0', STR_PAD_LEFT) }}-{{ $year }}
        @if(isset($cpd) && $cpd['total'] > 0)
        &nbsp;&bull;&nbsp; {{ $cpd['total'] }} CPD PTS ({{ $cpd['level']['name'] ?? 'Foundation' }})
        @endif
    </div>

</div>
</div>

</body>
</html>

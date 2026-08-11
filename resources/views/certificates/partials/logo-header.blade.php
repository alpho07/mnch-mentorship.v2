{{--
    Dual-logo header — Ministry of Health crest + the platform's own mark,
    side by side with a thin divider, matching the site nav's own MOH-logo
    | divider | MNCH-Kenya-mark pattern (resources/views/layouts/app.blade.php).
    Built with a <table> rather than flex — DomPDF's flexbox support is
    unreliable, tables render consistently across every cert template.
    Params (all optional): $logoHeight, $iconSize, $textSize, $textColor,
    $dividerColor, $marginBottom, $align ('center', the default, or 'left').
--}}
@php
    $mohLogoDataUri = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('moh_logo.png')));
    $tableMargin = ($align ?? 'center') === 'left' ? '0 0 '.($marginBottom ?? '4mm').' 0' : '0 auto '.($marginBottom ?? '4mm');
@endphp
<table style="margin:{{ $tableMargin }};border-collapse:collapse;">
    <tr>
        <td style="vertical-align:middle;padding-right:4mm;">
            <img src="{{ $mohLogoDataUri }}" alt="Ministry of Health" style="height:{{ $logoHeight ?? '11mm' }};width:auto;display:block;">
        </td>
        <td style="vertical-align:middle;padding:0 4mm;border-left:1px solid {{ $dividerColor ?? '#c8a87a' }};border-right:1px solid {{ $dividerColor ?? '#c8a87a' }};font-size:1px;line-height:{{ $logoHeight ?? '11mm' }};">&nbsp;</td>
        <td style="vertical-align:middle;padding-left:4mm;">
            <table style="border-collapse:collapse;">
                <tr>
                    <td style="vertical-align:middle;padding-right:2.5mm;">
                        {{-- Solid fill, not a CSS gradient — DomPDF's gradient
                             support is unreliable and silently drops it. --}}
                        <div style="width:{{ $iconSize ?? '9mm' }};height:{{ $iconSize ?? '9mm' }};border-radius:2mm;background-color:#1D6FB8;">
                            <svg viewBox="0 0 24 24" width="100%" height="100%">
                                <rect x="10" y="4" width="4" height="16" rx="1" fill="#ffffff"/>
                                <rect x="4" y="10" width="16" height="4" rx="1" fill="#ffffff"/>
                            </svg>
                        </div>
                    </td>
                    <td style="vertical-align:middle;">
                        <span style="font-family:'DejaVu Sans',sans-serif;font-weight:bold;font-size:{{ $textSize ?? '11pt' }};color:{{ $textColor ?? '#1a1005' }};white-space:nowrap;"><span style="color:#1D6FB8;">MNCH</span> Kenya</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

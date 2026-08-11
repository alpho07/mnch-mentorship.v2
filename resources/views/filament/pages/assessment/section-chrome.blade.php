@php
    $sectionKeys = array_keys($sections);
    $currentIndex = array_search($currentKey, $sectionKeys, true);
    $position = $currentIndex === false ? null : $currentIndex + 1;
    $total = count($sections);
    $doneCount = collect($sections)->filter(fn ($s) => $s['done'])->count();
@endphp

<div class="aqs-progress">
    <div class="aqs-progress-head">
        <span class="aqs-progress-label">
            @if($position)
                Section {{ $position }} of {{ $total }}
            @else
                Assessment Section
            @endif
        </span>
        <span class="aqs-progress-count">{{ $doneCount }}/{{ $total }} completed</span>
    </div>
    <div class="aqs-progress-dots">
        @foreach ($sections as $code => $section)
            <a
                href="{{ $section['route'] }}"
                class="aqs-dot {{ $section['done'] ? 'is-done' : '' }} {{ $code === $currentKey ? 'is-current' : '' }}"
                title="{{ $section['label'] }}"
            ></a>
        @endforeach
    </div>
</div>

<style>
    /* ── Section progress strip ─────────────────────────────────────── */
    .aqs-progress{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:1rem 1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(15,23,42,.05)}
    .aqs-progress-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;flex-wrap:wrap;gap:.4rem}
    .aqs-progress-label{font-size:.85rem;font-weight:700;color:#111827}
    .aqs-progress-count{font-size:.78rem;font-weight:600;color:#16a34a}
    .aqs-progress-dots{display:flex;flex-wrap:wrap;gap:.4rem}
    .aqs-dot{width:28px;height:8px;border-radius:99px;background:#e5e7eb;transition:background .15s,transform .15s;display:block}
    .aqs-dot:hover{transform:scaleY(1.35)}
    .aqs-dot.is-done{background:#16a34a}
    .aqs-dot.is-current{background:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.18)}
    @media(max-width:480px){.aqs-dot{width:18px}}

    /* ── Section wrapper ──────────────────────────────────────────────── */
    .aqs.fi-section{border-radius:16px!important}
    .aqs .fi-section-header{padding:1.35rem 1.5rem!important}
    .aqs .fi-section-header-heading{font-size:1.15rem!important}
    .aqs .fi-section-content{padding:.25rem 1.5rem 1.5rem!important}
    @media(max-width:640px){
        .aqs .fi-section-header{padding:1.1rem 1.1rem!important}
        .aqs .fi-section-content{padding:.25rem 1.1rem 1.25rem!important}
    }

    /* ── Field rows ───────────────────────────────────────────────────── */
    .aqs .fi-fo-field-wrp{padding:1.15rem 0;border-bottom:1px solid #f1f5f9}
    .aqs .fi-fo-field-wrp:last-child{border-bottom:none}
    .aqs .fi-fo-field-wrp-label span{font-size:.92rem!important;font-weight:600!important;color:#1e293b!important;line-height:1.45!important}

    /* ── Yes / No / Partial pills ────────────────────────────────────── */
    .aqs .fi-fo-radio{gap:.6rem!important}
    .aqs .fi-fo-radio label{
        display:flex;align-items:center;gap:.5rem;
        min-height:2.75rem;min-width:5.25rem;
        padding:.55rem 1.15rem;border-radius:10px;
        border:1.75px solid #e2e8f0;background:#fff;
        font-size:.85rem;font-weight:700;color:#475569;
        cursor:pointer;transition:border-color .15s,background .15s,transform .1s;
        justify-content:center;
    }
    .aqs .fi-fo-radio label:hover{border-color:#93c5fd;background:#f8fafc}
    .aqs .fi-fo-radio label:active{transform:scale(.97)}
    .aqs .fi-radio-input{width:17px;height:17px;flex-shrink:0}

    .aqs .fi-fo-radio label:has(input[value="Yes"]:checked){border-color:#16a34a;background:#f0fdf4;color:#15803d;accent-color:#16a34a}
    .aqs .fi-fo-radio label:has(input[value="No"]:checked){border-color:#dc2626;background:#fef2f2;color:#b91c1c;accent-color:#dc2626}
    .aqs .fi-fo-radio label:has(input[value="Partially"]:checked){border-color:#d97706;background:#fffbeb;color:#b45309;accent-color:#d97706}
    .aqs .fi-fo-radio label:has(input:checked){box-shadow:0 1px 2px rgba(15,23,42,.06)}

    @media(max-width:480px){
        .aqs .fi-fo-radio{flex-direction:column;align-items:stretch!important}
        .aqs .fi-fo-radio label{width:100%}
    }

    /* ── Explanation callout ─────────────────────────────────────────── */
    .aqs .fi-fo-field-wrp:has(textarea[id*="_explanation"]){padding-top:0;margin-top:-.6rem}
    .aqs .fi-fo-field-wrp:has(textarea[id*="_explanation"]) .fi-fo-field-wrp-label span{
        font-size:.74rem!important;font-weight:700!important;color:#b45309!important;
        text-transform:uppercase;letter-spacing:.04em;
    }
    .aqs textarea[id*="_explanation"]{border-color:#fde68a!important;background:#fffbeb!important}
    .aqs textarea[id*="_explanation"]:focus{border-color:#d97706!important;box-shadow:0 0 0 3px rgba(217,119,6,.15)!important}
</style>

@php
    /**
     * A4 executive brief, two pages.
     *
     * Palette is the platform's own executive-report system: teal #0097A7
     * on a slate scale, with the same success/danger tints the assessment
     * executive dashboard uses. No full-bleed panels — the sheet stays
     * light, which reads as a briefing paper rather than a dashboard export.
     *
     * dompdf constraints this layout is built around:
     *  - no flexbox or grid; every column is a table cell
     *  - no CSS custom properties; colours are written out
     *  - only DejaVu faces are embedded, so no remote webfonts
     */
    $meta = $brief['meta']; 
    $h = $brief['headline'];
    $coverage = $brief['coverage'];
    $depth = $brief['depth'];
    $monthly = $brief['monthly'];
    $n = fn ($v) => number_format((int) $v);
@endphp
<style>
    @page { margin: 17pt 26pt 14pt 26pt; }

    .exec-brief, .exec-brief * { box-sizing: border-box; }

    .exec-brief {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 6pt;
        line-height: 1.21;
        color: #1e293b;
        margin: 0;
        background: #ffffff;
    }

    .exec-brief table { border-collapse: collapse; width: 100%; }
    .exec-brief td, .exec-brief th { vertical-align: top; }

    /* ============================ MASTHEAD ============================ */
    .exec-brief .masthead { border-bottom: 2pt solid #0097A7; padding-bottom: 5pt; }
    .exec-brief .wordmark {
        font-size: 11.4pt;
        font-weight: bold;
        color: #0f172a;
        letter-spacing: -0.35pt;
        line-height: 1.12;
    }
    .exec-brief .wordmark-sub {
        font-size: 6.2pt;
        font-weight: bold;
        color: #0097A7;
        letter-spacing: 0.75pt;
        text-transform: uppercase;
        padding-top: 2.5pt;
    }
    .exec-brief .stamp { text-align: right; }
    .exec-brief .chip {
        background: #0097A7;
        color: #ffffff;
        font-size: 6.4pt;
        font-weight: bold;
        letter-spacing: 1.5pt;
        padding: 3pt 8pt;
    }
    .exec-brief .stamp-meta {
        font-size: 5.9pt;
        color: #64748b;
        line-height: 1.5;
        padding-top: 4pt;
    }
    .exec-brief .stamp-meta b { color: #334155; font-weight: bold; }

    /* ============================ STANDFIRST ============================ */
    .exec-brief .standfirst {
        font-size: 6.3pt;
        line-height: 1.4;
        color: #334155;
        padding: 6pt 0 5.5pt 0;
    }
    .exec-brief .standfirst b { color: #0f172a; }
    .exec-brief .defn-k {
        font-weight: bold;
        color: #0097A7;
        text-transform: uppercase;
        letter-spacing: 0.6pt;
        font-size: 6pt;
    }
    /* The scope note sits under the agreed definition without competing with it. */
    .exec-brief .defn-scope { color: #64748b; }

    /* ============================ MODULE COLUMNS ============================ */
    /* Two stacked lists side by side — curriculum under delivery, and the
       indicator framework. Table cells, not columns: dompdf has no multi-col. */
    .exec-brief .mods { margin-top: 4.5pt; }
    .exec-brief .mods td.col {
        width: 50%;
        border: 1pt solid #e2e8f0;
        border-top: 2pt solid #0097A7;
        padding: 3.5pt 7pt 3pt 7pt;
    }
    .exec-brief .mods td.gap { border: none; width: 11pt; padding: 0; }
    .exec-brief .mods .hd {
        font-size: 6.3pt;
        font-weight: bold;
        color: #0f172a;
        padding-bottom: 2pt;
        border-bottom: 0.5pt solid #e2e8f0;
        margin-bottom: 1.8pt;
    }
    .exec-brief .mods .hd .ct {
        font-size: 5.5pt;
        font-weight: bold;
        color: #0097A7;
        text-transform: uppercase;
        letter-spacing: 0.55pt;
    }
    .exec-brief .mods .ln {
        font-size: 5.6pt;
        color: #334155;
        padding: 0.6pt 0;
        border-bottom: 0.5pt solid #f8fafc;
    }
    .exec-brief .mods .ln .nm { }
    /* Indicator reference code, set narrow so the names stay aligned. */
    .exec-brief .mods .ln .ic {
        display: inline-block;
        width: 22pt;
        color: #0097A7;
        font-weight: bold;
        font-size: 5.3pt;
    }
    .exec-brief .mods .ln .vl {
        float: right;
        font-weight: bold;
        color: #0f172a;
        padding-left: 6pt;
    }

    /* ============================ KPI BAND ============================ */
    .exec-brief .kpi {
        background: #f8fafc;
        border-top: 1pt solid #e2e8f0;
        border-bottom: 1pt solid #e2e8f0;
    }
    .exec-brief .kpi td {
        width: 25%;
        padding: 6pt 9pt 5.5pt 9pt;
        border-left: 1pt solid #e2e8f0;
    }
    .exec-brief .kpi td.first { border-left: none; }
    .exec-brief .kpi .fig {
        font-size: 17.5pt;
        font-weight: bold;
        color: #0f172a;
        letter-spacing: -0.8pt;
        line-height: 1;
    }
    .exec-brief .kpi .fig small {
        font-size: 8.5pt;
        color: #94a3b8;
        letter-spacing: -0.2pt;
        font-weight: bold;
    }
    .exec-brief .kpi .cap {
        font-size: 5.6pt;
        text-transform: uppercase;
        letter-spacing: 0.72pt;
        color: #0097A7;
        font-weight: bold;
        padding-top: 4pt;
    }
    .exec-brief .kpi .note {
        font-size: 5.7pt;
        color: #94a3b8;
        padding-top: 2pt;
        line-height: 1.34;
    }

    .exec-brief .subband {
        padding: 5.5pt 0 0 0;
        font-size: 6.1pt;
        color: #475569;
    }
    .exec-brief .subband b { color: #0f172a; font-size: 7.2pt; }

    /* ============================ PART HEADINGS ============================ */
    /* The brief divides in two: what mentorship delivered, then what the
       sites were equipped with. A solid teal band separates them so the
       reader knows the subject has changed, not just the section. */
    .exec-brief .part {
        background: #0097A7;
        margin-top: 6pt;
    }
    .exec-brief .part td {
        padding: 2.4pt 9pt 2.4pt 9pt;
        vertical-align: middle;
    }
    .exec-brief .part .pk {
        font-size: 5.4pt;
        font-weight: bold;
        color: #a7e5ec;
        text-transform: uppercase;
        letter-spacing: 1.1pt;
        padding-right: 8pt;
    }
    .exec-brief .part .p-ttl {
        font-size: 7.4pt;
        font-weight: bold;
        color: #ffffff;
        letter-spacing: 0.2pt;
    }
    .exec-brief .part td.pr {
        text-align: right;
        font-size: 5.7pt;
        color: #cdf0f4;
    }

    /* ============================ SECTIONS ============================ */
    .exec-brief .sec { padding-top: 5pt; }
    .exec-brief .sec-h { border-bottom: 1pt solid #0f172a; padding-bottom: 2.6pt; }
    .exec-brief .sec-h .num {
        font-size: 7pt;
        font-weight: bold;
        color: #0097A7;
        padding-right: 6pt;
    }
    .exec-brief .sec-h .ttl {
        font-size: 7.2pt;
        font-weight: bold;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 1.1pt;
    }
    .exec-brief .sec-note {
        font-size: 5.9pt;
        color: #64748b;
        line-height: 1.32;
        padding: 2.5pt 0 0 0;
    }

    /* ============================ CURRICULUM CARDS ============================ */
    .exec-brief .prog { margin-top: 4.5pt; }
    .exec-brief .prog td {
        width: 50%;
        border: 1pt solid #e2e8f0;
        border-top: 2pt solid #0097A7;
        padding: 5pt 8pt 4.5pt 8pt;
    }
    .exec-brief .prog td.gap { border: none; width: 11pt; padding: 0; }
    .exec-brief .prog .nm {
        font-size: 7.5pt;
        font-weight: bold;
        color: #0f172a;
        padding-bottom: 3pt;
    }
    .exec-brief .prog .row { font-size: 5.9pt; color: #475569; padding: 0.6pt 0; }
    .exec-brief .prog .row b { color: #0f172a; font-size: 7pt; }

    /* ============================ DATA TABLES ============================ */
    .exec-brief table.data { margin-top: 4.5pt; }
    .exec-brief table.data th {
        font-size: 5.5pt;
        text-transform: uppercase;
        letter-spacing: 0.6pt;
        color: #94a3b8;
        text-align: right;
        font-weight: bold;
        padding: 0 5pt 2.4pt 5pt;
        border-bottom: 1pt solid #cbd5e1;
    }
    .exec-brief table.data th.l, .exec-brief table.data td.l { text-align: left; }
    .exec-brief table.data th.l { padding-left: 0; }
    .exec-brief table.data td {
        font-size: 6pt;
        text-align: right;
        color: #334155;
        padding: 1.3pt 5pt;
        border-bottom: 0.5pt solid #f1f5f9;
    }
    .exec-brief table.data td.l { color: #1e293b; padding-left: 0; }
    .exec-brief table.data tr.tot td {
        border-top: 1pt solid #0f172a;
        border-bottom: none;
        font-weight: bold;
        color: #0f172a;
        padding-top: 3pt;
    }
    .exec-brief .zero { color: #cbd5e1; }
    .exec-brief .yes { color: #16a34a; font-weight: bold; }
    .exec-brief .no { color: #cbd5e1; font-weight: bold; }
    /* Partial dispatch marker, kept small so it annotates rather than competes. */
    .exec-brief .pt {
        font-size: 4.6pt;
        color: #d97706;
        font-weight: bold;
        vertical-align: super;
        padding-left: 0.5pt;
    }

    /* The 29-site table carries twelve columns, so it runs tighter than the
       other data tables and drops the inter-column padding. */
    .exec-brief table.lab td { padding: 1.05pt 2pt; font-size: 5.7pt; }
    .exec-brief table.lab th { padding: 0 2pt 2.4pt 2pt; font-size: 5.1pt; letter-spacing: 0.3pt; }
    .exec-brief table.lab td.l, .exec-brief table.lab th.l { padding-left: 0; }

    /* Starts the pulse oximeter section on a fresh sheet. dompdf honours
       page-break-before on a block element; the padding reset stops the
       section heading floating away from the top of the new page. */
    .exec-brief .pagebreak { page-break-before: always; padding-top: 0; }

    /* Amber, for a partial state that is neither done nor outstanding. */
    .exec-brief .pt-t { color: #d97706; font-weight: bold; }

    /* 47 counties in two column-pairs, so the table runs tighter. */
    .exec-brief table.pox td { padding: 1.15pt 3pt; font-size: 5.8pt; }
    .exec-brief table.pox th { padding: 0 3pt 2.4pt 3pt; font-size: 5.2pt; }
    .exec-brief table.pox td.l, .exec-brief table.pox th.l { padding-left: 0; }

    /* ============================ ROLL-OUT FUNNEL ============================ */
    .exec-brief .funnel {
        margin-top: 5pt;
        background: #f8fafc;
        border-top: 1pt solid #e2e8f0;
        border-bottom: 1pt solid #e2e8f0;
    }
    .exec-brief .funnel td {
        width: 20%;
        padding: 5pt 7pt 4.5pt 7pt;
        border-left: 1pt solid #e2e8f0;
        text-align: center;
    }
    .exec-brief .funnel td.first { border-left: none; }
    /* The pulse oximeter funnel carries three cells rather than five. */
    .exec-brief .funnel.two td { width: 50%; }
    .exec-brief .funnel.three td { width: 33.33%; }
    .exec-brief .funnel .fg {
        font-size: 12.5pt;
        font-weight: bold;
        color: #0f172a;
        letter-spacing: -0.5pt;
        line-height: 1;
    }
    .exec-brief .funnel .fg small { font-size: 6.6pt; color: #94a3b8; font-weight: bold; }
    .exec-brief .funnel .fc {
        font-size: 5.3pt;
        text-transform: uppercase;
        letter-spacing: 0.6pt;
        color: #0097A7;
        font-weight: bold;
        padding-top: 3pt;
    }
    .exec-brief .ontrack { color: #16a34a; font-weight: bold; }
    .exec-brief .stalling { color: #dc2626; font-weight: bold; }

    /* ============================ CALLOUTS ============================ */
    .exec-brief .alert {
        background: #fff1f2;
        border-left: 2.5pt solid #dc2626;
        padding: 5pt 8pt 4.5pt 8pt;
        margin-top: 5.5pt;
    }
    .exec-brief .alert .t {
        font-size: 6.4pt;
        font-weight: bold;
        color: #991b1b;
        text-transform: uppercase;
        letter-spacing: 0.55pt;
        padding-bottom: 2.5pt;
    }
    .exec-brief .alert .b { font-size: 5.9pt; color: #7f2b2b; line-height: 1.38; }

    .exec-brief .reco {
        background: #f8fafc;
        border-left: 2.5pt solid #0097A7;
        padding: 5pt 8pt 4.5pt 8pt;
        margin-top: 5.5pt;
    }
    .exec-brief .reco .k {
        font-size: 5.6pt;
        letter-spacing: 0.9pt;
        text-transform: uppercase;
        color: #0097A7;
        font-weight: bold;
        padding-bottom: 2.5pt;
    }
    .exec-brief .reco .t {
        font-size: 7.2pt;
        font-weight: bold;
        color: #0f172a;
        padding-bottom: 2pt;
    }
    .exec-brief .reco .b { font-size: 5.9pt; color: #475569; line-height: 1.38; }
    .exec-brief .reco .b b { color: #0f172a; font-weight: bold; }

    /* ============================ COLOPHON ============================ */
    .exec-brief .foot {
        border-top: 1pt solid #e2e8f0;
        margin-top: 7pt;
        padding-top: 4.5pt;
        font-size: 5.1pt;
        color: #94a3b8;
        line-height: 1.38;
    }
    .exec-brief .foot .org {
        font-size: 6.4pt;
        font-weight: bold;
        color: #0f172a;
        letter-spacing: 0.35pt;
    }
    .exec-brief .foot .sub {
        font-size: 5.4pt;
        font-weight: bold;
        letter-spacing: 0.85pt;
        text-transform: uppercase;
        color: #0097A7;
        padding-top: 2pt;
    }
</style>

<div class="exec-brief">

{{-- ============================== MASTHEAD ============================== --}}
<table class="masthead">
    <tr>
        <td>
            <div class="wordmark">Newborn &amp; Child Health Mentorship Programme</div>
            <div class="wordmark-sub">{{ $meta['ministry'] }} &nbsp;&#124;&nbsp; {{ $meta['division'] }}</div>
        </td>
        <td class="stamp" style="width: 30%;">
            <span class="chip">{{ $meta['windowLabel'] }}</span>
            <div class="stamp-meta">
                <b>{{ $meta['audience'] }}</b><br>
                Month {{ $meta['monthsElapsed'] }} of {{ $meta['monthsTotal'] }} &nbsp;&#124;&nbsp; {{ $meta['contact'] }}
            </div>
        </td>
    </tr>
</table>

<div class="standfirst">
    <span class="defn-k">Mentorship</span> is a structured, continuous, workplace-based capacity-building approach in
    which experienced healthcare professionals provide guidance, coaching, and supportive supervision to strengthen the
    knowledge, clinical skills, confidence, and competence of healthcare workers. It reinforces practical skills and
    promotes the delivery of safe, standardized, and evidence-based care for the prevention, early detection, and
    management of common newborn and childhood illnesses and emergencies, ultimately contributing to better newborn and
    child health outcomes.
    <span class="defn-scope">This brief reports mentorship <b>actually delivered</b> inside health facilities between
    {{ $meta['windowStart']->format('F Y') }} and {{ $meta['windowEnd']->format('F Y') }} — not mentorship planned or
    budgeted. Pilot sites and mentorships with no enrolled health worker are excluded.</span>
</div>

{{-- ============================== KPI BAND ============================== --}}
<table class="kpi">
    <tr>
        <td class="first">
            <div class="fig">{{ $n($h['mentorships']) }}</div>
            <div class="cap">Live mentorships</div>
            <div class="note">{{ $h['mentorshipsCompleted'] }} completed, {{ $h['mentorshipsActive'] }} still running</div>
        </td>
        <td>
            <div class="fig">{{ $h['countiesPriorityReached'] }}<small> / {{ $h['countiesPriority'] }}</small></div>
            <div class="cap">Priority counties reached</div>
            <div class="note">{{ number_format($h['countiesPriorityReached'] / max($h['countiesPriority'], 1) * 100) }}% of the priority map</div>
        </td>
        <td>
            <div class="fig">{{ $n($h['mentees']) }}</div>
            <div class="cap">Health workers enrolled</div>
            <div class="note">across {{ $h['classes'] }} mentorship classes</div>
        </td>
        <td>
            <div class="fig">{{ $n($h['mentors']) }}</div>
            <div class="cap">Active facility mentors</div>
            <div class="note">mentoring inside their own facilities</div>
        </td>
    </tr>
</table>

<div class="subband">
    <b>{{ $h['facilities'] }}</b> facilities delivering &nbsp;&#124;&nbsp;
    <b>{{ $n($depth['modulesTotal']) }}</b> curriculum modules placed into classes &nbsp;&#124;&nbsp;
    <b>{{ $n($depth['menteeModules']['completed']) }}</b> module completions signed off by a mentor
</div>

{{-- ============================== PART ONE ============================== --}}
<table class="part"><tr>
    <td><span class="pk">Part one</span><span class="p-ttl">Mentorship</span></td>
    <td class="pr">Delivery, reach, indicators and monthly progress</td>
</tr></table>

{{-- ============================== 01 CURRICULUM ============================== --}}
<div class="sec">
    <table class="sec-h"><tr>
        <td style="width: 16pt;"><span class="num">01</span></td>
        <td><span class="ttl">Delivery by curriculum</span></td>
    </tr></table>
    <table class="prog">
        <tr>
            @foreach ($brief['programmes'] as $i => $p)
                @if ($i > 0)<td class="gap"></td>@endif
                <td>
                    <div class="nm">{{ $p['name'] }}</div>
                    <div class="row"><b>{{ $p['mentorships'] }}</b> mentorships in <b>{{ $p['facilities'] }}</b> facilities</div>
                    <div class="row"><b>{{ $n($p['mentees']) }}</b> health workers across <b>{{ $p['classes'] }}</b> classes</div>
                    <div class="row"><b>{{ $p['modulesCompleted'] }}</b> of <b>{{ $p['modules'] }}</b> curriculum modules taken to completion</div>
                </td>
            @endforeach
        </tr>
    </table>
</div>

{{-- ============================== 02 COVERAGE ============================== --}}
<div class="sec">
    <table class="sec-h"><tr>
        <td style="width: 16pt;"><span class="num">02</span></td>
        <td><span class="ttl">Where mentorship is happening</span></td>
    </tr></table>

    @php
        $rows = $coverage['counties']->values();
        $cols = 3;
        $perCol = (int) ceil($rows->count() / $cols);
    @endphp
    <table class="data">
        <tr>
            @for ($c = 0; $c < $cols; $c++)
                @if ($c > 0)<th class="l" style="width: 3%;"></th>@endif
                <th class="l" style="width: 16%;">County</th>
                <th style="width: 8%;">Mentorships</th>
                <th style="width: 7%;">Enrolled</th>
            @endfor
        </tr>
        @for ($i = 0; $i < $perCol; $i++)
            <tr>
                @for ($c = 0; $c < $cols; $c++)
                    @php $r = $rows[$i + ($c * $perCol)] ?? null; @endphp
                    @if ($c > 0)<td></td>@endif
                    <td class="l">{{ $r['name'] ?? '' }}@if ($r && ! $r['isPriority'])<span class="zero"> &dagger;</span>@endif</td>
                    <td>{{ $r['mentorships'] ?? '' }}</td>
                    <td>{{ $r['mentees'] ?? '' }}</td>
                @endfor
            </tr>
        @endfor
        <tr class="tot">
            <td class="l">All {{ $h['countiesReached'] }} counties</td>
            <td>{{ $h['mentorships'] }}</td>
            <td>{{ $n($h['mentees']) }}</td>
            <td colspan="{{ ($cols - 1) * 4 }}"></td>
        </tr>
    </table>

    @php
        // "41 Newborn Care and 17 Infant and Child Care" — composed here rather
        // than with an inline @foreach so the sentence stays readable.
        $classSplit = $brief['programmes']
            ->map(fn ($p) => $p['classes'].' '.$p['name'])
            ->join(', ', ' and ');
    @endphp
    <div class="sec-note" style="padding-top: 7pt;">The table lists each of the {{ $h['countiesReached'] }} counties
        with mentorship under way, the mentorships running there and the health workers enrolled in them. A
        <b>mentorship</b> is one facility delivering one curriculum; within it, health workers are taught in one or
        more <b>classes</b>. Across {{ $h['facilities'] }} facilities, {{ $h['mentorships'] }} mentorships run
        {{ $h['classes'] }} classes &mdash; {{ $classSplit }} &mdash; enrolling {{ $n($h['mentees']) }} health workers,
        each counted once in the class they joined. The modules below are the curriculum those classes are working
        through.</div>

    {{-- Modules actually under delivery, newborn and infant read separately. --}}
    <table class="mods">
        <tr>
            @foreach ($brief['curriculum'] as $programme => $data)
                @if (! $loop->first)<td class="gap"></td>@endif
                <td class="col">
                    <div class="hd">{{ $programme }} &nbsp;<span class="ct">{{ $data['modules']->count() }} modules
                        &middot; {{ $data['rate'] }}% complete</span></div>
                    @foreach ($data['modules'] as $m)
                        <div class="ln">
                            <span class="nm">{{ preg_replace('/^Module\s+(\d+):\s*/i', '$1. ', $m['module']) }}</span>
                            <span class="vl @if ($m['rate'] === 0) zero @endif">{{ $m['rate'] }}%</span>
                        </div>
                    @endforeach
                </td>
            @endforeach
        </tr>
    </table>
    <div class="sec-note" style="padding-top: 4pt;"><b>Module completion rate.</b> Each percentage is how many classes
        have finished that module, out of the classes scheduled to take it. Overall,
        {{ $brief['curriculum']->sum('completed') }} of {{ $brief['curriculum']->sum('classes') }} scheduled modules
        are complete.</div>
</div>

{{-- ============================== 03 MONTH BY MONTH ============================== --}}
<div class="sec">
    <table class="sec-h"><tr>
        <td style="width: 16pt;"><span class="num">03</span></td>
        <td><span class="ttl">Month by month</span></td>
    </tr></table>
    <table class="data">
        <tr>
            <th class="l" style="width: 30%;">Indicator</th>
            @foreach ($monthly['months'] as $label)
                <th>{{ strtoupper($label) }}</th>
            @endforeach
            <th style="padding-right: 0; border-bottom-color: #0f172a;">Total</th>
        </tr>
        @foreach ($monthly['rows'] as $row)
            <tr>
                <td class="l">{{ $row['label'] }}</td>
                @foreach ($row['values'] as $v)
                    <td @class(['zero' => $v === 0])>{{ $n($v) }}</td>
                @endforeach
                <td style="font-weight: bold; color: #0f172a; padding-right: 0;">{{ $n($row['total']) }}</td>
            </tr>
        @endforeach
    </table>
</div>

{{-- ============================== 04 INDICATORS ============================== --}}
<div class="sec">
    <table class="sec-h"><tr>
        <td style="width: 16pt;"><span class="num">04</span></td>
        <td><span class="ttl">Indicators being tracked</span></td>
    </tr></table>
    <div class="sec-note">The newborn and child indicators facilities are assessed against. Reporting is still being
        established, so most carry no submissions yet.</div>

    <table class="mods">
        <tr>
            @foreach ($brief['indicators']['streams'] as $stream => $data)
                @if (! $loop->first)<td class="gap"></td>@endif
                <td class="col">
                    <div class="hd">{{ $stream }} &nbsp;<span class="ct">{{ $data['total'] }} indicators</span></div>
                    @foreach ($data['items'] as $i)
                        <div class="ln">
                            <span class="ic">{{ $i['code'] }}</span>
                            <span class="nm">{{ $i['label'] }}</span>@if ($i['bands'])<span class="vl">{{ $i['bands'] }} bands</span>@endif
                        </div>
                    @endforeach
                </td>
            @endforeach
        </tr>
    </table>
</div>

{{-- ============================== PART TWO ============================== --}}
<table class="part"><tr>
    <td><span class="pk">Part two</span><span class="p-ttl">Skills lab and equipment</span></td>
    <td class="pr">Lab readiness, manikins, air devices and pulse oximeters</td>
</tr></table>

{{-- ============================== 05 SKILLS LAB ============================== --}}
@php
    $lab = $brief['skillsLab'];
    // null reads as "not reported", 0 as a reported nil — the two are shown
    // differently everywhere in this section.
    $num = fn ($v) => $v === null ? '<span class="zero">&mdash;</span>' : ($v === 0 ? '<span class="no">0</span>' : $v);
@endphp
<div class="sec">
    <table class="sec-h"><tr>
        <td style="width: 16pt;"><span class="num">05</span></td>
        <td><span class="ttl">Skills lab</span></td>
    </tr></table>
    {{-- Roll-out funnel: the same sites, counted at each stage. --}}
    <table class="funnel two">
        <tr>
            <td class="first">
                <div class="fg">{{ $lab['functional'] }}<small>/{{ $lab['sites'] }}</small></div>
                <div class="fc">Functional lab</div>
            </td>
            <td>
                <div class="fg">{{ $lab['manikinSites'] }}<small>/{{ $lab['sites'] }}</small></div>
                <div class="fc">Equipped</div>
            </td>
        </tr>
    </table>

    <div class="sec-note" style="padding-top: 5pt;">{{ $lab['assessed'] }} of {{ $lab['sites'] }} sites have been
        assessed and sensitised and {{ $lab['rolledOut'] }} have begun roll-out, so the programme has reached almost
        every site on the list. The lab itself is where the sequence narrows: {{ $lab['functional'] }} sites have a
        functional lab, {{ $lab['roomOnly'] }} have identified a room but not equipped it, and {{ $lab['noLab'] }} have
        neither. {{ $lab['manikinSites'] }} sites hold manikins and {{ $lab['unreported'] }} have not reported their
        equipment at all &mdash; for those, what is on site is unknown rather than nil.</div>

    <table class="data lab">
        <tr>
            <th class="l" style="width: 17%;">County</th>
            <th class="l" style="width: 32%;">Facility</th>
            <th class="l" style="width: 15%; padding-left: 7pt;">Skills lab</th>
            <th style="width: 9%;">Preemie<br>Natalie</th>
            <th style="width: 9%;">Neo<br>Natalie</th>
            <th style="width: 9%;">Baby<br>Anne</th>
            <th style="width: 9%; padding-right: 0;">Air<br>devices</th>
        </tr>
        @foreach ($lab['rows'] as $r)
            <tr>
                <td class="l">{{ $r['county'] }}</td>
                <td class="l">{{ $r['facility'] }}</td>
                <td class="l" style="padding-left: 7pt;">
                    @if ($r['lab'] === 'functional')<span class="yes">Functional</span>
                    @elseif ($r['lab'] === 'room')Room only
                    @elseif ($r['lab'] === 'none')<span class="no">None</span>
                    @else<span class="zero">&mdash;</span>@endif
                </td>
                <td>{!! $num($r['preemie']) !!}</td>
                <td>{!! $num($r['neo']) !!}</td>
                <td>{!! $num($r['anne']) !!}</td>
                <td style="padding-right: 0;">{!! $num($r['air']) !!}</td>
            </tr>
        @endforeach
        <tr class="tot">
            <td class="l">{{ $lab['counties'] }} counties</td>
            <td class="l">{{ $lab['sites'] }} sites</td>
            <td class="l" style="padding-left: 7pt;">{{ $lab['functional'] }} functional</td>
            <td colspan="3">{{ $lab['manikinUnits'] }} manikins</td>
            <td style="padding-right: 0;">{{ $lab['airUnits'] }}</td>
        </tr>
    </table>

    <div class="sec-note" style="padding-top: 5pt;">A dash means the site has not reported, which is not the same as a
        reported <span class="no">0</span>. A full equipment issue is one Preemie Natalie, two Neo Natalie and one Baby
        Anne, with two air devices. Pulse oximeter distribution is reported separately overleaf.</div>
</div>

{{-- ============================== 06 PULSE OXIMETERS ============================== --}}
@php
    $pox = $brief['pulseOximeters'];
    // 47 counties in two columns so the whole country fits one page.
    $poxRows = $pox['rows'];
    $poxPer = (int) ceil($poxRows->count() / 2);
    $poxStatus = [
        'full' => ['Fully dispatched', 'yes'],
        'partial' => ['Partial', 'pt-t'],
        'none' => ['Not dispatched', 'no'],
    ];
@endphp
<div class="sec pagebreak">
    <table class="sec-h"><tr>
        <td style="width: 16pt;"><span class="num">06</span></td>
        <td><span class="ttl">Pulse oximeter distribution</span></td>
    </tr></table>

    <table class="funnel three">
        <tr>
            <td class="first">
                <div class="fg">{{ $pox['full'] }}<small>/{{ $pox['counties'] }}</small></div>
                <div class="fc">Counties with full dispatch</div>
            </td>
            <td>
                <div class="fg">{{ $n($pox['devices']) }}</div>
                <div class="fc">Devices dispatched</div>
            </td>
            <td>
                <div class="fg">{{ $n($pox['facilities']) }}</div>
                <div class="fc">Receiving facilities</div>
            </td>
        </tr>
    </table>

    <div class="sec-note" style="padding-top: 5pt;">{{ $n($pox['devices']) }} pulse oximeters have been dispatched to
        {{ $n($pox['facilities']) }} facilities. {{ $pox['full'] }} counties are fully dispatched and
        {{ $pox['partial'] }} partially &mdash; {{ $pox['reached'] }} of {{ $pox['counties'] }} in all. The remaining
        counties are marked <span class="pt-t">Pending</span>: no devices have reached them yet.
        {{ $pox['awaiting'] }} of those have already identified their receiving facilities and are waiting on the
        consignment, shown by a facility count against a pending row; the rest have not yet been tagged.</div>

    <table class="data pox">
        <tr>
            @for ($c = 0; $c < 2; $c++)
                @if ($c > 0)<th class="l" style="width: 4%;"></th>@endif
                <th class="l" style="width: 15%;">County</th>
                <th class="l" style="width: 14%; padding-left: 6pt;">Status</th>
                <th style="width: 8%;">Devices</th>
                <th style="width: 11%;">Facilities</th>
            @endfor
        </tr>
        @for ($i = 0; $i < $poxPer; $i++)
            <tr>
                @for ($c = 0; $c < 2; $c++)
                    @php $r = $poxRows[$i + ($c * $poxPer)] ?? null; @endphp
                    @if ($c > 0)<td></td>@endif
                    <td class="l">{{ $r['county'] ?? '' }}</td>
                    <td class="l" style="padding-left: 6pt;">
                        @if ($r)<span class="{{ $poxStatus[$r['status']][1] }}">{{ $poxStatus[$r['status']][0] }}</span>@endif
                    </td>
                    <td>@if ($r){!! $r['devices'] === null ? '<span class="pt-t">Pending</span>' : number_format($r['devices']) !!}@endif</td>
                    <td>@if ($r){!! $r['facilities'] === null ? '<span class="zero">&mdash;</span>' : $r['facilities'] !!}@endif</td>
                @endfor
            </tr>
        @endfor
        <tr class="tot">
            <td class="l">{{ $pox['counties'] }} counties</td>
            <td class="l" style="padding-left: 6pt;">{{ $pox['full'] }} full, {{ $pox['partial'] }} partial</td>
            <td>{{ $n($pox['devices']) }}</td>
            <td>{{ $n($pox['facilities']) }}</td>
            <td colspan="5"></td>
        </tr>
    </table>

</div>

{{-- ============================== COLOPHON ============================== --}}
<div class="foot">
    <table>
        <tr>
            <td style="width: 70%; padding-right: 18pt;">
                Facility mentorship recorded on the national mentorship platform, {{ $meta['windowStart']->format('j M Y') }}
                to {{ $meta['windowEnd']->format('j M Y') }}; pilot sites, draft setups and mentorships with no enrolled
                health worker are excluded. Counties are attributed through the facility's sub-county. Health workers are
                counted once each in every figure, monthly columns bucketing each person by the month they first enrolled;
                monthly completion columns count only records carrying a completion date. Skills-lab holdings are the
                audited manikin, air-device and pulse-oximeter distributions, matched to facilities on Master Facility
                List code. The {{ $coverage['priorityTotal'] }}-county priority set is provisional — derived from
                mortality burden and delivery volume, not from this platform — pending the gazetted list.@if ($coverage['outsidePriority']->isNotEmpty())
                    &nbsp;&dagger; Delivery outside the priority set: {{ $coverage['outsidePriority']->join(', ', ' and ') }}.
                @endif
            </td>
            <td style="width: 30%; text-align: right;">
                <div class="org">{{ $meta['division'] }}</div>
                <div class="sub">Republic of Kenya</div>
                <div style="padding-top: 3pt;">Generated {{ $meta['generatedAt']->format('j F Y, H:i') }} EAT</div>
            </td>
        </tr>
    </table>
</div>

</div>

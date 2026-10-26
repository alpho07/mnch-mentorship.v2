@extends('analytics.layout')

@section('content')
@include('analytics.partials.filters', [
    'fields' => ['year', 'county', 'type'],
    'scopeChips' => [[$f->county ? 'County: '.$f->county : 'National · all counties', '#E3F5F7', '#00505A'], [$facilityCount.' assessed facilities', '#F0F4F4', '#3D5558']],
])

@include('analytics.partials.kpis', ['kpis' => $kpis])

{{-- One Alpine scope for the readiness cards and the facility table, so a card click filters the table. --}}
<div style="display:flex;flex-direction:column;gap:36px" x-data="{
    q: '', el: '', ment: '', rd: '', page: 0, size: 10,
    rows: @js($rows),
    get filtered() {
        const q = this.q.toLowerCase();
        return this.rows.filter(r => (!q || (r.n + ' ' + r.code).toLowerCase().includes(q))
            && (!this.el || r.el === this.el)
            && (!this.ment || (this.ment === 'with' ? r.ment > 0 : r.ment === 0))
            && (!this.rd || r.rd === this.rd));
    },
    get pages() { return Math.max(1, Math.ceil(this.filtered.length / this.size)) },
    get shown() { const p = Math.min(this.page, this.pages - 1); return this.filtered.slice(p * this.size, p * this.size + this.size) },
    count(key, val) { return this.rows.filter(r => r[key] === val).length },
    set(key, val) { this[key] = val; this.page = 0 },
}">

<section aria-labelledby="rd-h">
@include('analytics.partials.sec', ['n' => 1, 'id' => 'rd-h', 'title' => 'Skills lab readiness vs mentorship', 'after' => 'Select a group to filter the facility table'])
<div class="card rise" style="padding:24px">
<div style="display:flex;height:22px;border-radius:99px;overflow:hidden;gap:3px;margin-bottom:22px" aria-hidden="true">
@foreach ($readiness as $i => $r)<div class="barx" style="width:{{ $r['w'] }};background:{{ $r['bar'] }};animation-delay:{{ number_format(0.2 + $i * 0.15, 2) }}s"></div>@endforeach
</div>
<div style="display:flex;gap:16px;flex-wrap:wrap">
@foreach ($readiness as $r)
<button type="button" class="rcard" style="background:{{ $r['bg'] }}" :class="rd === '{{ $r['key'] }}' && 'on'" :aria-pressed="rd === '{{ $r['key'] }}'" @click="set('rd', rd === '{{ $r['key'] }}' ? '' : '{{ $r['key'] }}'); $nextTick(() => document.getElementById('tb-h').scrollIntoView({ behavior: 'smooth' }))">
<span style="display:flex;align-items:center;justify-content:space-between;gap:10px"><span class="chip" style="background:#FFFFFF;color:{{ $r['ink'] }}">{{ $r['tag'] }}</span><span class="num" style="font-size:13px;font-weight:700;color:{{ $r['ink'] }}">{{ $r['pct'] }}</span></span>
<span class="disp num" style="font-size:40px;font-weight:700;line-height:1;color:#0F2A2E">{{ $r['v'] }}</span>
<span style="font-weight:600;font-size:15px;color:#0F2A2E">{{ $r['title'] }}</span>
<span style="font-size:13px;color:#3D5558">{{ $r['note'] }}</span>
<span style="font-size:13px;font-weight:600;color:{{ $r['ink'] }};display:flex;align-items:center;gap:6px"><span x-text="rd === '{{ $r['key'] }}' ? 'Showing in table — clear' : 'View facilities'">View facilities</span>@include('analytics.partials.icon', ['name' => 'arrow-right', 'size' => 15, 'sw' => 2])</span>
</button>
@endforeach
</div>
</div>
</section>

@include('analytics.partials.insights', ['insights' => $insights, 'n' => 2])

<section aria-labelledby="tr-h">
@include('analytics.partials.sec', ['n' => 3, 'id' => 'tr-h', 'title' => 'Scores & trends'])
<div class="row-flex">
<article class="card rise" style="flex:1.7 1 560px;min-width:0;padding:24px">
<div class="ch-h"><div><h3 class="disp">Assessments over time</h3><p class="muted">Last 12 months by type · {{ $assessTotal }} assessments</p></div>
<div style="display:flex;gap:12px;flex-wrap:wrap">
@foreach ([['Baseline', '#0097A7', 'baseline'], ['Midline', '#E89B1C', 'midline'], ['Endline', '#6A4FC7', 'endline']] as [$label, $c, $k])
<span @class(['muted' => ! $typeTotals[$k]]) style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px"><span style="width:12px;height:12px;border-radius:3px;background:{{ $c }}"></span>{{ $label }}@if (! $typeTotals[$k]) · 0 @endif</span>
@endforeach
</div></div>
<div style="position:relative;height:230px;margin-left:30px">
@foreach ($ticks as $t)<div style="position:absolute;left:0;right:0;top:{{ $t['top'] }};border-top:1px dashed #E3EBEC"><span class="num muted" style="position:absolute;left:-30px;top:-9px;font-size:12px;width:22px;text-align:right">{{ $t['v'] }}</span></div>@endforeach
<div style="position:absolute;inset:0;display:flex;align-items:flex-end;gap:8px;padding:0 4px">
@foreach ($months as $m)
<div style="flex:1;height:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:6px;min-width:0" title="{{ $m['label'] }}: {{ $m['v'] }}">@if ($m['has'])<span class="num" style="font-weight:700;font-size:12.5px">{{ $m['v'] }}</span>@endif
<div class="bary" style="width:100%;max-width:40px;height:{{ $m['h'] }};border-radius:8px 8px 3px 3px;overflow:hidden;display:flex;flex-direction:column-reverse;background:#E6EDEE;animation-delay:{{ $m['delay'] }}">@foreach ($m['segs'] as [$c, $v])<span style="display:block;flex:{{ $v }} 1 0;background:{{ $m['peak'] && $c === '#0097A7' ? '#00838F' : ($c === '#0097A7' ? '#4FCFDD' : $c) }}"></span>@endforeach</div></div>
@endforeach
</div>
</div>
<div style="display:flex;gap:8px;margin:10px 0 0 30px;padding:0 4px">@foreach ($months as $m)<span class="muted" style="flex:1;min-width:0;text-align:center;font-size:12px">{{ $m['label'] }}</span>@endforeach</div>
</article>
<article class="card rise" style="flex:1 1 340px;min-width:0;padding:24px;display:flex;flex-direction:column;animation-delay:.08s">
<div class="ch-h"><div><h3 class="disp">Grade distribution</h3><p class="muted">{{ $gradeTotal }} completed assessments</p></div></div>
@include('analytics.partials.donut', ['segs' => $grades, 'size' => 180, 'centre' => $avg, 'centreSub' => 'average', 'aria' => 'Grade distribution'])
</article>
</div>
<div class="row-flex" style="margin-top:20px">
<article class="card rise" style="flex:1.7 1 560px;min-width:0;padding:24px">
<div class="ch-h"><div><h3 class="disp">Section score averages</h3><p class="muted">Average % across completed assessments{{ $paired ? ' — each section is reported under two entries in the source' : '' }}</p></div>
@if ($paired)<div style="display:flex;gap:14px;flex-wrap:wrap"><span style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px"><span style="width:12px;height:12px;border-radius:50%;background:#0097A7"></span>Higher entry</span><span style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px"><span style="width:12px;height:12px;border-radius:50%;background:#FFFFFF;border:3px solid #D9465F;box-sizing:border-box"></span>Lower entry</span></div>@endif</div>
<div style="display:flex;flex-direction:column;gap:22px;padding-top:6px">
@foreach ($sections as $s)
<div style="display:grid;grid-template-columns:minmax(0,220px) minmax(0,1fr) 96px;gap:16px;align-items:center"><span style="font-weight:600;font-size:13.5px">{{ $s['label'] }}</span><div style="position:relative;height:28px"><div style="position:absolute;left:0;right:0;top:13px;height:2px;background:#EDF3F4"></div>@if ($s['pair'])<div class="barx" style="position:absolute;left:{{ $s['lo'] }};width:{{ $s['span'] }};top:11px;height:6px;border-radius:99px;background:#BFE9EE;animation-delay:{{ $s['delay'] }}"></div><span class="dot" style="position:absolute;left:{{ $s['lo'] }};top:14px;width:14px;height:14px;border-radius:50%;background:#FFFFFF;border:3px solid #D9465F;box-sizing:border-box;transform:translate(-50%,-50%);animation-delay:{{ $s['delay'] }}"></span>@endif<span class="dot" style="position:absolute;left:{{ $s['hi'] }};top:14px;width:14px;height:14px;border-radius:50%;background:#0097A7;transform:translate(-50%,-50%);animation-delay:{{ $s['delay2'] }}"></span></div><span class="num" style="text-align:right;font-size:13px"><strong>{{ $s['hv'] }}</strong>@if ($s['pair'])<span class="muted"> · {{ $s['lv'] }}</span>@endif</span></div>
@endforeach
<div style="display:grid;grid-template-columns:minmax(0,220px) minmax(0,1fr) 96px;gap:16px"><span></span><div class="muted num" style="display:flex;justify-content:space-between;font-size:12px"><span>0%</span><span>50%</span><span>100%</span></div><span></span></div>
</div>
</article>
<article class="card rise" style="flex:1 1 340px;min-width:0;padding:24px;animation-delay:.08s">
<div class="ch-h"><div><h3 class="disp">Assessment status</h3><p class="muted">{{ $statusTotal }} assessments</p></div></div>
<div style="display:flex;flex-direction:column;gap:12px">
@foreach ($status as [$label, $v, $bg])<div style="border-radius:14px;background:{{ $bg }};padding:16px;display:flex;justify-content:space-between;align-items:center"><span style="font-weight:600">{{ $label }}</span><span class="disp num" style="font-size:28px;font-weight:700">{{ $v }}</span></div>@endforeach
</div>
</article>
</div>
</section>

<section aria-labelledby="tb-h">
@include('analytics.partials.sec', ['n' => 4, 'id' => 'tb-h', 'title' => 'Facility readiness & eligibility'])
<article class="card rise" style="padding:24px">
<div style="display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap;margin-bottom:16px">
<div style="flex:1 1 280px;max-width:360px" class="fld"><label for="a-q">Search facility</label><div class="selw">@include('analytics.partials.icon', ['name' => 'search', 'size' => 16, 'sw' => 2, 'style' => 'position:absolute;left:12px;top:14px;color:#556B6E'])<input class="inp" id="a-q" type="search" placeholder="Name or MFL code" x-model.debounce.200ms="q" @input="page = 0"></div></div>
<a class="btn btn-ghost" href="{{ $exportUrl ?? '#' }}">@include('analytics.partials.icon', ['name' => 'download', 'size' => 15, 'sw' => 2])Export PDF</a>
</div>
<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:18px">
<span class="muted" style="font-size:12.5px;font-weight:600;margin-right:2px">Eligibility</span>
@foreach ([['', 'All', '#9AA9AB'], ['Eligible', 'Eligible', '#12805C'], ['Partial', 'Partial', '#E89B1C'], ['Not Eligible', 'Not eligible', '#D9465F']] as [$val, $label, $dot])
<button type="button" class="pill" :class="el === '{{ $val }}' && 'on'" :aria-pressed="el === '{{ $val }}'" @click="set('el', '{{ $val }}')"><span style="width:8px;height:8px;border-radius:50%;background:{{ $dot }}"></span>{{ $label }}<span class="num" style="opacity:.75">{{ $eligCounts[$val] }}</span></button>
@endforeach
<span style="width:1px;height:24px;background:#E1E9EA;margin:0 4px"></span>
<span class="muted" style="font-size:12.5px;font-weight:600;margin-right:2px">Mentorship</span>
@foreach ([['', 'All'], ['with', 'With mentorship'], ['without', 'Without']] as [$val, $label])
<button type="button" class="pill" :class="ment === '{{ $val }}' && 'on'" :aria-pressed="ment === '{{ $val }}'" @click="set('ment', '{{ $val }}')">{{ $label }}</button>
@endforeach
<button type="button" class="pill" x-show="rd" x-cloak @click="set('rd', '')">Readiness group ×</button>
</div>
<div style="overflow-x:auto;border:1px solid #E1E9EA;border-radius:12px">
<table class="tbl">
<thead><tr><th>Facility</th><th>Location</th><th>Latest assessment</th><th>Skills lab</th><th>Room</th><th>Feedback</th><th>Eligibility</th><th>Trained</th><th style="text-align:right">Mentorships</th></tr></thead>
<tbody>
@php($yn = fn ($v) => $v === 'Yes' ? 'background:#E7F5EF;color:#0E6A4C' : ($v === 'No' ? 'background:#F3F6F6;color:#556B6E' : 'background:#FFFFFF;color:#6B7B7D'))
@php($elStyle = ['Eligible' => ['#E7F5EF', '#0E6A4C', '#12805C'], 'Partial' => ['#FDF3E3', '#8A5000', '#E89B1C'], 'Not Eligible' => ['#FCEBEE', '#A12A41', '#D9465F']])
@foreach (array_slice($rows, 0, 10) as $r)
<tr x-show="false"><td style="min-width:220px"><div style="font-weight:600">{{ $r['n'] }}</div><div class="muted num" style="font-size:12px">MFL {{ $r['code'] }}</div></td><td style="white-space:nowrap"><div>{{ $r['county'] }}</div><div class="muted" style="font-size:12px">{{ $r['sub'] }}</div></td><td style="white-space:nowrap"><div class="num">{{ $r['date'] }} · {{ $r['type'] }}</div><div class="muted" style="font-size:12px">{{ $r['by'] }}</div></td><td><span class="yn" style="{{ $yn($r['lab']) }}">{{ $r['lab'] }}</span></td><td><span class="yn" style="{{ $yn($r['room']) }}">{{ $r['room'] }}</span></td><td>{{ $r['fb'] }}</td><td>{{ $r['el'] }}</td><td>{{ $r['trained'] }}</td><td class="num" style="text-align:right;font-weight:700">{{ $r['ment'] }}</td></tr>
@endforeach
<template x-for="r in shown" :key="r.code + r.date">
<tr class="fadein"><td style="min-width:220px"><template x-if="r.id"><a style="font-weight:600;color:#0F2A2E" :href="'{{ $assessmentUrl ?? '/assessments' }}/' + r.id + '/executive'" x-text="r.n"></a></template><div x-show="!r.id" style="font-weight:600" x-text="r.n"></div><div class="muted num" style="font-size:12px" x-text="'MFL ' + r.code"></div></td><td style="white-space:nowrap"><div x-text="r.county"></div><div class="muted" style="font-size:12px" x-text="r.sub"></div></td><td style="white-space:nowrap"><div class="num" x-text="r.date + ' · ' + r.type"></div><div class="muted" style="font-size:12px" x-text="r.by"></div></td>
<td><span class="yn" :style="r.lab === 'Yes' ? 'background:#E7F5EF;color:#0E6A4C' : (r.lab === 'No' ? 'background:#F3F6F6;color:#556B6E' : 'background:#FFFFFF;color:#6B7B7D')" x-text="r.lab"></span></td>
<td><span class="yn" :style="r.room === 'Yes' ? 'background:#E7F5EF;color:#0E6A4C' : (r.room === 'No' ? 'background:#F3F6F6;color:#556B6E' : 'background:#FFFFFF;color:#6B7B7D')" x-text="r.room"></span></td>
<td><span class="chip" :style="r.fb === 'Given' ? 'background:#E5F6F8;color:#00505A' : 'background:#FDF3E3;color:#8A5000'" x-text="r.fb"></span></td>
<td><span class="chip" :style="{ 'Eligible': 'background:#E7F5EF;color:#0E6A4C', 'Partial': 'background:#FDF3E3;color:#8A5000', 'Not Eligible': 'background:#FCEBEE;color:#A12A41' }[r.el]"><span style="width:7px;height:7px;border-radius:50%" :style="'background:' + { 'Eligible': '#12805C', 'Partial': '#E89B1C', 'Not Eligible': '#D9465F' }[r.el]"></span><span x-text="r.el"></span></span></td>
<td style="white-space:nowrap" x-text="r.trained"></td><td class="num" style="text-align:right;font-weight:700" x-text="r.ment"></td></tr>
</template>
<tr x-show="filtered.length === 0" x-cloak><td colspan="9" style="padding:40px;text-align:center"><div style="font-weight:600">No assessed facilities match these filters</div><div class="muted">Clear a filter to see more.</div></td></tr>
</tbody>
</table>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:16px">
<span class="muted num" style="font-size:13px" x-text="filtered.length ? `Showing ${Math.min(page, pages - 1) * size + 1}–${Math.min(filtered.length, Math.min(page, pages - 1) * size + size)} of ${filtered.length} assessed facilities` : 'No results'">Showing 1–{{ min(10, count($rows)) }} of {{ count($rows) }} assessed facilities</span>
<div style="display:flex;gap:8px"><button class="btn btn-ghost" type="button" @click="page = Math.max(0, page - 1)" :disabled="page === 0" aria-label="Previous page">@include('analytics.partials.icon', ['name' => 'chevron-left', 'size' => 16, 'sw' => 2])Prev</button><button class="btn btn-ghost" type="button" @click="page = Math.min(pages - 1, page + 1)" :disabled="page >= pages - 1" aria-label="Next page">Next @include('analytics.partials.icon', ['name' => 'chevron-right', 'size' => 16, 'sw' => 2])</button></div>
</div>
</article>
</section>
</div>
@endsection

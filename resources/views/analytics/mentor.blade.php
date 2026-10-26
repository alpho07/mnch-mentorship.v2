@extends('analytics.layout')

@section('content')
@include('analytics.partials.filters', [
    'fields' => ['programme', 'county', 'cadre', 'level', 'search'],
    'searchLabel' => 'Mentor', 'searchPlaceholder' => 'Search by name',
    'scopeIntro' => 'Performance matrix shows',
    'scopeChips' => [[$scope, '#E3F5F7', '#00505A']],
])

@include('analytics.partials.kpis', ['kpis' => $kpis])

<section aria-labelledby="ex-h" x-data="{ tab: 'fac', all: false }">
@include('analytics.partials.sec', ['n' => 1, 'id' => 'ex-h', 'title' => 'Needs attention', 'chip' => '<span class="chip" style="background:#FCEBEE;color:#A12A41">'.e($exceptionsTotal).' exceptions</span>'])
<article class="card rise" style="padding:24px">
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
<div class="seg-ctl" role="tablist" aria-label="Exception type">
@foreach ([['fac', 'Facilities', count($facEx)], ['inactive', 'Inactive mentors', count($inactive)], ['cpd', 'Zero CPD', count($zeroCpd)]] as [$key, $label, $count])
<button type="button" role="tab" class="seg-b" :class="tab === '{{ $key }}' && 'on'" :aria-selected="tab === '{{ $key }}'" @click="tab = '{{ $key }}'">{{ $label }}<span class="num" style="font-size:12px;padding:1px 7px;border-radius:99px" :style="tab === '{{ $key }}' ? 'background:#FCEBEE;color:#A12A41' : 'background:#FFFFFF;color:#556B6E'">{{ $count }}</span></button>
@endforeach
</div>
<span class="muted" style="font-size:13px" x-text="{ fac: 'Facilities with low completion or unusual attendance', inactive: 'Days since last review, attendance mark or feedback', cpd: 'Mentors with a closed class and no CPD points' }[tab]"></span>
</div>

<div x-show="tab === 'fac'" class="fadein">
<div class="xrow muted hide-sm" style="font-size:12px;font-weight:600;padding-top:4px;padding-bottom:8px"><span>Facility</span><span>Avg module completion</span><span>Confirmed attendance</span><span></span></div>
@forelse ($facEx as $x)
<div class="xrow"><div style="min-width:0"><div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $x['name'] }}</div><div class="muted" style="font-size:12.5px">{{ $x['classes'] }}</div></div><div style="display:flex;align-items:center;gap:10px"><span class="track" style="flex:1;height:8px"><span class="barx" style="display:block;height:100%;width:{{ $x['cw'] }};background:{{ $x['cc'] }};border-radius:99px"></span></span><span class="num" style="font-weight:700;width:40px;text-align:right">{{ $x['comp'] }}</span></div><div style="display:flex;align-items:center;gap:10px"><span class="track" style="flex:1;height:8px"><span class="barx" style="display:block;height:100%;width:{{ $x['aw'] }};background:#1D6FB8;border-radius:99px"></span></span><span class="num" style="font-weight:700;width:40px;text-align:right">{{ $x['att'] }}</span>@if ($x['flag'])<span class="chip" style="background:#FDF3E3;color:#8A5000" title="Attendance above 100% — check attendance records">Check</span>@endif</div><a class="btn btn-ghost" href="{{ $adminUrl ?? '/admin' }}" style="min-height:38px;padding:0 14px;font-size:13px">Review</a></div>
@empty
<p class="muted">No facility exceptions.</p>
@endforelse
</div>

<div x-show="tab === 'inactive'" x-cloak class="fadein">
@forelse ($inactive as $m)
<div class="xrow" style="grid-template-columns:minmax(0,1.4fr) minmax(0,2fr) auto" @if ($m['extra']) x-show="all" @endif><div style="min-width:0"><div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $m['name'] }}</div><div class="muted" style="font-size:12.5px">No reviews, attendance or feedback recorded</div></div><div style="display:flex;align-items:center;gap:10px"><span class="track" style="flex:1;height:8px"><span style="display:block;height:100%;width:{{ $m['w'] }};background:{{ $m['c'] }};border-radius:99px"></span></span><span class="num" style="font-weight:700;width:76px;text-align:right">{{ $m['days'] }} days</span></div><a class="btn btn-ghost" href="{{ $adminUrl ?? '/admin' }}" style="min-height:38px;padding:0 14px;font-size:13px">Follow up</a></div>
@empty
<p class="muted">No inactive mentors.</p>
@endforelse
@if (count($inactive) > 10)
<div style="display:flex;justify-content:center;margin-top:14px"><button class="btn btn-ghost" type="button" @click="all = !all" x-text="all ? 'Show top 10 only' : 'Show all {{ count($inactive) }} inactive mentors'">Show all {{ count($inactive) }} inactive mentors</button></div>
@endif
</div>

<div x-show="tab === 'cpd'" x-cloak class="fadein">
@forelse ($zeroCpd as $z)
<div class="xrow" style="grid-template-columns:minmax(0,1fr) auto"><div><div style="font-weight:600">{{ $z['name'] }}</div><div class="muted" style="font-size:12.5px">{{ $z['facility'] }} · {{ $z['classes'] }} {{ $z['classes'] === 1 ? 'class' : 'classes' }} led, no modules completed · 0 CPD points</div></div><a class="btn btn-ghost" href="{{ $adminUrl ?? '/admin' }}" style="min-height:38px;padding:0 14px;font-size:13px">View mentor</a></div>
@empty
<p class="muted">Every mentor with a closed class has earned CPD points.</p>
@endforelse
@if ($zeroCpdNote)<p class="muted" style="margin:14px 4px 0;font-size:12.5px">{{ $zeroCpdNote }}</p>@endif
</div>
</article>
</section>

<section aria-labelledby="lb-h">
@include('analytics.partials.sec', ['n' => 2, 'id' => 'lb-h', 'title' => 'Leaderboard & levels'])
<div class="row-flex">
<article class="card rise" style="flex:1.5 1 560px;min-width:0;padding:24px" x-data="{ by: 'cpd' }">
<div class="ch-h"><div><h3 class="disp">Top {{ count($cpdLeaders) }} mentors</h3><p class="muted" x-text="by === 'cpd' ? 'Ranked by total CPD points earned' : 'Ranked by total mentees supported'">Ranked by total CPD points earned</p></div>
<div class="seg-ctl" role="tablist" aria-label="Rank by"><button type="button" role="tab" class="seg-b" :class="by === 'cpd' && 'on'" :aria-selected="by === 'cpd'" @click="by = 'cpd'">CPD points</button><button type="button" role="tab" class="seg-b" :class="by === 'reach' && 'on'" :aria-selected="by === 'reach'" @click="by = 'reach'">Mentee reach</button></div></div>
@foreach (['cpd' => $cpdLeaders, 'reach' => $reachLeaders] as $key => $list)
<div style="display:flex;flex-direction:column;gap:8px" x-show="by === '{{ $key }}'" @if ($key === 'reach') x-cloak @endif>
@foreach ($list as $l)
<div class="brow fadein" style="grid-template-columns:30px minmax(0,210px) minmax(0,1fr) 40px"><span class="disp num" style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;background:{{ $l['medal'] }};color:{{ $l['medalInk'] }}">{{ $l['rank'] }}</span><span class="lb" style="font-weight:600">{{ $l['name'] }}</span><span class="track"><span class="barx" style="display:block;height:100%;width:{{ $l['w'] }};background:{{ $l['c'] }};border-radius:99px;animation-delay:{{ $l['delay'] }}"></span></span><span class="vl num">{{ $l['v'] }}</span></div>
@endforeach
</div>
@endforeach
</article>
<article class="card rise" style="flex:1 1 360px;min-width:0;padding:24px;display:flex;flex-direction:column;animation-delay:.08s">
<div class="ch-h"><div><h3 class="disp">CPD level distribution</h3><p class="muted">{{ array_sum(array_column($levelRows, 'v')) }} mentors by level</p></div></div>
<div style="display:flex;flex-direction:column;gap:14px;flex:1;justify-content:center">
@foreach ($levelRows as $l)
<div style="display:grid;grid-template-columns:44px minmax(0,1fr) 72px;gap:14px;align-items:center"><span class="lvl" style="background:{{ $l['bg'] }};color:{{ $l['ink'] }};height:44px;font-size:14px">{{ $l['code'] }}</span><div><div style="font-size:13px;margin-bottom:6px;font-weight:600">{{ $l['label'] }}</div><span class="track" style="height:12px"><span class="barx" style="display:block;height:100%;width:{{ $l['w'] }};background:{{ $l['c'] }};border-radius:99px"></span></span></div><div style="text-align:right"><div class="disp num" style="font-size:22px;font-weight:700;line-height:1">{{ $l['v'] }}</div><div class="muted num" style="font-size:12px">{{ $l['pct'] }}</div></div></div>
@endforeach
</div>
<p class="muted" style="margin:18px 0 0;font-size:12.5px;padding-top:14px;border-top:1px solid #EEF3F3">{{ $foundationShare }} of mentors are still at Foundation level.</p>
</article>
</div>
</section>

<section aria-labelledby="cl-h">
@include('analytics.partials.sec', ['n' => 3, 'id' => 'cl-h', 'title' => 'Class activity'])
<div class="row-flex">
<article class="card rise" style="flex:1.7 1 560px;min-width:0;padding:24px">
<div class="ch-h"><div><h3 class="disp">Classes started per month</h3><p class="muted">Last 12 months · {{ $classTotal }} classes</p></div>@if ($peak)<span class="chip" style="background:#F0EDFB;color:#4E3A9E">{{ $peak }}</span>@endif</div>
@include('analytics.partials.columns', ['cols' => $cols])
</article>
<article class="card rise" style="flex:1 1 340px;min-width:0;padding:24px;display:flex;flex-direction:column;animation-delay:.08s">
<div class="ch-h"><div><h3 class="disp">Class status</h3><p class="muted">{{ $classes }} classes</p></div></div>
@include('analytics.partials.donut', ['segs' => $classSegs, 'size' => 180, 'centre' => $classSegs[0]['v'], 'centreSub' => 'active', 'aria' => 'Class status'])
</article>
</div>
</section>

<section aria-labelledby="wh-h">
@include('analytics.partials.sec', ['n' => 4, 'id' => 'wh-h', 'title' => 'Where mentors work'])
<div class="grid-auto" style="--min:380px;gap:20px">
<article class="card rise" style="padding:24px"><div class="ch-h"><div><h3 class="disp">By facility</h3><p class="muted">Unique mentors · top {{ count($byFac) }}</p></div></div>@include('analytics.partials.bars', ['bars' => $byFac, 'lw' => 170, 'vw' => 40])</article>
<article class="card rise" style="padding:24px;animation-delay:.06s"><div class="ch-h"><div><h3 class="disp">By department</h3><p class="muted">Unique mentors · top {{ count($byDept) }}</p></div></div>@include('analytics.partials.bars', ['bars' => $byDept, 'lw' => 170, 'vw' => 40])</article>
<article class="card rise" style="padding:24px;animation-delay:.12s"><div class="ch-h"><div><h3 class="disp">By module</h3><p class="muted">Unique mentors per module in live classes · top {{ count($byMod) }}</p></div></div>@include('analytics.partials.bars', ['bars' => $byMod, 'lw' => 230, 'vw' => 40])</article>
</div>
</section>

<section aria-labelledby="mx-h" x-data="{ page: 0, size: 10, rows: @js($rows), get pages() { return Math.max(1, Math.ceil(this.rows.length / this.size)) }, get shown() { return this.rows.slice(this.page * this.size, this.page * this.size + this.size) } }">
@include('analytics.partials.sec', ['n' => 5, 'id' => 'mx-h', 'title' => 'Mentor performance matrix'])
<article class="card rise" style="padding:24px">
<div class="ch-h"><div><h3 class="disp">Active classes — mentor activity</h3><p class="muted">{{ count($rows) }} {{ count($rows) === 1 ? 'class' : 'classes' }} · sorted by mentor CPD points</p></div></div>
<div style="overflow-x:auto;border:1px solid #E1E9EA;border-radius:12px">
<table class="tbl">
<thead><tr><th>Mentor</th><th>Cadre · Dept</th><th>Facility · Class</th><th style="min-width:150px">Modules done</th><th style="text-align:right">Mentees</th><th style="text-align:right">CPD</th><th>Level</th></tr></thead>
<tbody>
{{-- First page is rendered on the server so the table shows without JavaScript; Alpine takes over paging. --}}
@foreach (array_slice($rows, 0, 10) as $r)
<tr x-show="false"><td><div style="font-weight:600;white-space:nowrap">{{ $r['n'] }}</div><div class="muted" style="font-size:12px">{{ $r['county'] }}</div></td><td><div>{{ $r['cadre'] }}</div><div class="muted" style="font-size:12px">{{ $r['dept'] }}</div></td><td style="min-width:220px"><div>{{ $r['fac'] }}</div><div class="muted" style="font-size:12px">{{ $r['cls'] }}</div></td><td><div style="display:flex;align-items:center;gap:10px"><span class="track" style="flex:1;height:8px;min-width:70px"><span style="display:block;height:100%;width:{{ $r['pw'] }};background:{{ $r['pc'] }};border-radius:99px"></span></span><span class="num" style="font-weight:600;font-size:12.5px;white-space:nowrap">{{ $r['done'] }}</span></div></td><td class="num" style="text-align:right;font-weight:600">{{ $r['m'] }}</td><td class="num" style="text-align:right;font-weight:700">{{ $r['cpd'] }}</td><td><span class="lvl" style="background:{{ $r['lbg'] }};color:{{ $r['link'] }}" title="{{ $r['lname'] }}">{{ $r['l'] }}</span></td></tr>
@endforeach
<template x-for="r in shown" :key="r.n + r.cls + r.fac + r.done">
<tr class="fadein"><td><div style="font-weight:600;white-space:nowrap" x-text="r.n"></div><div class="muted" style="font-size:12px" x-text="r.county"></div></td><td><div x-text="r.cadre"></div><div class="muted" style="font-size:12px" x-text="r.dept"></div></td><td style="min-width:220px"><div x-text="r.fac"></div><div class="muted" style="font-size:12px" x-text="r.cls"></div></td><td><div style="display:flex;align-items:center;gap:10px"><span class="track" style="flex:1;height:8px;min-width:70px"><span style="display:block;height:100%;border-radius:99px" :style="`width:${r.pw};background:${r.pc}`"></span></span><span class="num" style="font-weight:600;font-size:12.5px;white-space:nowrap" x-text="r.done"></span></div></td><td class="num" style="text-align:right;font-weight:600" x-text="r.m"></td><td class="num" style="text-align:right;font-weight:700" x-text="r.cpd"></td><td><span class="lvl" :style="`background:${r.lbg};color:${r.link}`" :title="r.lname" x-text="r.l"></span></td></tr>
</template>
@if (! $rows)
<tr><td colspan="7" style="padding:40px;text-align:center"><div style="font-weight:600">No classes match these filters</div><div class="muted">Try another county, cadre or level.</div></td></tr>
@endif
</tbody>
</table>
</div>
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:16px">
<span class="muted num" style="font-size:13px" x-text="rows.length ? `Showing ${page * size + 1}–${Math.min(rows.length, page * size + size)} of ${rows.length}` : 'No results'">Showing 1–{{ min(10, count($rows)) }} of {{ count($rows) }}</span>
<div style="display:flex;gap:8px"><button class="btn btn-ghost" type="button" @click="page = Math.max(0, page - 1)" :disabled="page === 0" aria-label="Previous page">@include('analytics.partials.icon', ['name' => 'chevron-left', 'size' => 16, 'sw' => 2])Prev</button><button class="btn btn-ghost" type="button" @click="page = Math.min(pages - 1, page + 1)" :disabled="page >= pages - 1" aria-label="Next page">Next @include('analytics.partials.icon', ['name' => 'chevron-right', 'size' => 16, 'sw' => 2])</button></div>
</div>
</article>
</section>
@endsection

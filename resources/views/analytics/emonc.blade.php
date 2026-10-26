@extends('analytics.layout')

@section('content')
@include('analytics.partials.filters', [
    'fields' => ['programme', 'county', 'year'],
    'scopeChips' => [[$f->county ? 'County: '.$f->county : 'National · all counties', '#E3F5F7', '#00505A']],
])

@if ($empty)
<section class="card rise" aria-labelledby="setup-h" style="padding:28px;display:flex;gap:28px;align-items:center;flex-wrap:wrap;background:#FFF7F0;border-color:#F6DCC4;animation-delay:.12s">
<span style="width:64px;height:64px;border-radius:20px;background:#FFFFFF;color:#C2560C;display:flex;align-items:center;justify-content:center;flex:none;box-shadow:0 8px 20px -12px rgba(194,86,12,.5)">@include('analytics.partials.icon', ['name' => 'tag', 'size' => 30, 'sw' => 1.8])</span>
<div style="flex:1 1 420px;min-width:0">
<h2 id="setup-h" class="disp" style="margin:0;font-size:22px;font-weight:600">No EmONC mentorship data yet</h2>
<p style="margin:8px 0 0;color:#5B4636;text-wrap:pretty">This dashboard only counts programmes whose name includes the tag <strong style="color:#8A3C06">Maternal EmONC</strong>. No programme carries that tag yet, so every figure below reads zero. Tag a programme in Admin and it will appear here on the next refresh.</p>
</div>
<a class="btn btn-pri" href="{{ $adminUrl ?? '/admin' }}" style="background:#C2560C">Tag a programme @include('analytics.partials.icon', ['name' => 'arrow-right', 'size' => 16, 'sw' => 2])</a>
</section>
@endif

@include('analytics.partials.kpis', ['kpis' => $kpis, 'min' => 260])

<section aria-labelledby="pipe-h">
@include('analytics.partials.sec', ['n' => 1, 'id' => 'pipe-h', 'title' => 'Certification pipeline'])
<article class="card rise" style="padding:24px">
<div class="ch-h"><div><h3 class="disp">From module completion to certificate</h3><p class="muted">Each mentee moves left to right; counts show who is waiting at each stage</p></div>
@if ($queueClear)<span class="chip" style="background:#E7F5EF;color:#0E6A4C">@include('analytics.partials.icon', ['name' => 'check-circle', 'size' => 13, 'sw' => 2.2])Queue clear</span>@endif</div>
<ol style="display:flex;gap:14px;flex-wrap:wrap;align-items:stretch;margin:0;padding:0;list-style:none">
@foreach ($steps as $i => [$label, $note, $colour, $count])
<li class="step rise" style="animation-delay:{{ number_format(0.15 + $i * 0.08, 2) }}s">
<div style="display:flex;align-items:center;gap:10px"><span class="disp num" style="width:30px;height:30px;border-radius:50%;background:{{ $colour }};color:#FFFFFF;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex:none">{{ $i + 1 }}</span><span style="font-weight:600;font-size:13.5px">{{ $label }}</span></div>
<div class="disp num" style="font-size:34px;font-weight:700;margin-top:14px">{{ number_format($count) }}</div>
<div class="muted" style="font-size:12.5px">{{ $note }}</div>
</li>
@endforeach
</ol>
</article>
</section>

<section aria-labelledby="act-h">
@include('analytics.partials.sec', ['n' => 2, 'id' => 'act-h', 'title' => 'Activity & actions'])
<div class="row-flex">
<article class="card rise" style="flex:1.7 1 560px;min-width:0;padding:24px">
<div class="ch-h"><div><h3 class="disp">EmONC classes started per month</h3><p class="muted">Last 12 months</p></div></div>
@include('analytics.partials.columns', ['cols' => $cols, 'emptyTitle' => $trendEmpty ? 'No EmONC classes started' : null, 'emptyText' => 'The chart fills in with the first class'])
</article>
<article class="card rise" style="flex:1 1 340px;min-width:0;padding:24px;animation-delay:.08s">
<div class="ch-h"><div><h3 class="disp">Action queue</h3><p class="muted">Items requiring attention</p></div></div>
<div style="display:flex;flex-direction:column;gap:10px">
@foreach ($queue as [$label, $icon, $tone, $count])
@php([$bg, $ink] = \App\Services\Analytics\DashboardPresenter::PALETTE[$tone])
<a href="{{ $adminUrl ?? '/admin' }}" style="display:flex;align-items:center;gap:14px;min-height:64px;padding:10px 14px;border-radius:14px;border:1px solid #E6EDEE;text-decoration:none;color:#0F2A2E;background:#FAFCFC"><span class="ico" style="background:{{ $bg }};color:{{ $ink }}">@include('analytics.partials.icon', ['name' => $icon, 'size' => 19])</span><span style="flex:1;min-width:0"><span style="display:block;font-weight:600">{{ $label }}</span><span class="muted" style="font-size:12.5px">{{ $count ? 'Waiting for action' : 'Nothing waiting' }}</span></span><span class="disp num" style="font-size:22px;font-weight:700">{{ number_format($count) }}</span>@include('analytics.partials.icon', ['name' => 'chevron-right', 'size' => 16, 'sw' => 2])</a>
@endforeach
</div>
</article>
</div>
</section>

@if ($empty)
<section aria-labelledby="brk-h">
@include('analytics.partials.sec', ['n' => 3, 'id' => 'brk-h', 'title' => 'Breakdowns', 'after' => 'Fill in automatically once EmONC classes have mentees'])
<div class="grid-auto" style="--min:290px">
@foreach ($breakdowns as $i => [$title, $sub])
<article class="card rise" style="padding:20px;animation-delay:{{ number_format(0.1 + $i * 0.05, 2) }}s">
<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start"><div><h3 class="disp" style="margin:0;font-size:16px;font-weight:600">{{ $title }}</h3><p class="muted" style="margin:3px 0 0;font-size:12.5px">{{ $sub }}</p></div><span class="chip" style="background:#F0F4F4;color:#556B6E">No data</span></div>
<div style="display:flex;flex-direction:column;gap:10px;margin-top:18px" aria-hidden="true">
@foreach ([78, 54, 36] as $w)<div style="display:flex;gap:10px;align-items:center"><span class="skel" style="width:72px;height:10px;border-radius:99px"></span><span class="skel" style="flex:0 1 {{ $w }}%;height:10px;border-radius:99px"></span></div>@endforeach
</div>
</article>
@endforeach
</div>
</section>
@endif

<section aria-labelledby="mx-h">
@include('analytics.partials.sec', ['n' => $empty ? 4 : 3, 'id' => 'mx-h', 'title' => 'Per-mentee completion matrix'])
<article class="card rise" style="padding:24px">
<div class="ch-h"><div><h3 class="disp">EmONC mentee status</h3><p class="muted">{{ count($matrix) }} records</p></div></div>
<div style="overflow-x:auto;border:1px solid #E1E9EA;border-radius:12px">
<table class="tbl">
<thead><tr><th>Mentee</th><th>Facility</th><th>Class</th><th>Modules done</th><th>Video review</th><th>Mentor approval</th><th>DRMH certification</th><th>Certificate</th></tr></thead>
<tbody>
@forelse ($matrix as $r)
<tr><td style="font-weight:600">{{ \App\Services\Analytics\DashboardPresenter::personName($r['mentee']) }}</td><td>{{ $r['facility'] }}</td><td>{{ $r['class'] }}</td><td class="num">{{ $r['modules_done'] }} / {{ $r['modules_total'] }}</td><td>{{ $r['video_review'] }}</td><td>{{ $r['mentor_approval'] }}</td><td>{{ $r['drmh'] }}</td><td>{{ $r['certificate'] }}</td></tr>
@empty
<tr><td colspan="8" style="padding:44px 16px;text-align:center"><div style="font-weight:600;font-size:15px">No EmONC mentees yet</div><div class="muted" style="margin-top:4px">Mentees appear here once a programme tagged “Maternal EmONC” has enrolments.</div></td></tr>
@endforelse
</tbody>
</table>
</div>
</article>
</section>
@endsection

@extends('analytics.layout')

@section('content')
@include('analytics.partials.filters', [
    'fields' => ['programme', 'year', 'county', 'subcounty', 'facility', 'search'],
    'searchPlaceholder' => 'Programme or facility',
    'scopeChips' => [[$scope, '#E3F5F7', '#00505A'], [($f->programme ?? 'All programmes').' · '.($f->year ?? 'All years'), '#F0F4F4', '#3D5558']],
])

@include('analytics.partials.kpis', ['kpis' => $kpis])

@include('analytics.partials.insights', ['insights' => $insights, 'n' => 1])

<section aria-labelledby="act-h">
@include('analytics.partials.sec', ['n' => 2, 'id' => 'act-h', 'title' => 'Activity & status'])
<div class="row-flex">
<article class="card rise" style="flex:1.7 1 560px;min-width:0;padding:24px">
<div class="ch-h"><div><h3 class="disp">Mentorships started per month</h3><p class="muted">Last 12 months · {{ $trendTotal }} in total</p></div><span class="chip" style="background:#E5F6F8;color:#00505A">{{ $trendRange }}</span></div>
<div style="position:relative;height:250px;margin:14px 0 0 34px">
@foreach ($trend['ticks'] as $t)
<div style="position:absolute;left:0;right:0;top:{{ $t['top'] }};border-top:1px dashed #E3EBEC"><span class="num muted" style="position:absolute;left:-34px;top:-9px;font-size:12px;width:26px;text-align:right">{{ $t['v'] }}</span></div>
@endforeach
<div class="reveal" style="position:absolute;inset:0"><svg viewBox="0 0 1000 300" preserveAspectRatio="none" aria-hidden="true" style="width:100%;height:100%;overflow:visible;display:block"><defs><linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0097A7" stop-opacity="0.32"/><stop offset="1" stop-color="#0097A7" stop-opacity="0"/></linearGradient></defs><path d="{{ $trend['area'] }}" fill="url(#trendFill)"/><path d="{{ $trend['line'] }}" fill="none" stroke="#0097A7" stroke-width="3" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round"/></svg></div>
@foreach ($trend['pts'] as $p)
<div class="pt" style="position:absolute;left:{{ $p['x'] }};top:{{ $p['y'] }};width:28px;height:28px;transform:translate(-50%,-50%)"><span class="pop" style="position:absolute;left:50%;top:50%;width:11px;height:11px;border-radius:50%;background:#FFFFFF;border:3px solid #0097A7;box-sizing:border-box;transform:translate(-50%,-50%);animation-delay:{{ $p['delay'] }}"></span><span class="tip num" style="position:absolute;left:50%;bottom:100%;white-space:nowrap;background:#0F2A2E;color:#FFFFFF;font-size:12px;font-weight:600;padding:5px 9px;border-radius:8px;pointer-events:none">{{ $p['label'] }} · {{ $p['v'] }}</span></div>
@endforeach
@if ($trend['peak'])
<div class="disp num" style="position:absolute;left:{{ $trend['peak']['x'] }};top:{{ $trend['peak']['y'] }};transform:translate(-50%,-150%);font-weight:700;font-size:15px;color:#00505A">{{ $trend['peak']['v'] }}</div>
@endif
</div>
<div style="position:relative;height:22px;margin:10px 0 0 34px" aria-hidden="true">
@foreach ($trend['pts'] as $p)<span class="muted" style="position:absolute;left:{{ $p['x'] }};transform:translateX(-50%);font-size:12px;white-space:nowrap">{{ $p['label'] }}</span>@endforeach
</div>
<table class="sr-only"><caption>Mentorships started per month</caption><tbody>@foreach ($trend['pts'] as $p)<tr><th scope="row">{{ $p['label'] }}</th><td>{{ $p['v'] }}</td></tr>@endforeach</tbody></table>
</article>
<article class="card rise" style="flex:1 1 340px;min-width:0;padding:24px;display:flex;flex-direction:column;animation-delay:.08s">
<div class="ch-h"><div><h3 class="disp">Mentorship status</h3><p class="muted">{{ $mentorships }} mentorships</p></div></div>
@include('analytics.partials.donut', ['segs' => $status, 'centre' => $statusCentre, 'centreSub' => 'completed', 'aria' => 'Mentorship status'])
</article>
</div>
</section>

<section aria-labelledby="who-h">
@include('analytics.partials.sec', ['n' => 3, 'id' => 'who-h', 'title' => 'Who is being mentored'])
<div class="row-flex">
<article class="card rise" style="flex:1 1 420px;min-width:0;padding:24px">
<div class="ch-h"><div><h3 class="disp">By department</h3><p class="muted">Top {{ count($depts) }} departments by participants</p></div><span class="chip" style="background:#E5F6F8;color:#00505A">Participants</span></div>
@include('analytics.partials.bars', ['bars' => $depts, 'lw' => 150])
</article>
<article class="card rise" style="flex:1 1 420px;min-width:0;padding:24px;animation-delay:.08s">
<div class="ch-h"><div><h3 class="disp">By cadre</h3><p class="muted">Top {{ count($cadres) }} professional roles · mentees</p></div><span class="chip" style="background:#EAF1FA;color:#174F82">Mentees</span></div>
@include('analytics.partials.bars', ['bars' => $cadres, 'lw' => 190])
@foreach ($cadreClash as $group)
<p class="muted" style="margin:16px 0 0;font-size:12.5px">“{{ implode('” and “', $group) }}” are recorded as separate cadres in the source data.</p>
@endforeach
</article>
</div>
</section>

<section aria-labelledby="geo-h">
@include('analytics.partials.sec', ['n' => 4, 'id' => 'geo-h', 'title' => 'Coverage & geographic reach'])
@include('analytics.partials.geo', ['mapTitle' => 'Unique mentees by county', 'peopleLabel' => 'Unique mentees', 'programmesLabel' => 'Mentorships', 'focusBg' => '#00363B', 'focusAccent' => '#7FDCE6', 'focusSoft' => '#BFE9EE', 'rankSub' => $countiesActive.' counties with active mentees'])
</section>

<section aria-labelledby="lvl-h">
@include('analytics.partials.sec', ['n' => 5, 'id' => 'lvl-h', 'title' => 'Reach by facility level'])
@include('analytics.partials.levels', ['heading' => 'Share of facilities with a mentorship', 'sub' => 'Facilities with an active mentorship ÷ registered facilities of that type', 'chip' => number_format($facilities).' of '.number_format($registered).' overall', 'chipBg' => '#F0EDFB', 'chipInk' => '#4E3A9E'])
</section>

<section aria-labelledby="out-h">
@include('analytics.partials.sec', ['n' => 6, 'id' => 'out-h', 'title' => 'Mentee & programme outcomes'])
<div class="row-flex">
<article class="card rise" style="flex:1.3 1 480px;min-width:0;padding:24px">
<div class="ch-h"><div><h3 class="disp">Module completion per mentee</h3><p class="muted">{{ number_format($tracked) }} mentees with module records</p></div><span class="chip" style="background:#E7F5EF;color:#0E6A4C">{{ number_format($fullyComplete) }} fully complete</span></div>
<div style="display:flex;align-items:flex-end;gap:16px;height:220px;padding-top:28px;border-bottom:1px solid #E1E9EA">
@foreach ($completion as $c)
<div style="flex:1;height:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:8px;min-width:0"><span class="disp num" style="font-weight:700;font-size:16px">{{ $c['v'] }}</span><div class="bary" style="width:100%;max-width:72px;height:{{ $c['h'] }};background:{{ $c['color'] }};border-radius:10px 10px 4px 4px;animation-delay:{{ $c['delay'] }}"></div></div>
@endforeach
</div>
<div style="display:flex;gap:16px;margin-top:10px">
@foreach ($completion as $c)<div style="flex:1;text-align:center;min-width:0"><div style="font-size:12.5px;font-weight:600">{{ $c['label'] }}</div><div class="muted num" style="font-size:12px">{{ $c['pct'] }}</div></div>@endforeach
</div>
</article>
<article class="card rise" style="flex:1 1 380px;min-width:0;padding:24px;animation-delay:.08s">
<div class="ch-h"><div><h3 class="disp">Top mentorship programmes</h3><p class="muted">By number of mentees</p></div></div>
<div style="display:flex;flex-direction:column;gap:14px">
@foreach ($programmes as $p)
<div style="display:flex;flex-direction:column;gap:6px"><div style="display:flex;justify-content:space-between;gap:12px"><span style="font-size:13.5px;font-weight:600;min-width:0">{{ $p['label'] }}</span><span class="num" style="font-weight:700">{{ $p['v'] }}</span></div><span class="track" style="height:8px"><span class="barx" style="display:block;height:100%;width:{{ $p['w'] }};background:{{ $p['color'] }};border-radius:99px;animation-delay:{{ $p['delay'] }}"></span></span></div>
@endforeach
</div>
</article>
</div>
<div class="row-flex" style="margin-top:20px">
<article class="card rise" style="flex:1 1 420px;min-width:0;padding:24px">
<div class="ch-h"><div><h3 class="disp">Mentee enrolment status</h3><p class="muted">{{ number_format($enrolTotal) }} enrolment records</p></div></div>
@include('analytics.partials.donut', ['segs' => $enrolSegs, 'size' => 150, 'stroke' => 5.6, 'centre' => number_format($enrolTotal), 'centreSub' => 'records', 'aria' => 'Enrolment status'])
</article>
<article class="card rise" style="flex:1 1 420px;min-width:0;padding:24px;animation-delay:.08s">
<div class="ch-h"><div><h3 class="disp">Class lifecycle</h3><p class="muted">{{ number_format($lifeTotal) }} classes</p></div></div>
<div style="display:flex;height:18px;border-radius:99px;overflow:hidden;gap:3px" aria-hidden="true">
@foreach ($lifecycle as $s)<div class="barx" style="height:100%;width:{{ $s['w'] }};background:{{ $s['color'] }};animation-delay:{{ $s['delay'] }}"></div>@endforeach
</div>
<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:20px">
@foreach ($lifecycle as $s)
<div style="border-radius:14px;background:{{ $s['bg'] }};padding:14px"><div style="display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:600;color:#2E4A4E"><span style="width:10px;height:10px;border-radius:3px;background:{{ $s['color'] }}"></span>{{ $s['label'] }}</div><div class="disp num" style="font-size:28px;font-weight:700;margin-top:6px">{{ $s['v'] }}</div><div class="muted num" style="font-size:12px">{{ $s['pct'] }}</div></div>
@endforeach
</div>
</article>
</div>
</section>
@endsection

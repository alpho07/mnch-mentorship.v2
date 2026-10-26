@extends('analytics.layout')

@section('content')
@include('analytics.partials.filters', [
    'fields' => ['programme', 'year', 'county', 'subcounty', 'facility', 'search'],
    'searchPlaceholder' => 'Programme or facility',
    'scopeChips' => [[$scope, '#E3F5F7', '#00505A'], [$scopeProgramme, '#EAF1FA', '#174F82']],
])

@include('analytics.partials.kpis', ['kpis' => $kpis])

<section aria-labelledby="pg-h">
@include('analytics.partials.sec', ['n' => 1, 'id' => 'pg-h', 'title' => 'Training programmes', 'after' => 'Select a programme to filter the page'])
<div class="grid-auto" style="--min:280px">
@foreach ($cards as $p)
<a @class(['tcard', 'rise', 'on' => $p['on']]) href="{{ $p['href'] }}" @if ($p['on']) aria-current="true" @endif style="animation-delay:{{ $p['delay'] }}">
<span style="position:absolute;left:0;right:0;top:0;height:6px;background:{{ $p['c'] }}"></span>
<span style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:4px"><span class="chip" style="background:{{ $p['bg'] }};color:{{ $p['ink'] }}">{{ $p['year'] }}</span><span class="chip" style="background:#E7F5EF;color:#0E6A4C">{{ $p['status'] }}</span></span>
<span class="disp" style="font-size:19px;font-weight:600;line-height:1.2">{{ $p['name'] }}</span>
<span style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
<span style="display:flex;flex-direction:column"><span class="disp num" style="font-size:28px;font-weight:700">{{ $p['part'] }}</span><span class="muted" style="font-size:12px">Participants</span></span>
<span style="display:flex;flex-direction:column"><span class="disp num" style="font-size:28px;font-weight:700">{{ $p['fac'] }}</span><span class="muted" style="font-size:12px">Facilities</span></span>
</span>
<span style="display:flex;flex-direction:column;gap:6px"><span style="display:flex;justify-content:space-between;font-size:12px;color:#3D5558"><span>Share of all enrolments</span><span class="num" style="font-weight:700">{{ $p['share'] }}</span></span><span class="track" style="height:8px"><span class="barx" style="display:block;height:100%;width:{{ $p['share'] }};background:{{ $p['c'] }};border-radius:99px;animation-delay:{{ $p['delay'] }}"></span></span></span>
</a>
@endforeach
</div>
</section>

@include('analytics.partials.insights', ['insights' => $insights, 'n' => 2])

<section aria-labelledby="geo-h">
@include('analytics.partials.sec', ['n' => 3, 'id' => 'geo-h', 'title' => 'Coverage & geographic reach'])
@include('analytics.partials.geo', ['mapTitle' => 'Participants by county', 'peopleLabel' => 'Participants', 'programmesLabel' => 'Programmes', 'focusBg' => '#0F3B63', 'focusAccent' => '#9CC7F2', 'focusSoft' => '#C9DDF3', 'rankSub' => ($rankCount > $rankLimit ? 'Top '.$rankLimit.' of '.$rankCount : $rankCount).' counties by participants'])
</section>

<section aria-labelledby="lvl-h">
@include('analytics.partials.sec', ['n' => 4, 'id' => 'lvl-h', 'title' => 'Reach by facility level'])
@include('analytics.partials.levels', ['heading' => 'Share of facilities with a trained worker', 'sub' => 'Facilities with at least one participant ÷ registered facilities of that type', 'chip' => number_format($facilitiesReached).' of '.number_format($registered).' overall', 'chipBg' => '#EAF1FA', 'chipInk' => '#174F82'])
</section>

<section aria-labelledby="who-h">
@include('analytics.partials.sec', ['n' => 5, 'id' => 'who-h', 'title' => 'Who was trained'])
<div class="row-flex">
<article class="card rise" style="flex:1 1 420px;min-width:0;padding:24px">
<div class="ch-h"><div><h3 class="disp">By department</h3><p class="muted">Top {{ count($depts) }} departments by participants</p></div></div>
@include('analytics.partials.bars', ['bars' => $depts, 'lw' => 190, 'vw' => 52])
</article>
<article class="card rise" style="flex:1 1 420px;min-width:0;padding:24px;animation-delay:.08s">
<div class="ch-h"><div><h3 class="disp">By cadre</h3><p class="muted">Top {{ count($cadres) }} cadres · {{ number_format($cadreSum) }} of {{ number_format($unique) }} participants</p></div></div>
@include('analytics.partials.bars', ['bars' => $cadres, 'lw' => 210, 'vw' => 52])
</article>
</div>
<article class="card rise" style="padding:24px;margin-top:20px">
<div class="ch-h"><div><h3 class="disp">Enrolments by programme year</h3><p class="muted">{{ $recent ? number_format($recent).' enrolments in the last 12 months' : 'No enrolments in the last 12 months — programme history shown instead' }}</p></div></div>
<div style="display:flex;align-items:flex-end;gap:24px;height:200px;padding-top:28px;border-bottom:1px solid #E1E9EA">
@foreach ($years as $y)
<div style="flex:1;height:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:8px;min-width:0"><span class="disp num" style="font-weight:700;font-size:16px">{{ $y['v'] }}</span><div class="bary" style="width:100%;max-width:96px;height:{{ $y['h'] }};background:{{ $y['c'] }};border-radius:10px 10px 4px 4px;animation-delay:{{ $y['delay'] }}"></div></div>
@endforeach
</div>
<div style="display:flex;gap:24px;margin-top:10px">@foreach ($years as $y)<div style="flex:1;text-align:center;min-width:0"><div class="disp" style="font-weight:700">{{ $y['year'] }}</div><div class="muted" style="font-size:12px">{{ $y['name'] }}</div></div>@endforeach</div>
</article>
</section>
@endsection

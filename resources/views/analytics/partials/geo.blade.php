{{--
  County map + focus panel + ranking (Newborn & Child, Trainings).
  Every county is a link that toggles ?county=…, so it works without JavaScript.
  Needs from the presenter: map, legend, focus, national, ranking, rankCount, rankLimit, hasCounty, resetCountyHref
  Params: $mapTitle, $peopleLabel, $programmesLabel, $focusBg, $focusAccent, $focusSoft, $rankSub
--}}
<div class="row-flex">
<article class="card rise" style="flex:1.5 1 600px;min-width:0;padding:24px">
<div class="ch-h"><div><h3 class="disp">{{ $mapTitle }}</h3><p class="muted">Select a county on the map, the list or the filter</p></div>
<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
@foreach ($legend as $l)
<span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#3D5558"><span style="width:12px;height:12px;border-radius:3px;background:{{ $l['c'] }};border:1px solid rgba(15,42,46,.08)"></span>{{ $l['t'] }}</span>
@endforeach
</div></div>
<div style="display:flex;flex-wrap:wrap;gap:28px;align-items:flex-start">
<div style="flex:1 1 300px;max-width:400px;min-width:0">
<svg class="kmap kmap-in" viewBox="0 0 400 507" role="img" aria-label="{{ $mapTitle }} — map of Kenya's 47 counties" style="width:100%;height:auto;display:block;overflow:visible">
@foreach ($map['counties'] as $c)
<a href="{{ $c['href'] }}" aria-label="{{ $c['label'] }}"><title>{{ $c['label'] }}</title><path d="{{ $c['d'] }}" fill="{{ $c['fill'] }}" stroke="#FFFFFF" stroke-width="0.9" stroke-linejoin="round"/></a>
@endforeach
@if ($map['selD'])<path d="{{ $map['selD'] }}" fill="none" stroke="#0F2A2E" stroke-width="2.4" stroke-linejoin="round" pointer-events="none"/>@endif
</svg>
</div>
<div style="flex:1 1 240px;min-width:0;display:flex;flex-direction:column;gap:16px">
@php($fp = $focus ?? $national)
<div style="border-radius:16px;background:{{ $focusBg }};color:#FFFFFF;padding:20px" aria-live="polite">
<div class="eyebrow" style="color:{{ $focusAccent }}">{{ $focus ? 'County focus' : 'National view' }}</div>
<div class="disp" style="font-size:28px;font-weight:700;margin-top:6px">{{ $focus['name'] ?? 'All counties' }}</div>
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px 12px;margin-top:18px">
<div><div class="disp num" style="font-size:26px;font-weight:700">{{ $fp['people'] }}</div><div style="font-size:12px;color:{{ $focusSoft }}">{{ $peopleLabel }}</div></div>
<div><div class="disp num" style="font-size:26px;font-weight:700">{{ $fp['programmes'] }}</div><div style="font-size:12px;color:{{ $focusSoft }}">{{ $programmesLabel }}</div></div>
<div><div class="disp num" style="font-size:26px;font-weight:700">{{ $fp['facilities'] }}</div><div style="font-size:12px;color:{{ $focusSoft }}">Facilities reached</div></div>
<div><div class="disp num" style="font-size:26px;font-weight:700">{{ $fp['coverage'] }}</div><div style="font-size:12px;color:{{ $focusSoft }}">Facility coverage</div></div>
</div>
<div style="margin-top:16px;font-size:13px;color:#E3F3F5;padding-top:14px;border-top:1px solid rgba(255,255,255,.15)">{{ $fp['note'] }}</div>
</div>
@if ($hasCounty)<a class="btn btn-ghost" href="{{ $resetCountyHref }}" style="align-self:flex-start">Back to national view</a>@endif
</div>
</div>
</article>
<article class="card rise" style="flex:1 1 340px;min-width:0;padding:24px;animation-delay:.08s" x-data="{ all: false }">
<div class="ch-h"><div><h3 class="disp">County ranking</h3><p class="muted">{{ $rankSub }}</p></div></div>
<div style="display:flex;flex-direction:column;gap:2px">
@foreach ($ranking as $r)
<a @class(['cbtn', 'on' => $r['on']]) href="{{ $r['href'] }}" @if ($r['extra']) x-show="all" x-cloak @endif @if ($r['on']) aria-current="true" @endif><span class="num muted" style="font-size:12px;font-weight:600">{{ $r['rank'] }}</span><span style="display:flex;flex-direction:column;gap:5px;min-width:0"><span style="font-weight:600;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $r['name'] }}</span><span class="track" style="height:6px"><span class="barx" style="display:block;height:100%;width:{{ $r['w'] }};background:{{ $r['color'] }};border-radius:99px;animation-delay:{{ $r['delay'] }}"></span></span></span><span class="num" style="text-align:right;font-weight:700">{{ $r['v'] }}</span></a>
@endforeach
</div>
@if ($rankCount > $rankLimit)
<div style="display:flex;justify-content:center;margin-top:12px"><button class="btn btn-ghost" type="button" @click="all = !all" style="min-height:40px" x-text="all ? 'Show top {{ $rankLimit }}' : 'Show all {{ $rankCount }} counties'">Show all {{ $rankCount }} counties</button></div>
@endif
</article>
</div>

{{-- $segs: Viz::donut(); $size px; $stroke; $centre big text; $centreSub small text --}}
@php($sz = $size ?? 188)
<div style="display:flex;align-items:center;justify-content:center;flex:1;gap:28px;flex-wrap:wrap">
<div style="position:relative;width:{{ $sz }}px;height:{{ $sz }}px;flex:none">
<svg viewBox="0 0 42 42" aria-hidden="true" style="position:absolute;inset:0;width:100%;height:100%;transform:rotate(-90deg)"><circle cx="21" cy="21" r="15.9155" fill="none" stroke="#EDF3F4" stroke-width="{{ $stroke ?? 5.2 }}"/>@foreach ($segs as $s)<circle class="seg" cx="21" cy="21" r="15.9155" fill="none" stroke="{{ $s['color'] }}" stroke-width="{{ $stroke ?? 5.2 }}" stroke-dasharray="{{ $s['dash'] }}" stroke-dashoffset="{{ $s['off'] }}" style="animation-delay:{{ $s['delay'] }}"/>@endforeach</svg>
<div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center"><span class="disp num" style="font-size:{{ $sz < 170 ? 26 : 32 }}px;font-weight:700;line-height:1">{{ $centre }}</span><span class="muted" style="font-size:12px;margin-top:4px">{{ $centreSub }}</span></div>
</div>
<ul style="display:flex;flex-direction:column;gap:12px;min-width:160px;margin:0;padding:0;list-style:none" aria-label="{{ $aria ?? 'Breakdown' }}">
@foreach ($segs as $s)
<li style="display:flex;align-items:center;gap:10px"><span style="width:12px;height:12px;border-radius:4px;background:{{ $s['color'] }};flex:none"></span><span style="flex:1">{{ $s['label'] }}</span><span class="num" style="font-weight:700">{{ $s['v'] }}</span><span class="num muted" style="font-size:12px;width:36px;text-align:right">{{ $s['pct'] }}</span></li>
@endforeach
</ul>
</div>

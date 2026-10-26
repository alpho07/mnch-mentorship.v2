{{-- Reach by facility level. $levels from presenter; $heading, $sub, $chip, $chipBg, $chipInk --}}
<article class="card rise" style="padding:24px">
<div class="ch-h"><div><h3 class="disp">{{ $heading }}</h3><p class="muted">{{ $sub }}</p></div><span class="chip" style="background:{{ $chipBg }};color:{{ $chipInk }}">{{ $chip }}</span></div>
<div class="grid-auto" style="--min:260px;gap:14px">
@foreach ($levels as $l)
<div style="border:1px solid #E6EDEE;border-radius:14px;padding:16px;display:flex;flex-direction:column;gap:10px;background:{{ $l['bg'] }}">
<div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px"><span style="font-weight:600;font-size:13.5px">{{ $l['label'] }}</span><span class="disp num" style="font-size:22px;font-weight:700;color:{{ $l['ink'] }}">{{ $l['pct'] }}</span></div>
<span class="track" style="height:8px"><span class="barx" style="display:block;height:100%;width:{{ $l['w'] }};background:{{ $l['color'] }};border-radius:99px;animation-delay:{{ $l['delay'] }}"></span></span>
<span class="muted num" style="font-size:12.5px">{{ $l['of'] }}</span>
</div>
@endforeach
</div>
</article>

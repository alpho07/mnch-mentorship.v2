{{-- $bars: Viz::bars(); $lw label column px; $vw value column px --}}
<div style="display:flex;flex-direction:column;gap:6px;--lw:{{ $lw ?? 150 }}px;--vw:{{ $vw ?? 44 }}px">
@forelse ($bars as $b)
<div class="brow"><span class="lb" title="{{ $b['label'] }}">{{ $b['label'] }}</span><span class="track"><span class="barx" style="display:block;height:100%;width:{{ $b['w'] }};background:{{ $b['color'] }};border-radius:99px;animation-delay:{{ $b['delay'] }}"></span></span><span class="vl num">{{ $b['v'] }}</span></div>
@empty
<p class="muted" style="margin:0">No records for these filters.</p>
@endforelse
</div>

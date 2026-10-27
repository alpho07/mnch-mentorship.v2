{{-- Subtopic rows with bars. Rows with resources link to /resources?topic=…&sub=…; the filter box and "Hide empty" toggle act on them. --}}
<div style="display:flex;flex-direction:column;gap:6px">
@foreach ($c['subs'] as $s)
@if ($s['empty'])
<div class="srow" style="color:{{ $s['ink'] }}" x-show="showSub(@js($c['search']), @js($s['name']), true)"><span>{{ $s['name'] }}</span><span class="track"><span style="display:block;height:100%;width:0"></span></span><span class="num" style="text-align:right;font-weight:700" aria-label="no resources">{{ $s['v'] }}</span></div>
@else
<a class="srow" href="{{ $s['href'] }}" style="color:{{ $s['ink'] }}" x-show="showSub(@js($c['search']), @js($s['name']), false)"><span>{{ $s['name'] }}</span><span class="track"><span class="barx" style="display:block;height:100%;width:{{ $s['w'] }};background:{{ $c['c'] }};border-radius:99px"></span></span><span class="num" style="text-align:right;font-weight:700">{{ $s['v'] }}</span></a>
@endif
@endforeach
</div>

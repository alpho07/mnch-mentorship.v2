<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(250px,100%),1fr));gap:18px">
@foreach ($cards as $r)
<a class="rcard fadein" href="{{ $r['url'] }}" style="animation-delay:{{ $r['delay'] }}">
<span style="position:relative;display:block;height:150px;background:{{ $r['cover'] }};overflow:hidden">
@if ($r['img'])
<img src="{{ $r['img'] }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;object-position:{{ $r['imgPos'] }};display:block">
@else
@include('site.partials.icon', ['name' => $r['icon'], 'stroke' => '#FFFFFF', 'sw' => 1.2, 'size' => 150, 'style' => 'position:absolute;right:-18px;bottom:-26px;opacity:.16'])
<span style="position:absolute;left:16px;top:16px;right:16px;display:flex;justify-content:space-between;align-items:center"><span style="font-size:10.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.9)">{{ $r['format'] }}</span><span style="width:30px;height:30px;border-radius:9px;background:rgba(255,255,255,.18);display:inline-flex;align-items:center;justify-content:center;color:#FFF">@include('site.partials.icon', ['name' => $r['icon'], 'size' => 16])</span></span>
<span class="disp" aria-hidden="true" style="position:absolute;left:16px;right:40px;bottom:16px;color:#FFF;font-size:16px;font-weight:700;line-height:1.2;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden">{{ $r['title'] }}</span>
@endif
</span>
<span style="display:flex;flex-direction:column;gap:8px;padding:16px;flex:1">
<span style="display:flex;gap:6px;flex-wrap:wrap"><span class="chip chip-sm" style="background:{{ $r['catBg'] }};color:{{ $r['catInk'] }}">{{ $r['sub'] }}</span>@if ($r['advanced'])<span class="chip chip-sm" style="background:#FDF3E3;color:#8A5000">Advanced</span>@endif</span>
<span class="disp" style="font-size:16.5px;font-weight:700;line-height:1.28">{{ $r['title'] }}</span>
<span class="muted num" style="font-size:12.5px;display:flex;gap:12px;flex-wrap:wrap;margin-top:auto;padding-top:6px">
<span style="display:inline-flex;align-items:center;gap:5px" title="Views">@include('site.partials.icon', ['name' => 'eye', 'size' => 14])<span class="sr">Views</span>{{ $r['views'] }}</span>
@if ($r['hasDownloads'])<span style="display:inline-flex;align-items:center;gap:5px" title="Downloads">@include('site.partials.icon', ['name' => 'download', 'size' => 14])<span class="sr">Downloads</span>{{ $r['downloads'] }}</span>@endif
<span>Added {{ $r['age'] }}</span>
</span>
</span>
</a>
@endforeach
</div>

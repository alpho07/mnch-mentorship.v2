{{-- Monthly columns. $cols: Viz::columns() result (cols, ticks); optional $emptyTitle/$emptyText overlay --}}
<div style="position:relative;height:230px;margin-left:30px">
@foreach ($cols['ticks'] as $t)
<div style="position:absolute;left:0;right:0;top:{{ $t['top'] }};border-top:1px dashed #E3EBEC"><span class="num muted" style="position:absolute;left:-30px;top:-9px;font-size:12px;width:22px;text-align:right">{{ $t['v'] }}</span></div>
@endforeach
<div style="position:absolute;inset:0;display:flex;align-items:flex-end;gap:8px;padding:0 4px">
@foreach ($cols['cols'] as $m)
<div style="flex:1;height:100%;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:6px;min-width:0" title="{{ $m['label'] }}: {{ $m['v'] }}">@if ($m['has'])<span class="num" style="font-weight:700;font-size:12.5px">{{ $m['v'] }}</span>@endif<div class="bary" style="width:100%;max-width:40px;height:{{ $m['h'] }};background:{{ $m['c'] }};border-radius:8px 8px 3px 3px;animation-delay:{{ $m['delay'] }}"></div></div>
@endforeach
</div>
@if (! empty($emptyTitle))
<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center"><div style="background:#FFFFFF;border:1px solid #E1E9EA;border-radius:14px;padding:14px 18px;text-align:center;box-shadow:0 10px 24px -16px rgba(15,42,46,.3)"><div style="font-weight:600">{{ $emptyTitle }}</div><div class="muted" style="font-size:12.5px;margin-top:2px">{{ $emptyText }}</div></div></div>
@endif
</div>
<div style="display:flex;gap:8px;margin:10px 0 0 30px;padding:0 4px">@foreach ($cols['cols'] as $m)<span class="muted" style="flex:1;min-width:0;text-align:center;font-size:12px">{{ $m['label'] }}</span>@endforeach</div>

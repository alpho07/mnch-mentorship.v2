{{-- $kpis: list from DashboardPresenter::kpi(); $min: min column width (190 → 6 across at 1440px) --}}
<section aria-label="Key figures" class="grid-auto" style="--min:{{ $min ?? 190 }}px">
@foreach ($kpis as $i => $k)
<article class="kpi rise" style="background:{{ $k['bg'] }};animation-delay:{{ number_format(0.08 + $i * 0.05, 2) }}s">
<svg class="ring" viewBox="0 0 120 120" fill="none" stroke="{{ $k['ink'] }}" aria-hidden="true" style="opacity:.14"><circle cx="60" cy="60" r="56" stroke-width="2"/><circle cx="60" cy="60" r="40" stroke-width="2"/><circle cx="60" cy="60" r="24" stroke-width="2"/></svg>
<div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
<span class="ico" style="background:#FFFFFF;color:{{ $k['ink'] }}">@include('analytics.partials.icon', ['name' => $k['icon']])</span>
@if ($k['chip'])<span class="chip" style="background:#FFFFFF;color:{{ $k['ink'] }}">{{ $k['chip'] }}</span>@endif
</div>
<div>
<div class="disp num" style="font-size:{{ mb_strlen($k['value']) > 5 ? '32px' : '38px' }};font-weight:700;line-height:1">{{ $k['value'] }}@if ($k['suffix'])<span style="font-size:20px;color:{{ $k['ink'] }};font-weight:600">{{ $k['suffix'] }}</span>@endif</div>
<div style="font-weight:600;margin-top:6px">{{ $k['label'] }}</div>
</div>
@if ($k['bar'] !== null)
<span class="track" style="background:#FFFFFF;height:8px"><span class="barx" style="display:block;height:100%;width:{{ number_format(min(100, $k['bar']), 1) }}%;background:{{ $k['ink'] }};border-radius:99px;animation-delay:.5s"></span></span>
@endif
@if ($k['sub'] !== '')
<div style="font-size:12.5px;color:{{ $k['ink'] }};font-weight:500">{{ $k['sub'] }}</div>
@endif
</article>
@endforeach
</section>

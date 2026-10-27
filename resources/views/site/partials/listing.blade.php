{{--
  Listing page body for the public site: hero, optional filter bar, optional facet panels, card grid, pager.
  Params: eyebrow, title, lead, crumbs [[label, url|null]...], resources (paginator or collection), cards (SiteCards::make),
          form ['action','name','value','placeholder','hidden'=>[...]] (optional), sort ['value','options'=>[k=>label]] (optional),
          chips [['label','href','on'?,'count'?]] (optional), panels [['title','items'=>[['label','href','on','count']]]] (optional),
          emptyTitle, emptyText
--}}
@php
    $crumbs = $crumbs ?? []; $chips = $chips ?? []; $panels = $panels ?? []; $form = $form ?? null; $sort = $sort ?? null;
    $hasAside = count($panels) > 0;
@endphp
<section style="position:relative;overflow:hidden;background:var(--deep);color:#FFF">
<div aria-hidden="true" style="position:absolute;inset:0;background-image:radial-gradient(rgba(127,220,230,.15) 1px,transparent 1.3px);background-size:22px 22px"></div>
<div aria-hidden="true" style="position:absolute;right:-120px;top:-160px;width:520px;height:520px;border-radius:50%;border:2px solid rgba(127,220,230,.14)"></div>
<div class="wrap" style="position:relative;padding-top:28px;padding-bottom:44px">
<nav aria-label="Breadcrumb" style="display:flex;align-items:center;gap:8px;font-size:13px;color:#BFE9EE;flex-wrap:wrap"><a href="{{ route('home') }}" style="color:#BFE9EE;text-decoration:none">Home</a>
@foreach ($crumbs as [$cl, $cu])@include('site.partials.icon', ['name' => 'chevron-right', 'size' => 14])@if ($cu)<a href="{{ $cu }}" style="color:#BFE9EE;text-decoration:none">{{ $cl }}</a>@else<span style="color:#FFF;font-weight:600" aria-current="page">{{ $cl }}</span>@endif @endforeach</nav>
<div class="rise" style="margin-top:24px;max-width:760px">
@if (!empty($eyebrow))<span class="eyebrow" style="color:#7FDCE6">{{ $eyebrow }}</span>@endif
<h1 class="disp" style="margin:8px 0 0;font-size:clamp(30px,4vw,46px);font-weight:800;line-height:1.08">{{ $title }}</h1>
@if (!empty($lead))<p style="margin:12px 0 0;font-size:17px;color:#D4EEF1;max-width:640px">{{ $lead }}</p>@endif
</div>
@if ($form)
<form action="{{ $form['action'] }}" method="get" role="search" class="rise" style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap;max-width:640px">
@foreach (($form['hidden'] ?? []) as $hk => $hv)<input type="hidden" name="{{ $hk }}" value="{{ $hv }}">@endforeach
<label style="flex:1 1 280px;position:relative;display:block"><span class="sr">Search</span>@include('site.partials.icon', ['name' => 'search', 'size' => 18, 'stroke' => '#556B6E', 'style' => 'position:absolute;left:14px;top:15px'])<input class="finp" style="width:100%;height:48px;padding-left:42px" type="search" name="{{ $form['name'] }}" value="{{ $form['value'] ?? '' }}" placeholder="{{ $form['placeholder'] ?? 'Search' }}"></label>
<button class="btn btn-pri btn-lg" type="submit">Search</button>
</form>
@endif
</div>
</section>

<div class="wrap" style="padding-top:28px;padding-bottom:72px">
@if (count($chips))
<nav aria-label="Related" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:22px">
@foreach ($chips as $c)<a @class(['tpill', 'on' => !empty($c['on'])]) href="{{ $c['href'] }}">{{ $c['label'] }}@isset($c['count'])<span class="num" style="opacity:.7;font-size:12.5px">{{ $c['count'] }}</span>@endisset</a>@endforeach
</nav>
@endif
<div style="display:flex;flex-wrap:wrap;gap:28px;align-items:flex-start">
@if ($hasAside)
<aside style="flex:0 1 260px;min-width:min(260px,100%);display:flex;flex-direction:column;gap:16px">
@foreach ($panels as $pn)
<nav class="panel" aria-label="{{ $pn['title'] }}">
<div class="eyebrow" style="color:var(--ink-3);font-size:11.5px;margin:0 0 8px 10px">{{ $pn['title'] }}</div>
@foreach ($pn['items'] as $it)<a @class(['frow', 'on' => !empty($it['on'])]) href="{{ $it['href'] }}" @if (!empty($it['on'])) aria-current="true" @endif><span>{{ $it['label'] }}</span>@isset($it['count'])<span class="num" style="font-size:12.5px;color:var(--muted)">{{ $it['count'] }}</span>@endisset</a>@endforeach
</nav>
@endforeach
</aside>
@endif
<section aria-label="Results" style="flex:999 1 560px;min-width:0">
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px">
<h2 class="num" style="margin:0;font-size:15px;font-weight:700" aria-live="polite">{{ number_format($resources instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $resources->total() : count($cards)) }} {{ Str::plural('resource', $resources instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $resources->total() : count($cards)) }}</h2>
@if ($sort)
<form action="{{ url()->current() }}" method="get" style="margin:0">
@foreach (request()->except(['sort', 'page']) as $k => $v)@if (is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
<label style="position:relative;display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--ink-3)">Sort<select class="sel" name="sort" onchange="this.form.submit()">
@foreach ($sort['options'] as $k => $label)<option value="{{ $k }}" @selected(($sort['value'] ?? '') === $k)>{{ $label }}</option>@endforeach
</select>@include('site.partials.icon', ['name' => 'chevron-down', 'size' => 16, 'stroke' => '#556B6E', 'style' => 'position:absolute;right:12px;pointer-events:none'])</label>
<noscript><button class="btn btn-ghost" type="submit">Apply</button></noscript>
</form>
@endif
</div>

@if (count($cards) === 0)
<div style="background:#FFF;border:1px dashed #C9D8DA;border-radius:18px;padding:48px 24px;text-align:center"><div class="disp" style="font-size:20px;font-weight:700">{{ $emptyTitle ?? 'No resources found' }}</div><p class="muted" style="margin:6px 0 16px">{{ $emptyText ?? 'Try a different search, or browse the whole library.' }}</p><a class="btn btn-ghost" href="{{ route('resources.index') }}">Browse all resources</a></div>
@else
@include('site.partials.resource-grid', ['cards' => $cards])
@endif
@include('site.partials.pager', ['paginator' => $resources])
</section>
</div>
</div>

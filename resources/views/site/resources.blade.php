@extends('site.layout')
@section('title', 'Resources')
@section('body-bg', '#F6F9F9')

@push('styles')
.fbtn{display:none}
@media (max-width:860px){.fbtn{display:inline-flex}.hscroll{flex-wrap:nowrap!important;overflow-x:auto;margin-inline:-16px;padding:2px 16px 8px;scrollbar-width:none;-webkit-overflow-scrolling:touch}.hscroll::-webkit-scrollbar{display:none}.hscroll .tpill{flex:none}}
.tpill{display:inline-flex;align-items:center;gap:8px;min-height:40px;padding:0 16px;border-radius:999px;font-size:14px;font-weight:600;background:#FFF;border:1px solid #D7E4E5;color:#2E4A4E;text-decoration:none;transition:background .2s,border-color .2s,color .2s}
.tpill:hover{border-color:#0097A7;color:#00363B}
.tpill.on{background:#00363B;border-color:#00363B;color:#FFF}
.frow{display:flex;justify-content:space-between;align-items:center;gap:10px;width:100%;min-height:40px;padding:6px 10px;border-radius:10px;font-size:14px;color:#2E4A4E;text-decoration:none;transition:background .2s}
.frow:hover{background:#F0F7F7;color:#00363B}
.frow.on{background:#E3F5F7;color:#00363B;font-weight:700}
.sel{appearance:none;-webkit-appearance:none;height:44px;border:1px solid #D3DFE0;border-radius:12px;background:#FFF;padding:0 38px 0 14px;font:inherit;font-size:14px;color:#0F2A2E}
.sel:focus{outline:2px solid #0097A7;outline-offset:1px;border-color:#0097A7}
.vbtn{width:44px;height:44px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;color:#556B6E;border:1px solid #D3DFE0;background:#FFF}
.vbtn:hover{color:#00363B}
.vbtn.on{background:#00363B;color:#FFF;border-color:#00363B}
.rcard{display:flex;flex-direction:column;background:#FFF;border:1px solid #E1E9EA;border-radius:18px;overflow:hidden;text-decoration:none;color:#0F2A2E;transition:transform .25s,box-shadow .25s}
.rcard:hover{transform:translateY(-4px);box-shadow:0 24px 40px -26px rgba(15,42,46,.45);color:#0F2A2E}
.lrow{display:grid;grid-template-columns:64px minmax(0,1fr) auto;gap:16px;align-items:center;padding:12px 14px;background:#FFF;border:1px solid #E1E9EA;border-radius:14px;text-decoration:none;color:#0F2A2E;transition:box-shadow .2s}
.lrow:hover{box-shadow:0 14px 30px -24px rgba(15,42,46,.5);color:#0F2A2E}
.panel{background:#FFF;border:1px solid #E1E9EA;border-radius:18px;padding:18px}
@endpush

@section('content')
<section style="position:relative;overflow:hidden;background:var(--deep);color:#FFF">
<div aria-hidden="true" style="position:absolute;inset:0;background-image:radial-gradient(rgba(127,220,230,.15) 1px,transparent 1.3px);background-size:22px 22px"></div>
<div aria-hidden="true" style="position:absolute;right:-120px;top:-160px;width:520px;height:520px;border-radius:50%;border:2px solid rgba(127,220,230,.14)"></div>
<div class="wrap" style="position:relative;padding-top:28px;padding-bottom:44px">
<nav aria-label="Breadcrumb" style="display:flex;align-items:center;gap:8px;font-size:13px;color:#BFE9EE"><a href="{{ route('home') }}" style="color:#BFE9EE;text-decoration:none">Home</a>@include('site.partials.icon', ['name' => 'chevron-right', 'size' => 14])<span style="color:#FFF;font-weight:600" aria-current="page">Resources</span></nav>
<div class="rise" style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:28px;margin-top:26px">
<div style="flex:1 1 560px;min-width:0">
<div class="eyebrow" style="color:#7FDCE6">Clinical knowledge library</div>
<h1 class="disp" style="margin:10px 0 0;font-size:clamp(34px,4.2vw,52px);line-height:1.04;font-weight:800;text-wrap:balance">Guidelines, manuals &amp; training slides</h1>
<p style="margin:14px 0 0;font-size:17px;color:#BFE9EE;max-width:600px">National materials for health workers mentoring in maternal, newborn and child health — free to read on any device.</p>
</div>
<div style="display:flex;gap:28px;flex-wrap:wrap">
@foreach ($heroStats as [$v, $l])<div><div class="disp num" style="font-size:34px;font-weight:800;line-height:1">{{ $v }}</div><div style="font-size:13px;color:#BFE9EE;margin-top:4px">{{ $l }}</div></div>@endforeach
</div>
</div>
<form role="search" action="{{ route('resources.index') }}" method="get" style="margin-top:28px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:8px;background:#FFF;border-radius:18px;max-width:760px;box-shadow:0 20px 40px -26px rgba(0,0,0,.6)">
@foreach ($hidden as $k => $v)@if ($k !== 'q')<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
@if ($f->sort !== 'views')<input type="hidden" name="sort" value="{{ $f->sort }}">@endif
<label style="flex:1 1 300px;position:relative;display:block"><span class="sr">Search resources</span>@include('site.partials.icon', ['name' => 'search', 'stroke' => '#556B6E', 'style' => 'position:absolute;left:14px;top:15px'])<input class="inp" type="search" name="q" value="{{ $f->q }}" placeholder="Search by title, e.g. CPAP, triage, jaundice" style="height:48px;border-radius:12px;padding:0 12px 0 42px;font-size:15.5px"></label>
<button class="btn btn-pri btn-lg" type="submit">Search</button>
<a class="btn btn-ghost btn-lg" href="{{ route('categories.index') }}">Browse by category</a>
</form>
</div>
</section>

<div class="wrap" style="padding-top:28px;padding-bottom:64px">
<nav aria-label="Topic" class="hscroll" style="display:flex;gap:8px;flex-wrap:wrap">
@foreach ($pills as $t)
<a @class(['tpill', 'on' => $t['on']]) href="{{ $t['href'] }}" @if ($t['on']) aria-current="true" @endif><span style="width:9px;height:9px;border-radius:50%;background:{{ $t['c'] }}"></span>{{ $t['name'] }}<span class="num" style="font-size:12px;opacity:.75">{{ $t['n'] }}</span></a>
@endforeach
</nav>

<div x-data="{ fo: window.innerWidth > 860 }" style="display:flex;flex-wrap:wrap;gap:28px;margin-top:26px;align-items:flex-start">
<button type="button" class="btn btn-ghost fbtn" @click="fo = !fo" :aria-expanded="fo" style="width:100%">@include('site.partials.icon', ['name' => 'layers', 'size' => 18])<span x-text="fo ? 'Hide filters' : 'Filters'"></span></button>
<aside aria-label="Filters" x-show="fo" style="flex:1 1 240px;max-width:280px;min-width:0;display:flex;flex-direction:column;gap:18px">
<nav class="panel" aria-label="Format">
<div class="eyebrow" style="color:var(--ink-3);font-size:11.5px;margin:0 0 8px 10px">Format</div>
@foreach ($formats as $fm)
<a @class(['frow', 'on' => $fm['on']]) href="{{ $fm['href'] }}" @if ($fm['on']) aria-current="true" @endif><span style="display:inline-flex;align-items:center;gap:10px"><span style="width:28px;height:28px;border-radius:8px;background:{{ $fm['bg'] }};color:{{ $fm['c'] }};display:inline-flex;align-items:center;justify-content:center">@include('site.partials.icon', ['name' => $fm['icon'], 'size' => 15])</span>{{ $fm['name'] }}</span><span class="num" style="font-size:12.5px;color:var(--muted)">{{ $fm['n'] }}</span></a>
@endforeach
</nav>
@if (count($subs))
<nav class="panel" aria-label="Subtopic">
<div class="eyebrow" style="color:var(--ink-3);font-size:11.5px;margin:0 0 8px 10px">Subtopic</div>
@foreach ($subs as $s)
<a @class(['frow', 'on' => $s['on']]) href="{{ $s['href'] }}" @if ($s['on']) aria-current="true" @endif><span>{{ $s['name'] }}</span><span class="num" style="font-size:12.5px;color:var(--muted)">{{ $s['n'] }}</span></a>
@endforeach
</nav>
@endif
<div style="border-radius:18px;padding:18px;background:#E5F6F8;color:var(--deep)">
<div class="disp" style="font-size:17px;font-weight:700">Can't find a guideline?</div>
<p style="margin:6px 0 12px;font-size:13.5px;color:var(--ink-2)">Ask the programme team to add it to the library.</p>
<a href="mailto:{{ $contactEmail }}" style="font-weight:700;font-size:14px">{{ $contactEmail }}</a>
</div>
</aside>

<section aria-label="Results" style="flex:999 1 560px;min-width:0">
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px">
<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap"><h2 class="num" style="margin:0;font-size:15px;font-weight:700" aria-live="polite">{{ $countLabel }}@if ($f->q) <span class="muted" style="font-weight:600">for “{{ $f->q }}”</span>@endif</h2>@if ($f->isFiltered())<a class="btn btn-ghost" href="{{ $clearHref }}" style="min-height:34px;padding:0 12px;font-size:13px">Clear filters ×</a>@endif</div>
<div style="display:flex;align-items:center;gap:10px">
<form action="{{ route('resources.index') }}" method="get" style="margin:0">
@foreach ($hidden as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
<label style="position:relative;display:flex;align-items:center;gap:8px;font-size:13.5px;color:var(--ink-3)">Sort<select class="sel" name="sort" onchange="this.form.submit()">
@foreach (['views' => 'Most viewed', 'downloads' => 'Most downloaded', 'recent' => 'Newest', 'az' => 'A–Z'] as $k => $label)<option value="{{ $k }}" @selected($f->sort === $k)>{{ $label }}</option>@endforeach
</select>@include('site.partials.icon', ['name' => 'chevron-down', 'size' => 16, 'stroke' => '#556B6E', 'style' => 'position:absolute;right:12px;pointer-events:none'])</label>
<noscript><button class="btn btn-ghost" type="submit">Apply</button></noscript>
</form>
<a @class(['vbtn', 'on' => $f->view === 'grid']) href="{{ $gridHref }}" aria-label="Grid view" @if ($f->view === 'grid') aria-current="true" @endif>@include('site.partials.icon', ['name' => 'grid'])</a>
<a @class(['vbtn', 'on' => $f->view === 'list']) href="{{ $listHref }}" aria-label="List view" @if ($f->view === 'list') aria-current="true" @endif>@include('site.partials.icon', ['name' => 'list'])</a>
</div>
</div>

@if ($total === 0)
<div style="background:#FFF;border:1px dashed #C9D8DA;border-radius:18px;padding:48px 24px;text-align:center"><div class="disp" style="font-size:20px;font-weight:700">No resources match</div><p class="muted" style="margin:6px 0 16px">Try a different word or clear the filters.</p><a class="btn btn-ghost" href="{{ $clearHref }}">Clear filters</a></div>
@elseif ($f->view === 'list')
<div style="display:flex;flex-direction:column;gap:10px">
@foreach ($items as $r)
<a class="lrow fadein" href="{{ $r['url'] }}" style="animation-delay:{{ $r['delay'] }}">
<span style="width:64px;height:64px;border-radius:12px;background:{{ $r['cover'] }};color:#FFF;display:flex;align-items:center;justify-content:center;overflow:hidden">@if ($r['img'])<img src="{{ $r['img'] }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover">@else @include('site.partials.icon', ['name' => $r['icon'], 'size' => 24, 'sw' => 1.8])@endif</span>
<span style="min-width:0;display:flex;flex-direction:column;gap:4px"><span style="font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $r['title'] }}</span><span class="muted" style="font-size:13px">{{ $r['formatLabel'] }} · {{ $r['sub'] }} · added {{ $r['age'] }}</span></span>
<span class="muted num hide-md" style="font-size:13px;display:flex;gap:14px"><span>{{ $r['views'] }} views</span><span>{{ $r['downloads'] }} downloads</span></span>
</a>
@endforeach
</div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(250px,100%),1fr));gap:18px">
@foreach ($items as $r)
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
@endif

@if ($total > 0)
<nav aria-label="Pagination" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:24px">
<span class="muted num" style="font-size:13.5px">{{ $pageLabel }}</span>
@if (count($pageLinks))
<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
<a @class(['btn btn-ghost', 'is-disabled' => ! $prevHref]) href="{{ $prevHref ?? '#' }}" aria-label="Previous page" @if (! $prevHref) aria-disabled="true" tabindex="-1" @endif>@include('site.partials.icon', ['name' => 'chevron-left', 'size' => 16])</a>
@foreach ($pageLinks as $p)
<a @class(['btn', 'btn-pri' => $p['on'], 'btn-ghost' => ! $p['on']]) href="{{ $p['href'] }}" style="min-width:44px" @if ($p['on']) aria-current="page" @endif>{{ $p['n'] }}</a>
@endforeach
<a @class(['btn btn-ghost', 'is-disabled' => ! $nextHref]) href="{{ $nextHref ?? '#' }}" aria-label="Next page" @if (! $nextHref) aria-disabled="true" tabindex="-1" @endif>@include('site.partials.icon', ['name' => 'chevron-right', 'size' => 16])</a>
</div>
@endif
</nav>
@endif
</section>
</div>
</div>
@endsection

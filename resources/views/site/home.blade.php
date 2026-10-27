@extends('site.layout')
@section('title', 'Maternal, newborn & child health training')

@push('styles')
.qchip{display:inline-flex;align-items:center;gap:6px;min-height:36px;padding:0 14px;border-radius:999px;font-size:13px;font-weight:600;color:#00505A;background:#FFF;border:1px solid #CFE6E8;text-decoration:none;transition:border-color .2s,background .2s}
.qchip:hover{border-color:#0097A7;background:#F2FBFC;color:#00363B}
.seg-ctl{display:inline-flex;padding:5px;border-radius:14px;background:#EEF4F4;gap:4px;flex-wrap:wrap}
.seg-b{all:unset;box-sizing:border-box;min-height:40px;padding:0 16px;border-radius:10px;font-weight:600;font-size:14px;color:#3D5558;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:background .2s,color .2s}
.seg-b:hover{color:#0F2A2E}
.seg-b:focus-visible{outline:2px solid #0097A7}
.seg-b.on{background:#FFF;color:#00363B;box-shadow:0 2px 8px -3px rgba(15,42,46,.25)}
.seg-b .badge{font-size:12px;padding:1px 8px;border-radius:99px;background:#FFF}
.seg-b.on .badge{background:#E3F5F7}
.cbtn{all:unset;box-sizing:border-box;display:flex;justify-content:space-between;align-items:center;gap:10px;width:100%;min-height:44px;padding:8px 12px;border-radius:12px;cursor:pointer;color:#E0F7FA;transition:background .2s}
.cbtn:hover{background:rgba(255,255,255,.08)}
.cbtn:focus-visible{outline:2px solid #7FDCE6}
.cbtn.on{background:rgba(127,220,230,.16)}
.kmap-c{cursor:pointer;transition:opacity .2s}
.kmap-c:hover{opacity:.78}
.kmap-in{animation:kmapin .9s cubic-bezier(.2,.75,.25,1) both}
@keyframes kmapin{from{opacity:0;transform:translateY(8px) scale(.985)}}
.hero-search{margin-top:30px;display:flex;gap:8px;max-width:580px;padding:8px;background:#FFF;border:1px solid #D7E6E8;border-radius:18px;box-shadow:0 18px 36px -24px rgba(11,46,79,.45)}
@media (max-width:640px){.fl-child{left:8px!important;width:112px!important;height:112px!important}.fl-a{left:8px!important;top:12px!important}.fl-b{right:8px!important;bottom:24px!important}}
@endpush

@section('content')
{{-- ───────── Hero ───────── --}}
<section style="position:relative;overflow:hidden;background:var(--bg-mint)">
<div aria-hidden="true" style="position:absolute;inset:0;background-image:radial-gradient(rgba(0,131,143,.13) 1px,transparent 1.4px);background-size:24px 24px"></div>
<div aria-hidden="true" style="position:absolute;right:-180px;top:-220px;width:720px;height:720px;border-radius:50%;border:2px solid rgba(0,151,167,.12)"></div>
<div aria-hidden="true" style="position:absolute;right:-90px;top:-130px;width:540px;height:540px;border-radius:50%;border:2px solid rgba(0,151,167,.1)"></div>
<div class="wrap" style="position:relative;display:flex;flex-wrap:wrap;align-items:center;gap:48px;padding-top:56px;padding-bottom:72px">
<div class="rise" style="flex:1 1 520px;min-width:0">
<span class="chip" style="background:#FFF;color:#00505A;border:1px solid #CFE6E8"><span style="width:8px;height:8px;border-radius:50%;background:#1F9D55"></span>Ministry of Health · Republic of Kenya</span>
<h1 class="disp" style="margin:22px 0 0;font-size:clamp(40px,5.4vw,68px);line-height:1;font-weight:800;color:var(--navy);text-wrap:balance">Strengthening care for <span style="color:#0089A0">mothers</span>, <span style="color:#0089A0">newborns</span> &amp; <span style="color:var(--green)">children</span></h1>
<p style="margin:22px 0 0;font-size:18.5px;line-height:1.55;color:var(--ink-3);max-width:560px;text-wrap:pretty">Kenya's training and mentorship hub for maternal, newborn and child health — clinical guidelines, facility mentorship and live coverage across the counties.</p>
<form class="hero-search" role="search" action="{{ route('resources.index') }}" method="get">
<label style="flex:1;position:relative;display:block"><span class="sr">Search guidelines, manuals and slides</span>@include('site.partials.icon', ['name' => 'search', 'stroke' => '#556B6E', 'style' => 'position:absolute;left:14px;top:15px'])<input class="inp" name="q" type="search" placeholder="Search guidelines, manuals and slides" style="height:48px;padding:0 12px 0 42px;font-size:15.5px;border-radius:12px"></label>
<button class="btn btn-pri btn-lg" type="submit">Search</button>
</form>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:16px;align-items:center">
<span class="muted" style="font-size:13px;font-weight:600;margin-right:4px">Popular:</span>
@foreach ($popular as $q)<a class="qchip" href="{{ $q['href'] }}">{{ $q['label'] }}</a>@endforeach
</div>
</div>
<div style="flex:1 1 440px;min-width:0;position:relative;display:flex;justify-content:center;padding:10px 0 20px">
<div style="position:relative;width:min(420px,100%)">
<div class="zoom" style="position:relative;width:100%;aspect-ratio:400 / 520;border-radius:220px 220px 32px 32px;overflow:hidden;box-shadow:0 40px 70px -40px rgba(11,46,79,.6);background:#CFE3EA">
<img src="{{ $img }}/mother-newborn.jpg" alt="A smiling mother holding her sleeping newborn" style="width:100%;height:100%;object-fit:cover;display:block">
</div>
<div class="zoom fl-child" style="position:absolute;left:-56px;bottom:-14px;width:150px;height:150px;border-radius:50%;overflow:hidden;border:6px solid #FFF;box-shadow:0 20px 40px -20px rgba(11,46,79,.6);animation-delay:.25s"><img src="{{ $img }}/child-smile.jpg" alt="A smiling toddler" style="width:100%;height:100%;object-fit:cover;display:block"></div>
<div class="card float fl-a" style="position:absolute;left:-74px;top:56px;padding:14px 16px;display:flex;align-items:center;gap:12px;border-radius:16px">
<span style="width:42px;height:42px;border-radius:12px;background:#E5F6F8;color:#00838F;display:flex;align-items:center;justify-content:center">@include('site.partials.icon', ['name' => $heroStats[0][0], 'size' => 20])</span>
<span style="display:flex;flex-direction:column;line-height:1.15"><span class="disp num" style="font-size:24px;font-weight:800">{{ $heroStats[0][1] }}</span><span class="muted" style="font-size:12.5px;font-weight:600">{{ $heroStats[0][2] }}</span></span>
</div>
<div class="card float fl-b" style="position:absolute;right:-40px;bottom:70px;padding:14px 16px;display:flex;align-items:center;gap:12px;border-radius:16px;animation-delay:-3s">
<span class="pulse" style="width:10px;height:10px;border-radius:50%;background:#12805C;flex:none"></span>
<span style="display:flex;flex-direction:column;line-height:1.15"><span class="disp num" style="font-size:24px;font-weight:800">{{ $heroStats[1][1] }}</span><span class="muted" style="font-size:12.5px;font-weight:600">{{ $heroStats[1][2] }}</span></span>
</div>
</div>
</div>
</div>
</section>

{{-- ───────── Programmes ───────── --}}
<section class="sec">
<div class="wrap">
<div class="sec-h"><div><div class="eyebrow" style="color:#0089A0">Mentorship programmes</div><h2 class="disp">Three programmes, one platform</h2><p class="muted">Structured, on-site mentorship that keeps skills sharp where care happens — each with its own manuals, modules and live dashboard.</p></div></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(320px,100%),1fr));gap:20px">
@foreach ($programmes as $p)
<article class="card lift rise" style="padding:0;overflow:hidden;display:flex;flex-direction:column;animation-delay:{{ $p['delay'] }}">
<div style="position:relative;padding:26px 26px 22px;background:{{ $p['bg'] }};overflow:hidden">
<svg viewBox="0 0 120 120" fill="none" stroke="{{ $p['c'] }}" aria-hidden="true" style="position:absolute;right:-30px;top:-30px;width:150px;height:150px;opacity:.16"><circle cx="60" cy="60" r="56" stroke-width="2"/><circle cx="60" cy="60" r="40" stroke-width="2"/><circle cx="60" cy="60" r="24" stroke-width="2"/></svg>
<span style="width:52px;height:52px;border-radius:16px;background:#FFF;color:{{ $p['c'] }};display:flex;align-items:center;justify-content:center;box-shadow:0 10px 20px -14px rgba(15,42,46,.5)">@include('site.partials.icon', ['name' => $p['icon'], 'size' => 24])</span>
<div class="eyebrow" style="color:{{ $p['ink'] }};margin-top:18px">{{ $p['tag'] }}</div>
<h3 class="disp" style="margin:6px 0 0;font-size:25px;font-weight:700">{{ $p['name'] }}</h3>
</div>
<div style="padding:22px 26px 26px;display:flex;flex-direction:column;gap:18px;flex:1">
<p class="muted" style="margin:0;text-wrap:pretty">{{ $p['body'] }}</p>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:auto">
<a class="btn btn-ghost" href="{{ $p['library'] }}" style="min-height:42px;padding:0 16px">Manuals &amp; slides</a>
<a class="btn btn-ghost" href="{{ $p['dash'] }}" style="min-height:42px;padding:0 16px;color:{{ $p['ink'] }}">Dashboard @include('site.partials.icon', ['name' => 'arrow-right', 'size' => 16])</a>
</div>
</div>
</article>
@endforeach
</div>
</div>
</section>

{{-- ───────── Programme intelligence (map) ───────── --}}
<section style="margin-top:64px;background:var(--deep);background-image:radial-gradient(rgba(127,220,230,.14) 1px,transparent 1.3px);background-size:22px 22px;color:#FFF;padding:84px 0">
<div class="wrap">
<div class="sec-h"><div><div class="eyebrow" style="color:#7FDCE6">Programme intelligence</div><h2 class="disp" style="color:#FFF">Where mentorship is happening</h2><p style="color:#BFE9EE">A live readout from scheduled trainings, facility mentorships, class enrolments and participant records.</p></div><a class="btn btn-light btn-lg" href="{{ $dashboardUrl }}">Open analytics @include('site.partials.icon', ['name' => 'arrow-right', 'size' => 16])</a></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:16px">
@foreach ($intel as $i => [$label, $value, $sub, $icon, $bg, $c])
<article class="rise" style="border-radius:18px;padding:22px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);animation-delay:{{ .05 + $i * .07 }}s">
<div style="display:flex;justify-content:space-between;align-items:center"><span class="eyebrow" style="color:#BFE9EE;font-size:11.5px">{{ $label }}</span><span style="width:36px;height:36px;border-radius:11px;background:{{ $bg }};color:{{ $c }};display:flex;align-items:center;justify-content:center">@include('site.partials.icon', ['name' => $icon])</span></div>
<div class="disp num" style="font-size:44px;font-weight:800;line-height:1;margin-top:16px">{{ $value }}</div>
<p style="margin:10px 0 0;color:#CFEFF3;font-size:14px">{{ $sub }}</p>
</article>
@endforeach
</div>

<div style="display:flex;flex-wrap:wrap;gap:20px;margin-top:20px" x-data="{ sel: @js($firstCounty), hov: '', live: @js(array_keys($byCounty)) }">
<div style="flex:1.3 1 520px;min-width:0;border-radius:20px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.12);padding:24px;display:flex;flex-direction:column">
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><span class="chip" style="background:rgba(255,255,255,.1);color:#E0F7FA">{{ $heroStats[0][1] }} {{ $heroStats[0][2] }}</span>
<div style="display:flex;gap:12px;flex-wrap:wrap">@foreach ($legend as [$t, $c])<span style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;color:#CFEFF3"><span style="width:12px;height:12px;border-radius:3px;background:{{ $c }}"></span>{{ $t }}</span>@endforeach</div></div>
<div style="max-width:420px;width:100%;margin:18px auto 0;position:relative">
<svg class="kmap-in" viewBox="0 0 400 507" role="img" aria-label="Map of Kenya's 47 counties shaded by live mentorships" style="width:100%;height:auto;display:block;overflow:visible">
@foreach ($map as $c)
<path class="kmap-c" d="{{ $c['d'] }}" fill="{{ $c['fill'] }}" stroke="#FFFFFF" stroke-width="0.9" stroke-linejoin="round" @click="sel = @js($c['name'])" @mouseenter="hov = @js($c['label'])" @mouseleave="hov = ''"><title>{{ $c['label'] }}</title></path>
@endforeach
@foreach ($map as $c)
<path d="{{ $c['d'] }}" fill="none" stroke="#0F2A2E" stroke-width="2.4" stroke-linejoin="round" pointer-events="none" @if ($c['name'] !== $firstCounty) style="display:none" @endif x-show="sel === @js($c['name'])"/>
@endforeach
</svg>
<div x-show="hov" x-cloak x-text="hov" style="position:absolute;left:50%;bottom:-6px;transform:translateX(-50%);background:#FFF;color:#0F2A2E;border-radius:10px;padding:6px 10px;font-size:12.5px;font-weight:600;white-space:nowrap;pointer-events:none;box-shadow:0 10px 24px -10px rgba(0,0,0,.5)"></div>
</div>
</div>
<div style="flex:1 1 360px;min-width:0;border-radius:20px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.12);padding:22px;display:flex;flex-direction:column;gap:14px">
<div style="display:flex;align-items:center;gap:8px"><span class="pulse" style="width:9px;height:9px;border-radius:50%;background:#3DDC97"></span><span class="eyebrow" style="color:#BFE9EE">Live mentorships by county</span></div>
<div aria-live="polite">
@foreach ($byCounty as $name => $cty)
<div style="border-radius:16px;background:rgba(127,220,230,.1);padding:18px;@if ($name !== $firstCounty) display:none @endif" x-show="sel === @js($name)">
<div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px"><span class="disp" style="font-size:24px;font-weight:700">{{ $name }}</span><span class="disp num" style="font-size:24px;font-weight:800;color:#7FDCE6">{{ $cty['total'] }}</span></div>
<div style="display:flex;flex-direction:column;gap:10px;margin-top:14px">
@foreach ($cty['facilities'] as $f)
<div class="fadein" style="display:grid;grid-template-columns:minmax(0,1fr) 30px;gap:10px;align-items:center"><div style="min-width:0"><div style="font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#E0F7FA" title="{{ $f['n'] }}">{{ $f['n'] }}</div><span class="track" style="margin-top:6px;background:rgba(255,255,255,.1)"><span class="barx" style="display:block;height:100%;width:{{ $f['w'] }};background:#7FDCE6;border-radius:99px"></span></span></div><span class="num" style="text-align:right;font-weight:700">{{ $f['v'] }}</span></div>
@endforeach
</div>
</div>
@endforeach
<div style="border-radius:16px;background:rgba(127,220,230,.1);padding:18px;display:none" x-show="!live.includes(sel)">
<div class="disp" style="font-size:24px;font-weight:700" x-text="sel"></div>
<p style="margin:10px 0 0;color:#CFEFF3;font-size:14px">No live mentorship in this county right now.</p>
</div>
</div>
<div style="display:flex;flex-direction:column;gap:2px">
@foreach ($countyList as $name)
<button type="button" @class(['cbtn', 'on' => $name === $firstCounty]) :class="{ on: sel === @js($name) }" :aria-pressed="(sel === @js($name)).toString()" aria-pressed="{{ $name === $firstCounty ? 'true' : 'false' }}" @click="sel = @js($name)"><span style="font-weight:600;font-size:14px">{{ $name }}</span><span class="num" style="font-size:13px;color:#BFE9EE">{{ $byCounty[$name]['total'] }} {{ $byCounty[$name]['total'] === 1 ? 'mentorship' : 'mentorships' }}</span></button>
@endforeach
</div>
</div>
</div>
</div>
</section>

{{-- ───────── Calendar ───────── --}}
<section class="sec" x-data="{ tab: 'live' }">
<div class="wrap">
<div class="sec-h"><div><div class="eyebrow" style="color:#0089A0">Mentorship calendar</div><h2 class="disp">What's running this month</h2></div>
<div class="seg-ctl" role="tablist" aria-label="Mentorship status">
@foreach ([['live', 'Live now', '#12805C'], ['soon', 'Coming up', '#E89B1C'], ['done', 'Recently closed', '#9AA9AB']] as [$key, $label, $dot])
<button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}" @class(['seg-b', 'on' => $key === 'live']) :class="{ on: tab === '{{ $key }}' }" aria-selected="{{ $key === 'live' ? 'true' : 'false' }}" :aria-selected="(tab === '{{ $key }}').toString()" @click="tab = '{{ $key }}'"><span style="width:8px;height:8px;border-radius:50%;background:{{ $dot }}"></span>{{ $label }}<span class="badge num">{{ count($calendar[$key]) }}</span></button>
@endforeach
</div>
</div>
@foreach (['live', 'soon', 'done'] as $key)
<div id="panel-{{ $key }}" role="tabpanel" aria-labelledby="tab-{{ $key }}" x-show="tab === '{{ $key }}'" @if ($key !== 'live') style="display:none" @endif>
@if (count($calendar[$key]))
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(330px,100%),1fr));gap:18px">
@foreach ($calendar[$key] as $m)
<article class="card lift fadein" style="padding:22px;display:flex;flex-direction:column;gap:14px">
<div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><span class="chip" style="background:{{ $m['bg'] }};color:{{ $m['ink'] }}">{{ $m['programme'] }}</span><span class="muted num" style="font-size:13px;font-weight:600">{{ $m['when'] }}</span></div>
<div><h3 class="disp" style="margin:0;font-size:19px;font-weight:700;line-height:1.25">{{ $m['facility'] }}</h3><div class="muted" style="font-size:13.5px;margin-top:4px;display:flex;align-items:center;gap:6px">@include('site.partials.icon', ['name' => 'pin', 'size' => 14]){{ $m['county'] }} · {{ $m['cohort'] }}</div></div>
<div><div style="display:flex;justify-content:space-between;font-size:12.5px;color:var(--ink-3);margin-bottom:6px"><span>{{ $m['duration'] }}</span><span class="num" style="font-weight:700">{{ $m['label'] }}</span></div><span class="track"><span class="barx" style="display:block;height:100%;width:{{ $m['progress'] }};background:{{ $m['bar'] }};border-radius:99px"></span></span></div>
</article>
@endforeach
</div>
@else
<div class="card" style="padding:40px 24px;text-align:center;box-shadow:none;border-style:dashed"><p class="muted" style="margin:0">Nothing in this list right now.</p></div>
@endif
</div>
@endforeach
</div>
</section>

{{-- ───────── Featured resources ───────── --}}
<section class="sec">
<div class="wrap">
<div class="sec-h"><div><div class="eyebrow" style="color:#0089A0">Featured resources</div><h2 class="disp">The manuals teams use most</h2><p class="muted">National guidelines and mentorship packages, free to read on any device.</p></div><a class="btn btn-ghost btn-lg" href="{{ route('resources.index') }}">Browse the library @include('site.partials.icon', ['name' => 'arrow-right', 'size' => 16])</a></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(360px,100%),1fr));gap:20px">
@foreach ($featured as $i => $r)
<a class="card lift rise" href="{{ $r['url'] }}" style="display:flex;gap:18px;padding:16px;text-decoration:none;color:var(--ink);animation-delay:{{ .05 + $i * .05 }}s">
<span style="position:relative;flex:none;width:112px;height:150px;border-radius:12px;overflow:hidden;background:{{ $r['img'] ? '#DDEBF0' : $r['cover'] }};box-shadow:0 10px 20px -14px rgba(15,42,46,.6)">
@if ($r['img'])
<img src="{{ $r['img'] }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block">
@else
<span style="position:absolute;inset:0;padding:14px 12px;display:flex;flex-direction:column;justify-content:space-between;color:#FFF"><span style="font-size:9.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;opacity:.85">{{ $r['format'] }}</span><span class="disp" style="font-size:13.5px;font-weight:700;line-height:1.2;display:-webkit-box;-webkit-line-clamp:5;-webkit-box-orient:vertical;overflow:hidden">{{ $r['title'] }}</span><span style="display:flex;align-items:center;gap:6px;font-size:9.5px;font-weight:600;opacity:.85"><span style="width:14px;height:14px;border-radius:4px;background:rgba(255,255,255,.3)"></span>Ministry of Health</span></span>
@endif
</span>
<span style="display:flex;flex-direction:column;gap:8px;min-width:0;padding:4px 0">
<span style="display:flex;gap:6px;flex-wrap:wrap"><span class="chip" style="height:24px;font-size:11.5px;background:{{ $r['catBg'] }};color:{{ $r['catInk'] }}">{{ $r['topic'] }}</span><span class="chip" style="height:24px;font-size:11.5px;background:#F3F6F6;color:var(--ink-3)">{{ $r['formatLabel'] }}</span></span>
<span class="disp" style="font-size:17px;font-weight:700;line-height:1.25">{{ $r['title'] }}</span>
@if ($r['excerpt'])<span class="muted" style="font-size:13.5px;line-height:1.45;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">{{ $r['excerpt'] }}</span>@endif
<span class="muted num" style="font-size:12.5px;margin-top:auto;display:flex;gap:12px;align-items:center"><span style="display:inline-flex;align-items:center;gap:5px">@include('site.partials.icon', ['name' => 'eye', 'size' => 14]){{ $r['views'] }} views</span><span>{{ $r['age'] }}</span></span>
</span>
</a>
@endforeach
</div>
</div>
</section>

{{-- ───────── Topics ───────── --}}
<section class="sec">
<div class="wrap">
<div class="sec-h"><div><div class="eyebrow" style="color:#0089A0">Browse by topic</div><h2 class="disp">Find it by clinical area</h2></div><a class="btn btn-ghost btn-lg" href="{{ route('categories.index') }}">All categories</a></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(260px,100%),1fr));gap:14px">
@foreach ($topics as $t)
<a class="lift" href="{{ $t['href'] }}" style="display:flex;align-items:center;gap:14px;padding:16px 18px;border-radius:16px;background:{{ $t['bg'] }};text-decoration:none;color:var(--ink);border:1px solid rgba(15,42,46,.05)">
<span style="width:46px;height:46px;border-radius:14px;background:#FFF;color:{{ $t['c'] }};display:flex;align-items:center;justify-content:center;flex:none">@include('site.partials.icon', ['name' => $t['icon'], 'size' => 22])</span>
<span style="display:flex;flex-direction:column;min-width:0"><span style="font-weight:700">{{ $t['name'] }}</span><span class="num" style="font-size:13px;color:{{ $t['ink'] }}">{{ $t['count'] }}</span></span>
</a>
@endforeach
</div>
</div>
</section>

{{-- ───────── App banner ───────── --}}
<section style="padding:84px 0">
<div class="wrap">
<div style="position:relative;overflow:hidden;border-radius:28px;background:var(--navy);display:flex;flex-wrap:wrap;align-items:stretch">
<div style="flex:1 1 460px;min-width:0;padding:clamp(28px,5vw,52px) clamp(22px,4vw,48px);color:#FFF;position:relative;z-index:1">
<div class="eyebrow" style="color:#8FD9A8">MNCH mobile app</div>
<h2 class="disp" style="margin:10px 0 0;font-size:clamp(28px,3vw,40px);font-weight:800;line-height:1.08;text-wrap:balance">Take the library to the ward — even offline</h2>
<p style="margin:16px 0 0;color:#C9DDF3;max-width:460px">Mentors and mentees open manuals, log sessions and track modules on their phones. Content syncs when the network returns.</p>
<div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:28px"><a class="btn btn-pri btn-lg" href="{{ $appUrl }}" style="background:var(--green);box-shadow:none">Get the mobile app</a><a class="btn btn-light btn-lg" href="{{ $loginUrl }}">Sign in to the platform</a></div>
<div style="display:flex;flex-wrap:wrap;gap:18px;margin-top:28px;color:#C9DDF3;font-size:13.5px;font-weight:600">
<span style="display:inline-flex;align-items:center;gap:6px">@include('site.partials.icon', ['name' => 'shield', 'size' => 16, 'stroke' => '#8FD9A8'])Secure sign-in</span>
<span style="display:inline-flex;align-items:center;gap:6px">@include('site.partials.icon', ['name' => 'phone', 'size' => 16, 'stroke' => '#8FD9A8'])Built for phones</span>
<span style="display:inline-flex;align-items:center;gap:6px">@include('site.partials.icon', ['name' => 'cloud', 'size' => 16, 'stroke' => '#8FD9A8'])Works offline</span>
</div>
</div>
<div style="flex:1 1 380px;min-width:0;min-height:320px;position:relative">
<img src="{{ $img }}/kangaroo-care.jpg" alt="A nurse supporting a mother giving kangaroo care to her newborn" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
<div aria-hidden="true" style="position:absolute;inset:0;background:linear-gradient(90deg,#0B2E4F 0%,rgba(11,46,79,0) 32%)"></div>
</div>
</div>
</div>
</section>
@endsection

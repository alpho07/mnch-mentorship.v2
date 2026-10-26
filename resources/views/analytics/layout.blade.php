{{-- Shell for the five analytics dashboards: header, module tabs, hero, footer, all CSS and animations. --}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $pageTitle }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
<style>
:root{--ink:#0F2A2E;--ink-2:#2E4A4E;--ink-3:#3D5558;--muted:#556B6E;--line:#E1E9EA;--line-2:#EEF3F3;--ground:#F2F6F6;--track:#EDF3F4;--teal:#0097A7;--teal-btn:#00838F;--teal-deep:#00363B;--teal-ink:#00505A;--teal-link:#00695C}
body{margin:0;background:var(--ground)}
[x-cloak]{display:none!important}
a{color:var(--teal-link)}a:hover{color:#004D40}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.pg{font-family:'Instrument Sans',system-ui,-apple-system,'Segoe UI',sans-serif;color:var(--ink);background:var(--ground);-webkit-font-smoothing:antialiased;font-size:14px;line-height:1.45;min-height:100vh}
.disp{font-family:'Bricolage Grotesque','Instrument Sans',system-ui,sans-serif;letter-spacing:-0.02em}
.num{font-variant-numeric:tabular-nums}
.wrap{max-width:1320px;margin:0 auto;padding:0 24px;box-sizing:border-box}
.card{background:#FFFFFF;border:1px solid var(--line);border-radius:18px;box-shadow:0 1px 2px rgba(15,42,46,.04),0 12px 32px -18px rgba(15,42,46,.18)}
.muted{color:var(--muted)}
.eyebrow{font-size:12px;font-weight:600;letter-spacing:.09em;text-transform:uppercase}
.navlink{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:0 14px;border-radius:10px;color:var(--ink-2);text-decoration:none;font-weight:500}
.navlink:hover{background:#EEF5F5;color:var(--teal-deep)}
.tab{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:0 18px;border-radius:999px;color:#CFEFF3;text-decoration:none;font-weight:600;transition:background .2s,color .2s}
.tab:hover{background:rgba(255,255,255,.10);color:#FFFFFF}
.tab.on{background:#FFFFFF;color:var(--teal-deep);box-shadow:0 6px 16px -8px rgba(0,0,0,.4)}
.hero{background-color:var(--teal-deep);background-image:radial-gradient(rgba(127,220,230,.16) 1px,transparent 1.3px);background-size:22px 22px;position:relative;overflow:hidden}
.fld{display:flex;flex-direction:column;gap:6px;min-width:0}
.fld label{font-size:12px;font-weight:600;color:var(--ink-3);letter-spacing:.02em}
.selw{position:relative}
.sel{appearance:none;-webkit-appearance:none;width:100%;height:44px;border:1px solid #D3DFE0;border-radius:10px;background:#F8FBFB;padding:0 38px 0 12px;font:inherit;color:var(--ink);box-sizing:border-box}
.sel:hover{border-color:#9FC9CE}
.sel:focus,.inp:focus{outline:2px solid var(--teal);outline-offset:1px;border-color:var(--teal);background:#FFFFFF}
.inp{width:100%;height:44px;border:1px solid #D3DFE0;border-radius:10px;background:#F8FBFB;padding:0 12px 0 38px;font:inherit;color:var(--ink);box-sizing:border-box}
.chev{position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--muted)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 18px;border-radius:10px;font:inherit;font-weight:600;cursor:pointer;border:1px solid transparent;text-decoration:none;transition:background .2s,border-color .2s;box-sizing:border-box}
.btn-pri{background:var(--teal-btn);color:#FFFFFF}.btn-pri:hover{background:#006B75;color:#FFFFFF}
.btn-ghost{background:#FFFFFF;color:var(--ink);border-color:#D3DFE0}.btn-ghost:hover{background:var(--ground);color:var(--ink)}
.btn[disabled]{opacity:.45;cursor:not-allowed}
.chip{display:inline-flex;align-items:center;gap:6px;height:26px;padding:0 10px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap}
.kpi{position:relative;overflow:hidden;border-radius:18px;padding:20px 20px 18px;border:1px solid rgba(15,42,46,.06);display:flex;flex-direction:column;gap:14px;transition:transform .25s,box-shadow .25s}
.kpi:hover{transform:translateY(-3px);box-shadow:0 18px 30px -20px rgba(15,42,46,.35)}
.ring{position:absolute;right:-34px;bottom:-34px;width:132px;height:132px;pointer-events:none}
.ico{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex:none}
.sec{display:flex;align-items:baseline;gap:14px;margin:0 0 16px;flex-wrap:wrap}
.sec .n{font-size:13px;font-weight:700;color:var(--teal)}
.sec h2{margin:0;font-size:24px;font-weight:600}
.ch-h{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:20px}
.ch-h h3{margin:0;font-size:18px;font-weight:600}
.ch-h p{margin:4px 0 0;font-size:13px}
.track{display:block;height:10px;background:var(--track);border-radius:99px;overflow:hidden}
.brow{display:grid;grid-template-columns:minmax(0,var(--lw,150px)) minmax(0,1fr) var(--vw,44px);align-items:center;gap:12px;min-height:30px}
.brow .lb{font-size:13px;color:var(--ink-2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.brow .vl{text-align:right;font-weight:600;font-size:13px}
.brow:hover .barx{filter:brightness(1.08) saturate(1.15)}
.row-flex{display:flex;flex-wrap:wrap;gap:20px}
.grid-auto{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(var(--min,260px),100%),1fr));gap:16px}
.cbtn{box-sizing:border-box;display:grid;grid-template-columns:26px minmax(0,1fr) 46px;align-items:center;gap:10px;width:100%;min-height:44px;padding:6px 10px;border-radius:10px;cursor:pointer;transition:background .2s;text-decoration:none;color:var(--ink)}
.cbtn:hover{background:#F0F7F7;color:var(--ink)}
.cbtn:focus-visible{outline:2px solid var(--teal)}
.cbtn.on{background:#E3F5F7}
.seg-ctl{display:inline-flex;padding:4px;border-radius:12px;background:#EEF4F4;gap:4px;flex-wrap:wrap}
.seg-b{all:unset;box-sizing:border-box;min-height:36px;padding:0 14px;border-radius:9px;font-weight:600;font-size:13px;color:var(--ink-3);cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:background .2s,color .2s}
.seg-b:hover{color:var(--ink)}
.seg-b:focus-visible{outline:2px solid var(--teal)}
.seg-b.on{background:#FFFFFF;color:var(--teal-deep);box-shadow:0 2px 8px -3px rgba(15,42,46,.25)}
.pill{all:unset;box-sizing:border-box;display:inline-flex;align-items:center;gap:8px;min-height:38px;padding:0 14px;border-radius:999px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid #D3DFE0;background:#FFFFFF;color:var(--ink-2);transition:background .2s,border-color .2s,color .2s}
.pill:hover{border-color:#9FC9CE}
.pill:focus-visible{outline:2px solid var(--teal)}
.pill.on{background:var(--teal-deep);border-color:var(--teal-deep);color:#FFFFFF}
.xrow{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr) minmax(0,1fr) auto;gap:18px;align-items:center;padding:12px 4px;border-bottom:1px solid var(--line-2)}
.xrow:last-child{border-bottom:none}
.tbl{width:100%;border-collapse:separate;border-spacing:0;font-size:13px}
.tbl th{text-align:left;font-weight:600;color:var(--ink-3);background:#F5F9F9;padding:12px 14px;border-bottom:1px solid var(--line);white-space:nowrap}
.tbl td{padding:12px 14px;border-bottom:1px solid var(--line-2);vertical-align:middle}
.tbl tbody tr{transition:background .15s}
.tbl tbody tr:hover{background:#F6FAFA}
.lvl{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:24px;padding:0 8px;border-radius:7px;font-size:12px;font-weight:700}
.yn{display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:22px;padding:0 7px;border-radius:6px;font-size:11.5px;font-weight:700}
.step{flex:1 1 180px;min-width:0;position:relative;border-radius:16px;padding:18px;background:#F7FAFA;border:1px solid #E6EDEE}
.tcard{box-sizing:border-box;position:relative;overflow:hidden;border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:12px;background:#FFFFFF;border:1px solid var(--line);text-decoration:none;color:var(--ink);transition:transform .25s,box-shadow .25s,border-color .25s}
.tcard:hover{transform:translateY(-3px);box-shadow:0 18px 30px -20px rgba(15,42,46,.35);color:var(--ink)}
.tcard.on{border-color:var(--ink);box-shadow:0 0 0 1px var(--ink)}
.rcard{all:unset;box-sizing:border-box;flex:1 1 280px;min-width:0;border-radius:18px;padding:20px;cursor:pointer;display:flex;flex-direction:column;gap:10px;border:2px solid transparent;transition:transform .25s,box-shadow .25s,border-color .25s}
.rcard:hover{transform:translateY(-3px);box-shadow:0 18px 30px -20px rgba(15,42,46,.35)}
.rcard:focus-visible{outline:2px solid var(--teal);outline-offset:2px}
.rcard.on{border-color:var(--ink)}
.kmap a path{transition:opacity .2s}
.kmap a:hover path,.kmap a:focus path{opacity:.78}
.kmap a:focus{outline:none}
.skel{background:linear-gradient(90deg,#EDF3F4 0%,#F7FAFA 40%,#EDF3F4 80%);background-size:200% 100%;animation:shim 2.4s ease-in-out infinite}
@keyframes shim{from{background-position:100% 0}to{background-position:-100% 0}}
.rise{animation:rise .75s cubic-bezier(.2,.75,.25,1) both}
@keyframes rise{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
.barx{transform-origin:left center;animation:gx 1.1s cubic-bezier(.2,.75,.25,1) both}
@keyframes gx{from{transform:scaleX(0)}}
.bary{transform-origin:center bottom;animation:gy 1s cubic-bezier(.2,.75,.25,1) both}
@keyframes gy{from{transform:scaleY(0)}}
.reveal{animation:reveal 1.6s cubic-bezier(.45,0,.2,1) both .15s}
@keyframes reveal{from{clip-path:inset(0 100% 0 0)}to{clip-path:inset(0 0 0 0)}}
.seg{animation:seg 1.3s cubic-bezier(.2,.75,.25,1) both}
@keyframes seg{from{stroke-dasharray:0 100}}
.pop{animation:pop .5s cubic-bezier(.2,.75,.25,1.4) both}
@keyframes pop{from{opacity:0;transform:translate(-50%,-50%) scale(0)}}
.dot{animation:dot .6s cubic-bezier(.2,.75,.25,1.4) both}
@keyframes dot{from{transform:translate(-50%,-50%) scale(0)}}
.fadein{animation:fadein .45s ease both}
@keyframes fadein{from{opacity:0;transform:translateY(6px)}}
.kmap-in{animation:fadein .9s cubic-bezier(.2,.75,.25,1) both}
.pt .tip{opacity:0;transition:opacity .15s,transform .15s;transform:translate(-50%,-6px)}
.pt:hover .tip{opacity:1;transform:translate(-50%,-12px)}
@media (max-width:760px){.hero-map,.hide-sm{display:none}.xrow{grid-template-columns:1fr}}
@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}}
</style>
@stack('head')
</head>
<body>
<div class="pg">

<header style="background:#FFFFFF;border-bottom:1px solid var(--line)">
<div class="wrap" style="display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:68px;flex-wrap:wrap">
<a href="{{ $homeUrl ?? '/' }}" style="display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--ink)">
<span style="width:38px;height:38px;border-radius:11px;background:var(--teal-btn);display:flex;align-items:center;justify-content:center;color:#FFFFFF">@include('analytics.partials.icon', ['name' => 'heart-pulse', 'size' => 20, 'sw' => 2])</span>
<span style="display:flex;flex-direction:column;line-height:1.1"><span class="disp" style="font-size:18px;font-weight:700">MNCH Kenya</span><span class="muted" style="font-size:12px;font-weight:500">Mentorship Analytics</span></span>
</a>
<nav aria-label="Primary" style="display:flex;align-items:center;gap:4px;flex-wrap:wrap">
<a class="navlink" href="{{ $homeUrl ?? '/' }}">@include('analytics.partials.icon', ['name' => 'home', 'size' => 17, 'sw' => 1.8])Home</a>
<a class="navlink" href="{{ $resourcesUrl ?? '/resources' }}">@include('analytics.partials.icon', ['name' => 'book', 'size' => 17, 'sw' => 1.8])Resources</a>
<a class="navlink" href="{{ $categoriesUrl ?? '/categories' }}">@include('analytics.partials.icon', ['name' => 'grid', 'size' => 17, 'sw' => 1.8])Categories</a>
<a class="btn btn-pri" href="{{ $adminUrl ?? '/admin' }}" style="margin-left:8px">@include('analytics.partials.icon', ['name' => 'shield', 'size' => 16, 'sw' => 2])Admin</a>
</nav>
</div>
</header>

<section class="hero">
<img class="hero-map" src="{{ $heroImage ?? '/images/analytics/kenya-hero.svg' }}" alt="" style="position:absolute;right:max(24px,calc(50% - 640px));top:28px;height:360px;width:auto;opacity:.95;pointer-events:none">
<div class="wrap" style="position:relative;padding-top:28px;padding-bottom:112px">
<nav aria-label="Dashboards" style="display:inline-flex;flex-wrap:wrap;gap:4px;padding:5px;border-radius:999px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12)">
@foreach ($tabs as $t)
<a @class(['tab', 'on' => $t['on']]) href="{{ $t['href'] }}" @if ($t['on']) aria-current="page" @endif>@include('analytics.partials.icon', ['name' => $t['icon'], 'size' => 16, 'sw' => 2]){{ $t['label'] }}</a>
@endforeach
</nav>
<div class="rise" style="margin-top:44px;max-width:660px">
<div class="eyebrow" style="color:{{ $eyebrowColor }}">{{ $eyebrow }}</div>
<h1 class="disp" style="margin:12px 0 0;font-size:clamp(34px,4.4vw,52px);line-height:1.04;font-weight:700;color:#FFFFFF;text-wrap:balance">{{ $title }}</h1>
<p style="margin:14px 0 0;font-size:17px;color:#BFE9EE;max-width:560px;text-wrap:pretty">{{ $lede }}</p>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:22px">
@foreach ($chips as [$icon, $text])
<span class="chip" style="background:rgba(255,255,255,.1);color:#E0F7FA">@include('analytics.partials.icon', ['name' => $icon, 'size' => 13, 'sw' => 2.2]){{ $text }}</span>
@endforeach
<span class="chip" style="background:rgba(255,255,255,.1);color:#E0F7FA">@include('analytics.partials.icon', ['name' => 'clock', 'size' => 13, 'sw' => 2.2])Updated {{ $asOf }}</span>
</div>
</div>
</div>
</section>

<main class="wrap" style="margin-top:-76px;position:relative;display:flex;flex-direction:column;gap:36px;padding-bottom:56px">
@yield('content')
</main>

<footer style="border-top:1px solid var(--line);background:#FFFFFF">
<div class="wrap muted" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;padding-top:20px;padding-bottom:20px;font-size:12.5px"><span>MNCH Kenya Mentorship Platform · Analytics</span><span>Data as of {{ $asOf }}</span></div>
</footer>
</div>
</body>
</html>

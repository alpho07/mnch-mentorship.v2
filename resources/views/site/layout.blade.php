{{--
  Shell for the public pages: fonts, design tokens, header, footer, Alpine.
  Each page sets: @section('title'), @section('body-bg') (optional), @section('content'), and $page ('home'|'resources'|'categories').
--}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title') · MNCH Kenya</title>
<meta name="description" content="@yield('description', 'Kenya\'s training and mentorship hub for maternal, newborn and child health.')">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@unless (!empty($alpineLoaded))
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
<link rel="stylesheet" href="{{ asset('css/motion.css') }}?v={{ filemtime(public_path('css/motion.css')) }}">
<script defer src="{{ asset('js/motion.js') }}?v={{ filemtime(public_path('js/motion.js')) }}"></script>
@endunless
<style>
:root{
  --teal:#0097A7;--teal-btn:#00838F;--teal-btn-h:#006B75;--deep:#00363B;--navy:#0B2E4F;--green:#2E9E4F;
  --ink:#0F2A2E;--ink-2:#2E4A4E;--ink-3:#3D5558;--muted:#556B6E;--line:#E1E9EA;--line-2:#D3DFE0;
  --bg:#FFFFFF;--bg-soft:#F6F9F9;--bg-mint:#F2F8F7;--footer:#06222A;
  --font-display:system-ui, -apple-system, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif;
  --font-text:system-ui, -apple-system, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif;
}
*,*::before,*::after{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
html{scroll-padding-top:84px}
body{margin:0;background:@yield('body-bg', '#FFFFFF');font-family:var(--font-text);color:var(--ink);-webkit-font-smoothing:antialiased;font-size:15px;line-height:1.5}
img{max-width:100%}
a{color:#00695C}a:hover{color:#004D40}
[x-cloak]{display:none!important}
.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.skip{position:absolute;left:12px;top:-60px;z-index:50;background:var(--deep);color:#FFF;padding:10px 14px;border-radius:10px;font-weight:700}
.skip:focus{top:12px;color:#FFF}
.disp{font-family:var(--font-display);letter-spacing:-0.02em}
.num{font-variant-numeric:tabular-nums}
.wrap{max-width:1280px;margin:0 auto;padding:0 24px}
@media (max-width:640px){.wrap{padding:0 16px}}
.muted{color:var(--muted)}
.eyebrow{font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase}
.navlink{display:inline-flex;align-items:center;min-height:44px;padding:0 14px;border-radius:10px;color:var(--ink-2);text-decoration:none;font-weight:600;font-size:14.5px;transition:background .2s,color .2s}
.navlink:hover{background:#EEF5F5;color:var(--deep)}
.navlink.on{color:var(--deep);background:#E3F5F7}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 18px;border-radius:12px;font:inherit;font-weight:700;font-size:14px;cursor:pointer;border:1px solid transparent;text-decoration:none;transition:background .2s,transform .2s,box-shadow .2s;white-space:nowrap}
.btn-lg{min-height:48px;padding:0 22px;font-size:15px}
.btn-pri{background:var(--teal-btn);color:#FFF;box-shadow:0 10px 24px -12px rgba(0,131,143,.8)}.btn-pri:hover{background:var(--teal-btn-h);color:#FFF}
.btn-ghost{background:#FFF;color:var(--ink);border-color:var(--line-2)}.btn-ghost:hover{background:#F2F6F6;color:var(--ink)}
.btn-light{background:rgba(255,255,255,.1);color:#FFF;border-color:rgba(255,255,255,.28)}.btn-light:hover{background:rgba(255,255,255,.18);color:#FFF}
.btn.is-disabled{opacity:.45;pointer-events:none}
.btn:focus-visible,.navlink:focus-visible,a:focus-visible{outline:2px solid var(--teal);outline-offset:2px}
.chip{display:inline-flex;align-items:center;gap:6px;height:28px;padding:0 12px;border-radius:999px;font-size:12.5px;font-weight:600;white-space:nowrap}
.chip-sm{height:26px;padding:0 10px;font-size:12px}
.card{background:#FFF;border:1px solid var(--line);border-radius:20px;box-shadow:0 1px 2px rgba(15,42,46,.04),0 14px 34px -20px rgba(15,42,46,.22)}
.lift{transition:transform .25s,box-shadow .25s}
.lift:hover{transform:translateY(-4px);box-shadow:0 24px 40px -24px rgba(15,42,46,.4)}
.track{display:block;height:6px;background:#EDF3F4;border-radius:99px;overflow:hidden}
.sec{padding:84px 0 24px}
.sec-h{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:28px}
.sec-h h2{margin:8px 0 0;font-size:clamp(28px,3vw,38px);font-weight:700;line-height:1.1}
.sec-h p{margin:8px 0 0;max-width:620px}
.inp{width:100%;font:inherit;color:var(--ink);background:transparent;border:0}
.inp:focus{outline:2px solid var(--teal);outline-offset:1px}
.flink{color:#BFD8DA;text-decoration:none;font-size:14px}.flink:hover{color:#FFF}
/* motion */
.rise{animation:rise .8s cubic-bezier(.2,.75,.25,1) both}
@keyframes rise{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
.zoom{animation:zoom 1.2s cubic-bezier(.2,.75,.25,1) both}
@keyframes zoom{from{opacity:0;transform:scale(1.05)}to{opacity:1;transform:none}}
.float{animation:float 6s ease-in-out infinite}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.pulse{animation:pulse 1.8s ease-out infinite}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(18,128,92,.55)}100%{box-shadow:0 0 0 10px rgba(18,128,92,0)}}
.barx{transform-origin:left center;animation:gx 1.1s cubic-bezier(.2,.75,.25,1) both}
@keyframes gx{from{transform:scaleX(0)}}
.fadein{animation:fadein .45s ease both}
@keyframes fadein{from{opacity:0;transform:translateY(8px)}}
@media (max-width:860px){.hide-md{display:none!important}}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
@stack('styles')
</style>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>

@if (request('signed_out') === 'inactive')
<div role="status" x-data="{ show: true }" x-show="show" style="background:#FEF3C7;color:#6B4A00;border-bottom:1px solid #F3D98B"><div class="wrap" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding-top:12px;padding-bottom:12px;font-size:14.5px"><span><b>You were signed out</b> after {{ (int) round(config('session.idle_timeout') / 60) }} minutes of inactivity. <a href="{{ $loginUrl }}" style="color:#6B4A00;font-weight:700">Sign in again</a> to continue.</span><button type="button" @click="show = false" aria-label="Dismiss" style="border:0;background:none;font-size:22px;line-height:1;cursor:pointer;color:#6B4A00;min-width:44px;min-height:44px">&times;</button></div></div>
@endif
<header style="position:sticky;top:0;z-index:40;background:rgba(255,255,255,.96);-webkit-backdrop-filter:saturate(1.4) blur(8px);backdrop-filter:saturate(1.4) blur(8px);border-bottom:1px solid #E6EEEF">
<div class="wrap" style="display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:76px;flex-wrap:wrap">
<a href="{{ route('home') }}" style="display:flex;align-items:center;gap:14px;text-decoration:none;color:var(--ink)">
<img src="{{ $img }}/moh-logo.png" alt="Ministry of Health, Republic of Kenya" width="88" height="34" style="height:34px;width:auto">
<span style="width:1px;height:30px;background:#DCE6E7"></span>
<span style="display:flex;flex-direction:column;line-height:1.05"><span class="disp" style="font-size:19px;font-weight:800">MNCH Kenya</span><span class="muted" style="font-size:12px;font-weight:600">Training &amp; mentorship hub</span></span>
</a>
<nav aria-label="Primary" style="display:flex;align-items:center;gap:2px;flex-wrap:wrap">
@foreach ([['home', 'Home', route('home')], ['resources', 'Resources', route('resources.index')], ['categories', 'Categories', route('categories.index')], ['dashboards', 'Dashboards', $dashboardUrl]] as [$key, $label, $href])
<a @class(['navlink', 'on' => $page === $key]) href="{{ $href }}" @if ($page === $key) aria-current="page" @endif>{{ $label }}</a>
@endforeach
</nav>
<div style="display:flex;align-items:center;gap:10px">
@if ($page === 'home')
<form class="hide-md" role="search" action="{{ route('resources.index') }}" method="get" style="position:relative;display:block;margin:0">
<label for="hdr-q" class="sr">Search resources</label>
@include('site.partials.icon', ['name' => 'search', 'size' => 16, 'stroke' => '#556B6E', 'style' => 'position:absolute;left:12px;top:14px'])
<input id="hdr-q" name="q" type="search" placeholder="Search resources" style="width:220px;height:44px;border:1px solid var(--line-2);border-radius:12px;background:#F6FAFA;padding:0 12px 0 36px;font:inherit;font-size:14px;color:var(--ink)">
</form>
@endif
@auth
<div x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false" style="position:relative">
<button type="button" class="btn btn-ghost" @click="open = !open" :aria-expanded="open" aria-haspopup="true" style="gap:10px;padding:0 10px 0 6px"><span style="width:32px;height:32px;border-radius:99px;background:var(--teal-btn);color:#FFF;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:13px">{{ mb_strtoupper(mb_substr(auth()->user()->first_name ?? auth()->user()->name ?? 'U', 0, 1)) }}</span><span class="hide-md">{{ auth()->user()->first_name ?? 'Account' }}</span>@include('site.partials.icon', ['name' => 'chevron-down', 'size' => 14])</button>
<div x-show="open" x-cloak x-transition.opacity style="position:absolute;right:0;top:52px;z-index:60;min-width:230px;padding:8px;background:#FFF;border:1px solid var(--line);border-radius:14px;box-shadow:0 24px 40px -20px rgba(15,42,46,.45)">
<div style="padding:8px 12px 10px;border-bottom:1px solid var(--line);margin-bottom:6px"><b style="display:block;font-size:14px">{{ auth()->user()->full_name ?? auth()->user()->name }}</b><span class="muted" style="font-size:12.5px;word-break:break-all">{{ auth()->user()->email }}</span></div>
<a class="navlink" style="display:flex;width:100%" href="{{ url('/admin') }}">Admin panel</a>
<a class="navlink" style="display:flex;width:100%" href="{{ route('resources.bookmarks') }}">Bookmarks</a>
<a class="navlink" style="display:flex;width:100%" href="{{ route('resources.downloads') }}">Downloads</a>
<form method="POST" action="{{ url('admin/logout') }}" style="margin:6px 0 0;border-top:1px solid var(--line);padding-top:6px">@csrf<button type="submit" class="navlink" style="display:flex;width:100%;border:0;background:none;font:inherit;font-weight:600;color:#B23149;cursor:pointer">Sign out</button></form>
</div>
</div>
@else
<a class="btn btn-ghost" href="{{ $loginUrl }}">@include('site.partials.icon', ['name' => 'login', 'size' => 16])Sign in</a>
@endauth
</div>
</div>
</header>

<main id="main">
@yield('content')
</main>

<footer style="background:var(--footer);color:#FFF;padding:64px 0 28px">
<div class="wrap">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr));gap:36px">
<div style="display:flex;flex-direction:column;gap:16px">
<span style="display:inline-flex;align-self:flex-start;padding:10px 12px;border-radius:12px;background:#FFF"><img src="{{ $img }}/moh-logo.png" alt="Ministry of Health, Republic of Kenya" style="height:30px;width:auto;display:block"></span>
<p style="margin:0;color:#BFD8DA;font-size:14px">Kenya's platform for maternal, newborn and child health training, mentorship and resources.</p>
<div style="display:flex;gap:10px">@foreach ($social as $s)<a href="{{ $s['href'] }}" aria-label="{{ $s['label'] }}" style="display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:10px;background:rgba(255,255,255,.08);color:#BFD8DA;text-decoration:none"><i class="{{ $s['icon'] }}" aria-hidden="true"></i></a>@endforeach</div>
</div>
<nav aria-label="Quick links" style="display:flex;flex-direction:column;gap:10px"><span class="eyebrow" style="color:#7FDCE6">Quick links</span>
@foreach ($quickLinks as $l)<a class="flink" href="{{ $l['href'] }}" @if (!empty($l['download'])) download @endif>{{ $l['label'] }}@if (!empty($l['badge'])) <span style="display:inline-block;margin-left:4px;padding:1px 7px;border-radius:999px;background:#10B981;color:#FFF;font-size:10px;font-weight:700;letter-spacing:.04em;text-transform:uppercase">{{ $l['badge'] }}</span>@endif</a>@endforeach
</nav>
<nav aria-label="Top categories" style="display:flex;flex-direction:column;gap:10px"><span class="eyebrow" style="color:#7FDCE6">Top categories</span>
@foreach ($topCategories as $c)<a class="flink" href="{{ $c['href'] }}">{{ $c['name'] }} <span style="color:#8FB3B6;font-size:12px">({{ $c['count'] }})</span></a>@endforeach
</nav>
<div style="display:flex;flex-direction:column;gap:10px"><span class="eyebrow" style="color:#7FDCE6">Contact</span><a class="flink" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>@if ($supportPhone)<a class="flink" href="tel:{{ preg_replace('/[^+\d]/', '', $supportPhone) }}">{{ $supportPhone }}</a>@endif<span style="color:#BFD8DA;font-size:14px">Nairobi, Kenya</span></div>
</div>
<div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:48px;padding-top:22px;border-top:1px solid rgba(255,255,255,.1);color:#8FB3B6;font-size:13px"><span>© {{ $year }} {{ config('app.name') }}. All rights reserved.</span><span>Strengthening healthcare in Kenya</span></div>
</div>
</footer>
@include('partials.idle-timeout')
@stack('scripts')
</body>
</html>

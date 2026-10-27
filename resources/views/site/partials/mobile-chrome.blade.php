{{--
  App-like shell for phones: slide-in drawer menu, bottom tab bar and an "Install app" prompt.
  Needs Alpine on <body x-data="{ menu: false }"> and the variables of SiteChrome ($page, $loginUrl, $dashboardUrl, $quickLinks).
  Desktop (> 860px) never shows any of it.
--}}
<style>
.app-ic{display:none}
.mbar{display:none;align-items:center;gap:4px}
.mbtn{display:inline-flex;align-items:center;justify-content:center;width:46px;height:46px;border-radius:14px;border:0;background:transparent;color:var(--ink,#0F2A2E);cursor:pointer;text-decoration:none}
.mbtn:active{background:#E3F5F7}
.tabbar{display:none}
.drawer{position:fixed;inset:0;z-index:80;visibility:hidden;pointer-events:none;transition:visibility 0s linear .28s}
.drawer.open{visibility:visible;pointer-events:auto;transition-delay:0s}
.drawer-bd{position:absolute;inset:0;background:rgba(6,34,42,.55);opacity:0;transition:opacity .28s ease;-webkit-backdrop-filter:blur(2px);backdrop-filter:blur(2px)}
.drawer.open .drawer-bd{opacity:1}
.drawer-pn{position:absolute;top:0;right:0;bottom:0;width:min(88vw,360px);display:flex;flex-direction:column;background:#fff;transform:translateX(102%);transition:transform .3s cubic-bezier(.2,.75,.25,1);box-shadow:-20px 0 50px -20px rgba(0,0,0,.4);padding-top:env(safe-area-inset-top);padding-bottom:env(safe-area-inset-bottom)}
.drawer.open .drawer-pn{transform:none}
.dlink{display:flex;align-items:center;gap:14px;min-height:52px;padding:0 16px;border-radius:14px;color:#0F2A2E;text-decoration:none;font-weight:600;font-size:16px}
.dlink:active,.dlink:hover{background:#EEF5F5}
.dlink.on{background:#E3F5F7;color:#00363B}
.dlink svg{flex:none;color:#00838F}
.dsub{display:block;padding:14px 16px 6px;font-size:11.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#556B6E}
.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.btn-lg{min-height:48px;padding:0 22px;font-size:15px}
.install-only{display:none}
.can-install .install-only{display:block}
.ios-hint{display:none;margin:8px 16px 0;padding:12px 14px;border-radius:12px;background:#F2F8F7;color:#3D5558;font-size:13.5px;line-height:1.5}
.ios-install:not(.is-standalone) .ios-hint{display:block}
.is-standalone .install-only,.is-standalone .ios-hint{display:none!important}
@media (max-width:860px){
  .hide-md{display:none!important}
  .hdr{min-height:60px!important;flex-wrap:nowrap!important}
  .hdr > a{min-width:0}
  .hdr .disp{white-space:nowrap;font-size:17px!important}
  .hdr .moh{display:none}
  .hdr .app-ic{display:block!important;width:38px;height:38px;border-radius:11px}
  body{padding-bottom:calc(66px + env(safe-area-inset-bottom))}
  .mbar{display:flex}
  .tabbar{position:fixed;left:0;right:0;bottom:0;z-index:60;display:grid;grid-template-columns:repeat(5,1fr);padding:6px 6px calc(6px + env(safe-area-inset-bottom));background:rgba(255,255,255,.97);-webkit-backdrop-filter:saturate(1.4) blur(10px);backdrop-filter:saturate(1.4) blur(10px);border-top:1px solid #E1E9EA;box-shadow:0 -10px 30px -22px rgba(15,42,46,.5)}
  .tab-i{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;min-height:52px;border:0;background:transparent;border-radius:12px;color:#556B6E;font:inherit;font-size:11px;font-weight:700;text-decoration:none;cursor:pointer}
  .tab-i svg{width:23px;height:23px}
  .tab-i.on{color:#00363B}
  .tab-i.on svg{stroke:#00838F;stroke-width:2.2}
  .tab-i.on span{color:#00363B}
  .tab-i:active{background:#E3F5F7}
}
@media (min-width:861px){.mbar,.tabbar,.drawer{display:none!important}}
.is-standalone header{padding-top:env(safe-area-inset-top)}
@media (display-mode: standalone){.is-standalone-only{display:block}}
</style>

<nav class="tabbar" aria-label="Main">
@foreach ([['home', 'Home', route('home'), 'home'], ['resources', 'Resources', route('resources.index'), 'book'], ['categories', 'Topics', route('categories.index'), 'grid'], ['dashboards', 'Dashboards', $dashboardUrl, 'gauge']] as [$key, $label, $href, $ic])
<a @class(['tab-i', 'on' => ($page ?? '') === $key]) href="{{ $href }}" @if (($page ?? '') === $key) aria-current="page" @endif>@include('site.partials.icon', ['name' => $ic, 'size' => 23])<span>{{ $label }}</span></a>
@endforeach
@auth
<button type="button" class="tab-i" @click="menu = true">@include('site.partials.icon', ['name' => 'user', 'size' => 23])<span>Account</span></button>
@else
<a class="tab-i" href="{{ $loginUrl }}">@include('site.partials.icon', ['name' => 'login', 'size' => 23])<span>Sign in</span></a>
@endauth
</nav>

<div class="drawer" :class="menu && 'open'" :aria-hidden="!menu" @keydown.escape.window="menu = false" x-effect="document.documentElement.style.overflow = menu ? 'hidden' : ''">
<div class="drawer-bd" @click="menu = false"></div>
<aside class="drawer-pn" role="dialog" aria-modal="true" aria-label="Menu">
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 12px 10px 18px;border-bottom:1px solid #E6EEEF">
<span style="display:flex;flex-direction:column;line-height:1.1"><b class="disp" style="font-size:18px">MNCH Kenya</b><span class="muted" style="font-size:12px;font-weight:600">Training &amp; mentorship hub</span></span>
<button type="button" class="mbtn" @click="menu = false" aria-label="Close menu">@include('site.partials.icon', ['name' => 'x', 'size' => 22])</button>
</div>
<div style="flex:1;overflow-y:auto;padding:10px 10px 18px;-webkit-overflow-scrolling:touch">
<form action="{{ route('resources.index') }}" method="get" role="search" style="position:relative;margin:4px 6px 10px">
<label class="sr" for="drawer-q">Search resources</label>
@include('site.partials.icon', ['name' => 'search', 'size' => 18, 'stroke' => '#556B6E', 'style' => 'position:absolute;left:14px;top:15px'])
<input id="drawer-q" name="q" type="search" placeholder="Search guidelines and manuals" style="width:100%;height:48px;border:1px solid #D3DFE0;border-radius:14px;background:#F6FAFA;padding:0 14px 0 42px;font:inherit;font-size:16px;color:#0F2A2E">
</form>
<a class="dlink {{ ($page ?? '') === 'home' ? 'on' : '' }}" href="{{ route('home') }}">@include('site.partials.icon', ['name' => 'home', 'size' => 22])Home</a>
<a class="dlink {{ ($page ?? '') === 'resources' ? 'on' : '' }}" href="{{ route('resources.index') }}">@include('site.partials.icon', ['name' => 'book', 'size' => 22])Resources</a>
<a class="dlink {{ ($page ?? '') === 'categories' ? 'on' : '' }}" href="{{ route('categories.index') }}">@include('site.partials.icon', ['name' => 'grid', 'size' => 22])Categories</a>
<a class="dlink" href="{{ $dashboardUrl }}">@include('site.partials.icon', ['name' => 'gauge', 'size' => 22])Dashboards</a>
<a class="dlink" href="{{ route('resources.browse') }}">@include('site.partials.icon', ['name' => 'layers', 'size' => 22])Browse all</a>
<span class="dsub">Guides</span>
<a class="dlink" href="{{ route('guides.mentorship') }}">@include('site.partials.icon', ['name' => 'clipboard', 'size' => 22])Mentorship SOP guide</a>
<a class="dlink" href="{{ route('guides.emonc') }}">@include('site.partials.icon', ['name' => 'cap', 'size' => 22])EmONC mentor &amp; mentee guide</a>
<a class="dlink" href="{{ route('manual') }}">@include('site.partials.icon', ['name' => 'book', 'size' => 22])User manual</a>
<span class="dsub">Account</span>
@auth
<div style="padding:4px 16px 8px"><b style="display:block">{{ auth()->user()->full_name ?? auth()->user()->name }}</b><span class="muted" style="font-size:13px;word-break:break-all">{{ auth()->user()->email }}</span></div>
<a class="dlink" href="{{ url('/admin') }}">@include('site.partials.icon', ['name' => 'gauge', 'size' => 22])Admin panel</a>
<a class="dlink" href="{{ route('resources.bookmarks') }}">@include('site.partials.icon', ['name' => 'bookmark', 'size' => 22])Bookmarks</a>
<a class="dlink" href="{{ route('resources.downloads') }}">@include('site.partials.icon', ['name' => 'download', 'size' => 22])Downloads</a>
<form method="POST" action="{{ url('admin/logout') }}" style="margin:0">@csrf<button type="submit" class="dlink" style="width:100%;border:0;background:none;font:inherit;font-weight:600;font-size:16px;color:#B23149;cursor:pointer;text-align:left">@include('site.partials.icon', ['name' => 'login', 'size' => 22, 'stroke' => '#B23149'])Sign out</button></form>
@else
<a class="dlink" href="{{ $loginUrl }}">@include('site.partials.icon', ['name' => 'login', 'size' => 22])Sign in</a>
<a class="dlink" href="{{ url('admin/register') }}">@include('site.partials.icon', ['name' => 'user', 'size' => 22])Create account</a>
@endauth
<div class="install-only" style="padding:14px 6px 0"><button type="button" class="btn btn-pri btn-lg" data-install style="width:100%">@include('site.partials.icon', ['name' => 'phone-down', 'size' => 20])Install the app</button></div>
<p class="ios-hint"><b>Install on iPhone:</b> tap the Share button in Safari, then <b>Add to Home Screen</b>.</p>
</div>
</aside>
</div>

<script>
(function () {
    var root = document.documentElement, deferred = null;
    var standalone = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
    if (standalone) root.classList.add('is-standalone');
    if (/iphone|ipad|ipod/i.test(navigator.userAgent) && !standalone) root.classList.add('ios-install');
    window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; root.classList.add('can-install'); });
    window.addEventListener('appinstalled', function () { root.classList.remove('can-install'); deferred = null; });
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-install]');
        if (!b || !deferred) return;
        deferred.prompt();
        deferred.userChoice.finally(function () { deferred = null; root.classList.remove('can-install'); });
    });
    if ('serviceWorker' in navigator && location.protocol === 'https:') {
        window.addEventListener('load', function () { navigator.serviceWorker.register('/sw-site.js', { scope: '/' }).catch(function () {}); });
    }
})();
</script>

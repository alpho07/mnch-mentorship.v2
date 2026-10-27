@extends('site.layout')
@section('title', 'Categories')
@section('body-bg', '#F6F9F9')

@push('styles')
.ccard{position:relative;display:flex;flex-direction:column;background:#FFF;border:1px solid #E1E9EA;border-radius:22px;overflow:hidden;transition:transform .25s,box-shadow .25s}
.ccard:hover{transform:translateY(-4px);box-shadow:0 26px 44px -28px rgba(15,42,46,.45)}
.go{display:inline-flex;align-items:center;gap:8px;font-weight:700;font-size:14.5px;text-decoration:none}
.go svg{transition:transform .2s}
.go:hover svg{transform:translateX(3px)}
.srow{display:grid;grid-template-columns:minmax(0,1fr) 72px 26px;gap:12px;align-items:center;min-height:30px;font-size:14px;text-decoration:none;border-radius:8px}
a.srow:hover span:first-child{text-decoration:underline}
.tog{all:unset;box-sizing:border-box;display:inline-flex;align-items:center;gap:10px;min-height:44px;padding:0 6px;cursor:pointer;font-size:14px;font-weight:600;color:#2E4A4E}
.tog:focus-visible{outline:2px solid #0097A7;border-radius:8px}
.sw{position:relative;width:44px;height:26px;border-radius:99px;background:#C9D5D7;transition:background .2s}
.sw i{position:absolute;top:3px;left:3px;width:20px;height:20px;border-radius:50%;background:#FFF;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:left .2s}
.tog[aria-checked="true"] .sw{background:#00838F}
.tog[aria-checked="true"] .sw i{left:21px}
@endpush


@section('content')
@php($catIndex = array_map(fn ($c) => ['s' => $c['search'], 'subs' => array_column($c['subs'], 'name')], array_merge($featured, $rest)))
<div x-data="{ q: '', hideEmpty: false, cats: @js($catIndex),
    get none() { return this.q.trim() !== '' && !this.cats.some(c => this.showCat(c.s, c.subs)) },
    has(text) { return !this.q || text.includes(this.q.trim().toLowerCase()) },
    showSub(cat, name, empty) { return (!this.hideEmpty || !empty) && (this.has(cat) || this.has(name.toLowerCase())) },
    showCat(cat, subs) { return this.has(cat) || subs.some(s => this.has(s.toLowerCase())) } }">
<section style="position:relative;overflow:hidden;background:var(--bg-mint);border-bottom:1px solid #E1ECEC">
<div aria-hidden="true" style="position:absolute;inset:0;background-image:radial-gradient(rgba(0,131,143,.12) 1px,transparent 1.4px);background-size:24px 24px"></div>
<div class="wrap" style="position:relative;padding-top:28px;padding-bottom:44px">
<nav aria-label="Breadcrumb" style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted)"><a href="{{ route('home') }}" style="color:var(--muted);text-decoration:none">Home</a>@include('site.partials.icon', ['name' => 'chevron-right', 'size' => 14])<span style="color:var(--ink);font-weight:600" aria-current="page">Categories</span></nav>
<div class="rise" style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:28px;margin-top:26px">
<div style="flex:1 1 560px;min-width:0">
<div class="eyebrow" style="color:#0089A0">Browse by clinical area</div>
<h1 class="disp" style="margin:10px 0 0;font-size:clamp(34px,4.2vw,52px);line-height:1.04;font-weight:800;color:var(--navy);text-wrap:balance">Every guideline, sorted by topic</h1>
<p class="muted" style="margin:14px 0 0;font-size:17px;max-width:600px">Pick a clinical area to see its manuals, protocols and training slides — counts include everything filed under its subtopics.</p>
</div>
<div style="display:flex;gap:12px;flex-wrap:wrap">
@foreach ($stats as [$v, $l])<div style="background:#FFF;border:1px solid #DCE9EA;border-radius:16px;padding:14px 18px;min-width:120px"><div class="disp num" style="font-size:30px;font-weight:800;line-height:1;color:var(--navy)">{{ $v }}</div><div class="muted" style="font-size:13px;margin-top:4px">{{ $l }}</div></div>@endforeach
</div>
</div>
<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:28px" x-cloak>
<label style="position:relative;flex:0 1 420px;display:block"><span class="sr">Filter topics</span>@include('site.partials.icon', ['name' => 'search', 'stroke' => '#556B6E', 'style' => 'position:absolute;left:14px;top:15px'])<input type="search" x-model="q" placeholder="Filter topics, e.g. preterm, diabetes" style="width:100%;height:48px;border:1px solid var(--line-2);border-radius:14px;background:#FFF;padding:0 14px 0 42px;font:inherit;font-size:15px;color:var(--ink)"></label>
<button type="button" class="tog" role="switch" aria-checked="false" :aria-checked="hideEmpty.toString()" @click="hideEmpty = !hideEmpty"><span class="sw"><i></i></span>Hide empty subtopics</button>
</div>
</div>
</section>

<div class="wrap" style="padding-top:36px;padding-bottom:72px;display:flex;flex-direction:column;gap:20px">

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(520px,100%),1fr));gap:20px">
@foreach ($featured as $c)
<article id="{{ $c['slug'] }}" class="ccard rise" style="flex-direction:row;flex-wrap:wrap;animation-delay:{{ $c['delay'] }}" x-show="showCat(@js($c['search']), @js(array_column($c['subs'], 'name')))">
<div style="position:relative;flex:1 1 200px;min-height:240px;background:{{ $c['bg'] }}">
<img src="{{ $c['img'] }}" alt="{{ $c['alt'] }}" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:{{ $c['pos'] }}">
<span class="chip" style="position:absolute;left:14px;top:14px;background:#FFF;color:{{ $c['ink'] }}"><span class="num">{{ $c['total'] }}</span> {{ $c['unit'] }}</span>
</div>
<div style="flex:1.4 1 280px;min-width:0;padding:24px;display:flex;flex-direction:column;gap:14px">
<div style="display:flex;align-items:center;gap:12px"><span style="width:44px;height:44px;border-radius:13px;background:{{ $c['bg'] }};color:{{ $c['c'] }};display:flex;align-items:center;justify-content:center;flex:none">@include('site.partials.icon', ['name' => $c['icon'], 'size' => 22])</span><div><h2 class="disp" style="margin:0;font-size:24px;font-weight:700">{{ $c['name'] }}</h2><div class="muted" style="font-size:13px">{{ $c['meta'] }}</div></div></div>
<p class="muted" style="margin:0;font-size:14.5px">{{ $c['desc'] }}</p>
@include('site.partials.subtopics', ['c' => $c])
<a class="go" href="{{ $c['href'] }}" style="color:{{ $c['ink'] }};margin-top:auto">Browse {{ $c['total'] }} {{ $c['unit'] }} @include('site.partials.icon', ['name' => 'arrow-right', 'size' => 16])</a>
</div>
</article>
@endforeach
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(360px,100%),1fr));gap:20px">
@foreach ($rest as $c)
<article id="{{ $c['slug'] }}" class="ccard rise" style="animation-delay:{{ $c['delay'] }}" x-show="showCat(@js($c['search']), @js(array_column($c['subs'], 'name')))">
<div style="position:relative;padding:22px 22px 18px;background:{{ $c['bg'] }};overflow:hidden">
<svg viewBox="0 0 120 120" fill="none" stroke="{{ $c['c'] }}" aria-hidden="true" style="position:absolute;right:-28px;top:-28px;width:140px;height:140px;opacity:.16"><circle cx="60" cy="60" r="56" stroke-width="2"/><circle cx="60" cy="60" r="40" stroke-width="2"/><circle cx="60" cy="60" r="24" stroke-width="2"/></svg>
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
<span style="width:46px;height:46px;border-radius:14px;background:#FFF;color:{{ $c['c'] }};display:flex;align-items:center;justify-content:center">@include('site.partials.icon', ['name' => $c['icon'], 'size' => 22])</span>
<span style="text-align:right"><span class="disp num" style="display:block;font-size:30px;font-weight:800;line-height:1;color:{{ $c['ink'] }}">{{ $c['total'] }}</span><span style="font-size:12px;font-weight:600;color:{{ $c['ink'] }}">{{ $c['unit'] }}</span></span>
</div>
<h2 class="disp" style="margin:16px 0 0;font-size:21px;font-weight:700">{{ $c['name'] }}</h2>
</div>
<div style="padding:18px 22px 22px;display:flex;flex-direction:column;gap:14px;flex:1">
<p class="muted" style="margin:0;font-size:14px">{{ $c['desc'] }}</p>
@if (count($c['subs']))
<div style="padding-top:12px;border-top:1px solid #EEF3F3">@include('site.partials.subtopics', ['c' => $c])</div>
@endif
@if ($c['total'] === 0)
<div style="border:1px dashed #C9D8DA;border-radius:14px;padding:14px;font-size:13.5px;color:var(--ink-3)">No resources here yet. <a href="mailto:{{ $contactEmail }}" style="font-weight:700">Suggest one</a></div>
@else
<a class="go" href="{{ $c['href'] }}" style="color:{{ $c['ink'] }};margin-top:auto">Browse {{ $c['total'] }} {{ $c['unit'] }} @include('site.partials.icon', ['name' => 'arrow-right', 'size' => 16])</a>
@endif
</div>
</article>
@endforeach
</div>

<div x-show="none" style="display:none;background:#FFF;border:1px dashed #C9D8DA;border-radius:18px;padding:48px 24px;text-align:center"><div class="disp" style="font-size:20px;font-weight:700">No topic matches “<span x-text="q"></span>”</div><p class="muted" style="margin:6px 0 0">Try a broader word, or <a :href="@js(route('resources.index')) + '?q=' + encodeURIComponent(q)" href="{{ route('resources.index') }}">search all resources</a>.</p></div>
</div>
</div>
@endsection

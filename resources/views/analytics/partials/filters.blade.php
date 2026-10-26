{{--
  GET filter form. Dropdowns submit on change; Enter in the search box submits.
  $fields: list of field keys to show: programme, year, county, subcounty, facility, cadre, level, type, search
  $searchPlaceholder, $scopeChips: list of [text, bg, ink]
--}}
@php($o = $options)
<section class="card rise" aria-label="Filters" style="padding:20px 24px 18px;animation-delay:.1s">
<form method="GET" action="{{ strtok($resetHref, '?') }}">
<input type="hidden" name="mode" value="{{ $f->mode }}">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(180px,100%),1fr));gap:14px 16px">
@foreach ($fields as $field)
@switch($field)
@case('programme')
@include('analytics.partials.select', ['name' => 'programme', 'label' => 'Programme', 'options' => $o['programmes'], 'selected' => $f->programme, 'all' => 'All programmes'])
@break
@case('year')
@include('analytics.partials.select', ['name' => 'year', 'label' => 'Year', 'options' => array_combine($o['years'], $o['years']), 'selected' => $f->year, 'all' => 'All years'])
@break
@case('county')
@include('analytics.partials.select', ['name' => 'county', 'label' => 'County', 'options' => array_combine($o['counties'], $o['counties']), 'selected' => $f->county, 'all' => 'All counties'])
@break
@case('subcounty')
@include('analytics.partials.select', ['name' => 'subcounty', 'label' => 'Subcounty', 'options' => array_combine($o['subcounties'], $o['subcounties']), 'selected' => $f->subcounty, 'all' => $f->county ? 'All subcounties' : 'Choose a county first', 'disabled' => ! $f->county])
@break
@case('facility')
@include('analytics.partials.select', ['name' => 'facility', 'label' => 'Facility', 'options' => array_combine($o['facilities'], $o['facilities']), 'selected' => $f->facility, 'all' => $f->county ? 'All facilities' : 'Choose a county first', 'disabled' => ! $f->county])
@break
@case('cadre')
@include('analytics.partials.select', ['name' => 'cadre', 'label' => 'Cadre', 'options' => array_combine($o['cadres'], $o['cadres']), 'selected' => $f->cadre, 'all' => 'All cadres'])
@break
@case('level')
@include('analytics.partials.select', ['name' => 'level', 'label' => 'CPD level', 'options' => ['AP' => 'Advanced Practitioner', 'P' => 'Practitioner', 'F' => 'Foundation'], 'selected' => $f->level, 'all' => 'All levels'])
@break
@case('type')
@include('analytics.partials.select', ['name' => 'type', 'label' => 'Assessment type', 'options' => ['baseline' => 'Baseline', 'midline' => 'Midline', 'endline' => 'Endline'], 'selected' => $f->assessmentType, 'all' => 'All types'])
@break
@case('search')
<div class="fld"><label for="f-q">{{ $searchLabel ?? 'Search' }}</label><div class="selw">@include('analytics.partials.icon', ['name' => 'search', 'size' => 16, 'sw' => 2, 'style' => 'position:absolute;left:12px;top:14px;color:#556B6E'])<input class="inp" id="f-q" name="q" type="search" value="{{ $f->search }}" placeholder="{{ $searchPlaceholder ?? 'Search' }}"></div></div>
@break
@endswitch
@endforeach
</div>
<button type="submit" class="sr-only">Apply filters</button>
<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:16px;padding-top:14px;border-top:1px dashed #DCE6E7">
<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
<span class="muted" style="font-size:13px">{{ $scopeIntro ?? 'Showing' }}</span>
@foreach ($scopeChips as [$text, $bg, $ink])<span class="chip" style="background:{{ $bg }};color:{{ $ink }}">{{ $text }}</span>@endforeach
</div>
<a class="btn btn-ghost" href="{{ $resetHref }}" style="min-height:40px">@include('analytics.partials.icon', ['name' => 'reset', 'size' => 15, 'sw' => 2])Reset filters</a>
</div>
</form>
</section>

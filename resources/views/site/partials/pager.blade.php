@if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $paginator->hasPages())
@php
    $cur = $paginator->currentPage(); $last = $paginator->lastPage();
    $pages = collect([1, $last])->merge(range(max(1, $cur - 2), min($last, $cur + 2)))->unique()->sort()->values();
@endphp
<nav aria-label="Pagination" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:28px">
<span class="muted num" style="font-size:13.5px">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
<a class="pg {{ $paginator->onFirstPage() ? 'off' : '' }}" href="{{ $paginator->previousPageUrl() ?? '#' }}" aria-label="Previous page" @if ($paginator->onFirstPage()) aria-disabled="true" tabindex="-1" @endif>@include('site.partials.icon', ['name' => 'chevron-left', 'size' => 16])</a>
@php $prev = 0; @endphp
@foreach ($pages as $p)
@if ($p - $prev > 1)<span class="muted" aria-hidden="true">…</span>@endif
<a class="pg num {{ $p === $cur ? 'on' : '' }}" href="{{ $paginator->url($p) }}" @if ($p === $cur) aria-current="page" @endif>{{ $p }}</a>
@php $prev = $p; @endphp
@endforeach
<a class="pg {{ $paginator->hasMorePages() ? '' : 'off' }}" href="{{ $paginator->nextPageUrl() ?? '#' }}" aria-label="Next page" @unless ($paginator->hasMorePages()) aria-disabled="true" tabindex="-1" @endunless>@include('site.partials.icon', ['name' => 'chevron-right', 'size' => 16])</a>
</div>
</nav>
@endif

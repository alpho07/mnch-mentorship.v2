@php
    $statePath = $field->getStatePath();
    $programs  = $field->getPrograms();

    /**
     * Manual-inspired themes, matched by program name keywords.
     * Infant & Child: lime green from the actual MoH manual cover.
     * Newborn Care:   teal/cyan — standard neonatal/newborn palette in MoH materials.
     * Fallbacks for future programs.
     */
    $fallbacks = [
        ['from'=>'#C2185B','to'=>'#E91E63','shadow'=>'rgba(194,24,91,.35)','emoji'=>'❤️','tag'=>'Maternal Health'],
        ['from'=>'#E65100','to'=>'#EF8C00','shadow'=>'rgba(230,81,0,.35)',  'emoji'=>'🌿','tag'=>'Nutrition'],
        ['from'=>'#5E35B1','to'=>'#9C27B0','shadow'=>'rgba(94,53,177,.35)', 'emoji'=>'🧬','tag'=>'Programme'],
        ['from'=>'#00838F','to'=>'#26C6DA','shadow'=>'rgba(0,131,143,.35)', 'emoji'=>'🩺','tag'=>'Clinical'],
    ];

    $pgTheme = function(string $name, int $i) use ($fallbacks): array {
        $n = strtolower($name);
        if (str_contains($n,'infant') || str_contains($n,'child'))
            return ['from'=>'#7DB83A','to'=>'#A8D84E','shadow'=>'rgba(125,184,58,.40)','emoji'=>'👶','tag'=>'Paediatric Care'];
        if (str_contains($n,'newborn') || str_contains($n,'neonat'))
            return ['from'=>'#A855C8','to'=>'#CF8FE0','shadow'=>'rgba(168,85,200,.40)','emoji'=>'🤱','tag'=>'Neonatal Care'];
        if (str_contains($n,'maternal') || str_contains($n,'mother') || str_contains($n,'safe'))
            return ['from'=>'#B5006C','to'=>'#E91E8C','shadow'=>'rgba(181,0,108,.40)','emoji'=>'❤️','tag'=>'Maternal Health'];
        if (str_contains($n,'nutrition') || str_contains($n,'feeding'))
            return ['from'=>'#E05A00','to'=>'#FF8C00','shadow'=>'rgba(224,90,0,.40)',  'emoji'=>'🌿','tag'=>'Nutrition'];
        return $fallbacks[$i % count($fallbacks)];
    };
@endphp

@php $hasError = $errors->has($statePath); @endphp

<div
    x-data="{
        selected: $wire.entangle('{{ $statePath }}').live,
        pick(id) { this.selected = id; }
    }"
    class="pgpicker {{ $hasError ? 'pgpicker--error' : '' }}"
>
    <div class="pgpicker-grid">
        @forelse($programs as $i => $prog)
        @php
            $theme    = $pgTheme($prog->name, $i);
            $modules  = $prog->programModules;
            $modCount = $modules->count();
        @endphp

        <div
            class="pgpicker-card {{ $prog->canSelect ? '' : 'pgpicker-card--disabled' }}"
            :class="{ 'pgpicker-card--on': selected == {{ $prog->id }} }"
            style="--grad-from:{{ $theme['from'] }};--grad-to:{{ $theme['to'] }};--card-shadow:{{ $theme['shadow'] }}"
            role="radio"
            :aria-checked="selected == {{ $prog->id }}"
            @if($prog->canSelect)
                @click="pick({{ $prog->id }})"
                tabindex="0"
                @keydown.space.prevent="pick({{ $prog->id }})"
                @keydown.enter.prevent="pick({{ $prog->id }})"
            @else
                aria-disabled="true"
                tabindex="-1"
                title="This program is not active"
            @endif
        >
            {{-- Dot-pattern watermark (manual cover motif) --}}
            <div class="pgcover-dots" aria-hidden="true"></div>
            {{-- Radial glow circle --}}
            <div class="pgcover-glow" aria-hidden="true"></div>

            @unless($prog->canSelect)
                <div class="pgpicker-inactive-ribbon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="10" rx="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Not Active
                </div>
            @endunless

            {{-- Top row: MoH badge + checkmark --}}
            <div class="pgcover-toprow">
                <div class="pgcover-moh">
                    <svg class="pgcover-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L3 7v5c0 5.25 3.75 10.15 9 11.35C17.25 22.15 21 17.25 21 12V7L12 2z"/>
                    </svg>
                    <span>MoH Kenya</span>
                </div>
                <div class="pgpicker-tick" :class="{'pgpicker-tick--on': selected == {{ $prog->id }}}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
            </div>

            {{-- Large emoji icon (cover centrepiece) --}}
            <div class="pgcover-icon" aria-hidden="true">{{ $theme['emoji'] }}</div>

            {{-- Programme tag / type --}}
            <div class="pgcover-tag">{{ $theme['tag'] }}</div>

            {{-- Title (bold, uppercase like the manual) --}}
            <div class="pgcover-title">{{ $prog->name }}</div>

            {{-- Description --}}
            @if(filled($prog->description))
                <p class="pgcover-desc">"{{ $prog->description }}"</p>
            @endif

            {{-- Footer: module popover + mentor's manual label --}}
            <div class="pgcover-footer">
                <div
                    class="pgpicker-modules-wrap"
                    x-data="{
                        open: false, _t: null,
                        show(){ clearTimeout(this._t); this.open=true; },
                        hide(){ this._t=setTimeout(()=>{ this.open=false; },120); }
                    }"
                    @mouseenter="show()" @mouseleave="hide()" @click.stop
                >
                    <span class="pgcover-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                        </svg>
                        {{ $modCount }} {{ \Illuminate\Support\Str::plural('module', $modCount) }}
                    </span>

                    {{-- Module list popover --}}
                    <div
                        x-show="open"
                        x-transition:enter="pgpop-t"
                        x-transition:enter-start="pgpop-t--out"
                        x-transition:enter-end="pgpop-t--in"
                        x-transition:leave="pgpop-t"
                        x-transition:leave-start="pgpop-t--in"
                        x-transition:leave-end="pgpop-t--out"
                        class="pgpicker-popover"
                        style="display:none"
                        @mouseenter="show()" @mouseleave="hide()"
                    >
                        <div class="pgpicker-popover-title"
                             style="background:{{ $theme['from'] }}22;border-color:{{ $theme['from'] }}44">
                            <svg viewBox="0 0 24 24" fill="none" stroke="{{ $theme['from'] }}"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                            </svg>
                            <span style="color:{{ $theme['from'] }}">Curriculum ({{ $modCount }})</span>
                        </div>
                        <ul class="pgpicker-module-list">
                            @foreach($modules as $idx => $mod)
                            <li class="pgpicker-module-item">
                                <span class="pgpicker-module-num"
                                      style="background:{{ $theme['from'] }}1a;color:{{ $theme['from'] }}">
                                    {{ $idx + 1 }}
                                </span>
                                {{ $mod->name }}
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

            </div>
        </div>
        @empty
        <div class="pgpicker-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            No mentorship programs are configured yet.
        </div>
        @endforelse
    </div>

    {{-- Validation error banner --}}
    @if($hasError)
    <div class="pgpicker-error-banner" role="alert">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        {{ $errors->first($statePath) }}
    </div>
    @endif
</div>

@once
@push('styles')
<style>
/* ═══════════════════════════════════════════════════════════════════
   Program Picker — manual-cover card design
   ═══════════════════════════════════════════════════════════════════ */

.pgpicker { width: 100%; }

/* ── Error state ────────────────────────────────────────────────── */
.pgpicker--error .pgpicker-grid {
    border-radius: 18px;
    outline: 2px solid #ef4444;
    outline-offset: 4px;
    animation: pgpicker-shake .35s ease;
}
@keyframes pgpicker-shake {
    0%,100% { transform: translateX(0); }
    20%      { transform: translateX(-5px); }
    40%      { transform: translateX( 5px); }
    60%      { transform: translateX(-3px); }
    80%      { transform: translateX( 3px); }
}
.pgpicker-error-banner {
    display: flex;
    align-items: center;
    gap: .35rem;
    margin-top: .35rem;
    font-size: .75rem;
    font-weight: 500;
    color: #dc2626;
    line-height: 1.4;
}
.pgpicker-error-banner svg {
    width: 12px; height: 12px;
    flex-shrink: 0;
    stroke: #dc2626;
}
.dark .pgpicker-error-banner { color: #fca5a5; }
.dark .pgpicker-error-banner svg { stroke: #fca5a5; }

.pgpicker-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 1.25rem;
    padding: .25rem .25rem 2rem;
}

/* ── Card base ──────────────────────────────────────────────────── */
.pgpicker-card {
    position: relative;
    background: linear-gradient(150deg, var(--grad-from) 0%, var(--grad-to) 100%);
    border: 2.5px solid transparent;
    border-radius: 16px;
    padding: 1.1rem 1rem 1rem;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 0;
    overflow: hidden;
    min-height: 280px;
    transition:
        transform      .28s cubic-bezier(.34,1.45,.64,1),
        box-shadow     .22s ease,
        border-color   .2s ease;
    box-shadow: 0 3px 12px rgba(0,0,0,.18);
    outline: none;
    z-index: 1;
    color: #fff;
    user-select: none;
}
.pgpicker-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 28px var(--card-shadow), 0 3px 10px rgba(0,0,0,.14);
    border-color: rgba(255,255,255,.45);
    z-index: 10;
}
.pgpicker-card--on {
    transform: translateY(-12px) !important;
    box-shadow: 0 18px 40px var(--card-shadow), 0 6px 16px rgba(0,0,0,.2) !important;
    border-color: rgba(255,255,255,.85) !important;
    z-index: 10;
}

/* ── Dot-pattern watermark (circular halftone like the manual) ──── */
.pgcover-dots {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(circle, rgba(255,255,255,.18) 1.2px, transparent 1.2px);
    background-size: 9px 9px;
    mask-image: radial-gradient(ellipse 75% 70% at 55% 80%, black 0%, transparent 70%);
    -webkit-mask-image: radial-gradient(ellipse 75% 70% at 55% 80%, black 0%, transparent 70%);
    pointer-events: none;
}

/* ── Radial glow circle (lighter circle behind the title, like cover) */
.pgcover-glow {
    position: absolute;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,.13) 0%, transparent 70%);
    left: 50%;
    bottom: 30%;
    transform: translateX(-50%);
    pointer-events: none;
}

/* ── Disabled (inactive program) state ─────────────────────────── */
.pgpicker-card--disabled {
    cursor: not-allowed;
    filter: grayscale(.6) brightness(.9);
    opacity: .75;
}
.pgpicker-card--disabled:hover {
    transform: none;
    box-shadow: 0 3px 12px rgba(0,0,0,.18);
    border-color: transparent;
}

.pgpicker-inactive-ribbon {
    position: absolute;
    top: .85rem;
    right: -2.1rem;
    transform: rotate(40deg);
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    background: #dc2626;
    color: #fff;
    font-size: .62rem;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    padding: .28rem 2.5rem;
    box-shadow: 0 2px 6px rgba(0,0,0,.25);
    z-index: 3;
    pointer-events: none;
}
.pgpicker-inactive-ribbon svg {
    width: 11px;
    height: 11px;
    flex-shrink: 0;
}

/* ── Top row ─────────────────────────────────────────────────────── */
.pgcover-toprow {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .6rem;
    position: relative;
    z-index: 2;
}

.pgcover-moh {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    background: rgba(255,255,255,.18);
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,.25);
    border-radius: 999px;
    padding: .2rem .55rem .2rem .35rem;
    font-size: .62rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: rgba(255,255,255,.92);
}
.pgcover-shield {
    width: 14px; height: 14px;
    stroke: rgba(255,255,255,.9);
    flex-shrink: 0;
}

/* ── Checkmark ──────────────────────────────────────────────────── */
.pgpicker-tick {
    width: 24px; height: 24px;
    border-radius: 50%;
    background: rgba(255,255,255,.2);
    border: 1.5px solid rgba(255,255,255,.35);
    color: rgba(255,255,255,.7);
    display: flex; align-items: center; justify-content: center;
    transition: background .2s, border-color .2s, transform .25s cubic-bezier(.34,1.56,.64,1), opacity .15s;
    transform: scale(.65);
    opacity: 0;
    flex-shrink: 0;
}
.pgpicker-tick svg { width: 12px; height: 12px; }
.pgpicker-tick--on {
    background: rgba(255,255,255,.95);
    border-color: rgba(255,255,255,1);
    color: var(--grad-from);
    transform: scale(1);
    opacity: 1;
}

/* ── Large icon ─────────────────────────────────────────────────── */
.pgcover-icon {
    font-size: 2.6rem;
    text-align: center;
    margin: .5rem 0 .3rem;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,.2));
    position: relative;
    z-index: 2;
    line-height: 1;
}

/* ── Programme tag ──────────────────────────────────────────────── */
.pgcover-tag {
    font-size: .62rem;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: rgba(255,255,255,.65);
    text-align: center;
    margin-bottom: .35rem;
    position: relative;
    z-index: 2;
}

/* ── Title ──────────────────────────────────────────────────────── */
.pgcover-title {
    font-size: 1.08rem;
    font-weight: 900;
    color: #fff;
    text-align: center;
    line-height: 1.25;
    letter-spacing: .01em;
    text-shadow: 0 1px 6px rgba(0,0,0,.25);
    margin-bottom: .5rem;
    position: relative;
    z-index: 2;
}

/* ── Description ────────────────────────────────────────────────── */
.pgcover-desc {
    font-size: .72rem;
    color: rgba(255,255,255,.75);
    text-align: center;
    line-height: 1.55;
    font-style: italic;
    margin: 0 0 .7rem;
    flex: 1;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    position: relative;
    z-index: 2;
}

/* ── Footer ─────────────────────────────────────────────────────── */
.pgcover-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: .6rem;
    border-top: 1px solid rgba(255,255,255,.2);
    margin-top: auto;
    position: relative;
    z-index: 2;
}

/* ── Module badge (with popover) ─────────────────────────────────── */
.pgpicker-modules-wrap { position: relative; display: inline-flex; }

.pgcover-badge {
    display: inline-flex; align-items: center; gap: .3rem;
    font-size: .68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em;
    color: rgba(255,255,255,.9);
    background: rgba(255,255,255,.2);
    border: 1px solid rgba(255,255,255,.3);
    padding: .22rem .65rem;
    border-radius: 999px;
    transition: background .15s, border-color .15s;
    cursor: default;
}
.pgpicker-modules-wrap:hover .pgcover-badge {
    background: rgba(255,255,255,.35);
    border-color: rgba(255,255,255,.55);
}
.pgcover-badge svg { width: 11px; height: 11px; }


/* ── Module popover ─────────────────────────────────────────────── */
.pgpicker-popover {
    position: absolute;
    bottom: calc(100% + 10px);
    left: 0;
    min-width: 220px;
    max-width: 290px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 10px 32px rgba(0,0,0,.18), 0 2px 8px rgba(0,0,0,.07);
    z-index: 50;
    overflow: hidden;
}
.pgpicker-popover-title {
    display: flex; align-items: center; gap: .4rem;
    font-size: .68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em;
    padding: .5rem .75rem;
    border-bottom: 1px solid;
}
.pgpicker-popover-title svg { width: 13px; height: 13px; flex-shrink: 0; }
.pgpicker-module-list {
    list-style: none; margin: 0; padding: .35rem 0;
    max-height: 220px; overflow-y: auto;
}
.pgpicker-module-item {
    display: flex; align-items: baseline; gap: .5rem;
    padding: .35rem .75rem;
    font-size: .78rem; color: #374151; line-height: 1.4;
}
.pgpicker-module-item:hover { background: #f9fafb; }
.pgpicker-module-num {
    flex-shrink: 0;
    width: 18px; height: 18px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 50%;
    font-size: .65rem; font-weight: 800; line-height: 1;
}

/* Popover transition */
.pgpop-t           { transition: opacity .15s ease, transform .15s ease; }
.pgpop-t--out      { opacity: 0; transform: translateY(5px); }
.pgpop-t--in       { opacity: 1; transform: translateY(0); }

/* ── Empty state ─────────────────────────────────────────────────── */
.pgpicker-empty {
    grid-column: 1/-1;
    display: flex; align-items: center; gap: .5rem;
    color: #9ca3af; font-size: .85rem;
    padding: 2rem; background: #f9fafb;
    border-radius: 12px; border: 1px dashed #d1d5db;
}
.pgpicker-empty svg { width: 18px; height: 18px; flex-shrink: 0; }

/* ── Dark mode ──────────────────────────────────────────────────── */
.dark .pgpicker-popover { background:rgb(17 24 39); border-color:rgb(55 65 81); }
.dark .pgpicker-module-item { color:#d1d5db; }
.dark .pgpicker-module-item:hover { background:rgb(31 41 55); }
.dark .pgpicker-empty { background:rgb(17 24 39); border-color:rgb(55 65 81); color:rgb(107 114 128); }
</style>
@endpush
@endonce

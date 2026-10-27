{{-- resources/views/livewire/auth/custom-login.blade.php --}}
<div
    class="fi-simple-page"
    x-data="{ crisisOpen: false }"
    @keydown.escape.window="crisisOpen = false"
>
    <div class="auth-shell">

        {{-- ═══════════════════════════════════════════════════════════════
             LEFT — Hero Panel
             ═══════════════════════════════════════════════════════════════ --}}
        <section class="auth-hero" aria-labelledby="hero-title">
            <div class="hero-bg"></div>
            <div class="hero-gradient"></div>

            <div class="hero-content">
                <div class="hero-badge">
                    <span class="hero-badge-logo-wrap"><img src="{{ asset('moh_logo.png') }}" alt="Ministry of Health — Republic of Kenya" class="hero-badge-logo"></span>
                </div>

                <div class="hero-message">
                    <p class="hero-overline">National MNCH Mentorship Platform</p>
                    <h1 id="hero-title" class="hero-title">Strengthening care<br>through mentorship</h1>
                    <p class="hero-desc">
                        Equipping healthcare workers with practical skills, structured learning and real-time
                        support to improve outcomes for mothers, newborns and children.
                    </p>
                    <div class="hero-programmes">
                        <span class="programme maternal">Maternal Health</span>
                        <span class="programme newborn">Newborn Care</span>
                        <span class="programme child">Infant &amp; Child Care</span>
                    </div>
                </div>

                <div class="impact-wrap">
                    <div class="impact-heading">
                        <span>Live nationwide impact</span>
                        <button type="button" class="why-link" @click="crisisOpen = true">Why this matters ↗</button>
                    </div>
                    <div class="impact-grid">
                        <article class="impact-card">
                            <span class="impact-icon">✦</span>
                            <div><strong>{{ number_format($platformStats['mentorships']) }}</strong><span>Mentorships</span></div>
                        </article>
                        <article class="impact-card">
                            <span class="impact-icon">◎</span>
                            <div><strong>{{ number_format($platformStats['mentees']) }}</strong><span>Mentees enrolled</span></div>
                        </article>
                        <article class="impact-card">
                            <span class="impact-icon">+</span>
                            <div><strong>{{ number_format($platformStats['facilities']) }}</strong><span>Facilities reached</span></div>
                        </article>
                        <article class="impact-card">
                            <span class="impact-icon">⌖</span>
                            <div><strong>{{ number_format($platformStats['counties']) }}</strong><span>Counties covered</span></div>
                        </article>
                    </div>
                    <p class="hero-supported">Supported by <b>Division of RMNCAH</b> · County Health Departments · Development Partners</p>
                </div>
            </div>
        </section>

        {{-- ═══════════════════════════════════════════════════════════════
             RIGHT — Login Form
             ═══════════════════════════════════════════════════════════════ --}}
        <section class="auth-right" aria-label="Sign in">
            <div class="auth-box">

                <div class="moh-logo-wrap">
                    <img src="{{ asset('moh_logo.png') }}" alt="Ministry of Health — Republic of Kenya" class="moh-logo-img">
                </div>

                <div class="auth-intro">
                    <p class="login-kicker">MNCH Mentorship Platform</p>
                    <h2 class="auth-h1">Welcome back</h2>
                    <p class="auth-sub">Sign in to manage mentorships, track participation and access your learning dashboard.</p>
                </div>

                <form wire:submit.prevent="authenticate">
                    {{ $this->form }}

                    <div class="login-footer">
                        <a href="{{ route('filament.admin.auth.password-reset.request') }}" class="forgot-link">
                            Forgot password?
                        </a>
                    </div>

                    <button type="submit" class="auth-btn" wire:loading.attr="disabled">
                        <span class="btn-idle" wire:loading.remove wire:target="authenticate">
                            Sign in
                            <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </span>
                        <span class="btn-loading" wire:loading wire:target="authenticate">
                            <svg class="spin" viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5">
                                <circle cx="12" cy="12" r="10" style="opacity:0.25"/>
                                <path d="M4 12a8 8 0 018-8" style="opacity:0.85"/>
                            </svg>
                            Signing in…
                        </span>
                    </button>

                    <div class="login-footer login-footer-center">
                        <a href="{{ route('filament.admin.auth.register') }}" class="forgot-link">
                            New to the platform? Create Account.
                        </a>
                    </div>

                    <div class="login-footer login-footer-center" style="margin-top:.75rem">
                        <a href="{{ url('/') }}" class="forgot-link">
                            <svg viewBox="0 0 24 24" style="width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;display:inline;vertical-align:middle;margin-right:3px"><polyline points="15 18 9 12 15 6"/></svg>
                            Back to home
                        </a>
                    </div>
                </form>

                <div class="secure-strip">
                    <span>✓</span>Secure access for mentors, mentees and programme administrators
                </div>
            </div>

            <details class="mobile-matters">
                <summary @click.prevent="crisisOpen = true">Why this platform matters</summary>
            </details>
        </section>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         "Why This Platform Exists" — popup, opened via the hero /
         mobile "why this matters" links
         ═══════════════════════════════════════════════════════════════ --}}
    <div
        x-show="crisisOpen"
        x-cloak
        class="crisis-modal-backdrop"
        @click.self="crisisOpen = false"
        style="display:none"
    >
        <div class="crisis-modal" role="dialog" aria-modal="true" aria-labelledby="crisis-modal-title">
            <button type="button" class="crisis-modal-close" @click="crisisOpen = false" aria-label="Close">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>

            <div class="crisis-modal-label" id="crisis-modal-title">
                <svg viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                Why This Platform Exists
            </div>

            <div class="crisis-modal-stats">
                <div class="crisis-modal-stat">
                    <div class="crisis-modal-val">355</div>
                    <div class="crisis-modal-lbl">Maternal deaths per 100,000 live births</div>
                </div>
                <div class="crisis-modal-stat">
                    <div class="crisis-modal-val">21</div>
                    <div class="crisis-modal-lbl">Newborn deaths per 1,000 births — unchanged in 30 years</div>
                </div>
                <div class="crisis-modal-stat">
                    <div class="crisis-modal-val">41</div>
                    <div class="crisis-modal-lbl">Child deaths (under 5) per 1,000 live births</div>
                </div>
            </div>

            <p class="crisis-modal-note">
                <strong>Postpartum haemorrhage</strong> is Kenya's leading cause of maternal death, and
                <strong>newborns account for over half</strong> of all under-5 deaths. These are the exact gaps
                our EmONC mentorship curriculum closes — obstetric emergency response, essential newborn care
                &amp; resuscitation, and sick-child management — through live, hands-on mentorship already
                running in <strong>{{ number_format($platformStats['counties']) }} counties</strong> today.
            </p>

            <div class="crisis-modal-source">Source: Kenya Demographic &amp; Health Survey (KDHS) 2022 · Ministry of Health Kenya</div>

            <button type="button" class="crisis-modal-cta" @click="crisisOpen = false">
                Continue to Sign In
            </button>
        </div>
    </div>

    {{-- Filament's ->revealable() handles password toggle natively via Alpine --}}

    @include('livewire.auth.partials.styles')
</div>

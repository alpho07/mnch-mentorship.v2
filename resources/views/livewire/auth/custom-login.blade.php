{{-- resources/views/livewire/auth/custom-login.blade.php --}}
<div
    class="fi-simple-page"
    x-data="{
        crisisOpen: false,
        dismissCrisis() {
            this.crisisOpen = false;
            localStorage.setItem('mnch_why_seen_v1', '1');
        },
    }"
    x-init="crisisOpen = !localStorage.getItem('mnch_why_seen_v1')"
    @keydown.escape.window="dismissCrisis()"
>
    <div class="auth-shell">

        {{-- ═══════════════════════════════════════════════════════════════
             LEFT — Rich Hero Panel
             ═══════════════════════════════════════════════════════════════ --}}
        <div class="auth-hero">
            <div class="hero-bg"></div>
            <div class="hero-gradient"></div>
            <div class="hero-orb hero-orb-1"></div>
            <div class="hero-orb hero-orb-2"></div>
            <div class="hero-orb hero-orb-3"></div>

            <div class="hero-content">
                <div class="hero-badge"><span class="hero-dot"></span> Ministry of Health · Kenya</div>

                <h2 class="hero-title"><em>MNCH</em> Mentorship<br>Platform</h2>
                <p class="hero-desc">
                    Kenya's digital backbone for maternal, newborn &amp; child health mentorship — connecting
                    mentors and health workers through structured, trackable, evidence-based programmes.
                </p>

                <div class="crisis-panel">
                    <div class="crisis-label">
                        <svg viewBox="0 0 24 24"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                        Why This Platform Exists
                    </div>
                    <div class="crisis-stats">
                        <div class="crisis-stat">
                            <div class="crisis-val">355</div>
                            <div class="crisis-lbl">Maternal deaths per 100,000 live births</div>
                        </div>
                        <div class="crisis-stat">
                            <div class="crisis-val">21</div>
                            <div class="crisis-lbl">Newborn deaths per 1,000 births — unchanged in 30 years</div>
                        </div>
                        <div class="crisis-stat">
                            <div class="crisis-val">41</div>
                            <div class="crisis-lbl">Child deaths (under 5) per 1,000 live births</div>
                        </div>
                    </div>
                    <div class="crisis-note">
                        <strong>Postpartum haemorrhage</strong> is Kenya's leading cause of maternal death, and
                        <strong>newborns account for over half</strong> of all under-5 deaths. These are the exact
                        gaps our EmONC mentorship curriculum closes — obstetric emergency response, essential
                        newborn care &amp; resuscitation, and sick-child management — through live, hands-on
                        mentorship at the facility level.
                    </div>
                    <div class="crisis-source">Source: Kenya Demographic &amp; Health Survey (KDHS) 2022 · Ministry of Health Kenya</div>
                </div>

                <div class="live-label"><span class="hero-dot"></span> Live Nationwide Impact — Not A Pilot</div>
                <div class="hero-stats">
                    <div><div class="stat-val">{{ number_format($platformStats['mentorships']) }}</div><div class="stat-lbl">Mentorships</div></div>
                    <div><div class="stat-val">{{ number_format($platformStats['mentees']) }}</div><div class="stat-lbl">Mentees Enrolled</div></div>
                    <div><div class="stat-val">{{ number_format($platformStats['facilities']) }}</div><div class="stat-lbl">Facilities</div></div>
                    <div><div class="stat-val">{{ number_format($platformStats['counties']) }}</div><div class="stat-lbl">Counties</div></div>
                </div>

                <div class="hero-features">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <div class="feature-text">
                            <strong>Structured Mentorship</strong>
                            <span>Create programmes with classes, modules &amp; curriculum tracking from enrolment to completion</span>
                        </div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </div>
                        <div class="feature-text">
                            <strong>Real-Time Attendance</strong>
                            <span>Mentees self-confirm via secure links, mentors track participation with immutable records</span>
                        </div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <div class="feature-text">
                            <strong>Progress Analytics</strong>
                            <span>Module completion rates, attendance tracking &amp; facility-level performance dashboards</span>
                        </div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <div class="feature-text">
                            <strong>Role-Based Access</strong>
                            <span>Facility mentors, national mentors, admins &amp; mentees — each with tailored dashboards</span>
                        </div>
                    </div>
                </div>

                <div class="hero-workflow">
                    <div class="workflow-label">How It Works</div>
                    <div class="workflow-steps">
                        <div class="wf-step"><div class="wf-num">1</div><span>Create Mentorship</span></div>
                        <div class="wf-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
                        <div class="wf-step"><div class="wf-num">2</div><span>Add Classes &amp; Modules</span></div>
                        <div class="wf-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
                        <div class="wf-step"><div class="wf-num">3</div><span>Enrol &amp; Track</span></div>
                        <div class="wf-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
                        <div class="wf-step"><div class="wf-num">4</div><span>Complete &amp; Report</span></div>
                    </div>
                </div>

                <div class="hero-trust">
                    <span class="trust-label">Supported by</span>
                    <span class="trust-item">Division of RMNCAH</span>
                    <span class="trust-sep">·</span>
                    <span class="trust-item">County Health Departments</span>
                    <span class="trust-sep">·</span>
                    <span class="trust-item">Development Partners</span>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             RIGHT — Login Form
             ═══════════════════════════════════════════════════════════════ --}}
        <div class="auth-right">
            <div class="auth-box">

                {{-- Ministry of Health Logo --}}
                <div class="moh-logo-wrap">
                    <img src="{{ asset('moh_logo.png') }}" alt="Ministry of Health — Republic of Kenya" class="moh-logo-img">
                </div>

                <h1 class="auth-h1">Welcome back</h1>
                <p class="auth-sub">Sign in to your MNCH Mentorship account to continue.</p>

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

            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         "Why This Platform Exists" — one-time popup on first visit
         ═══════════════════════════════════════════════════════════════ --}}
    <div
        x-show="crisisOpen"
        x-cloak
        class="crisis-modal-backdrop"
        @click.self="dismissCrisis()"
        style="display:none"
    >
        <div class="crisis-modal" role="dialog" aria-modal="true" aria-labelledby="crisis-modal-title">
            <button type="button" class="crisis-modal-close" @click="dismissCrisis()" aria-label="Close">
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

            <button type="button" class="crisis-modal-cta" @click="dismissCrisis()">
                Continue to Sign In
            </button>
        </div>
    </div>

    {{-- Filament's ->revealable() handles password toggle natively via Alpine --}}

    <style>
        /* ── Resets ─────────────────────────────────────────────────────── */
        html,body{height:100%!important;margin:0!important;padding:0!important;overflow:hidden!important}
        .fi-simple-page,.fi-simple-main,.fi-simple-layout,.fi-simple{max-width:none!important;width:100%!important;padding:0!important;margin:0!important;background:transparent!important;min-height:100vh!important}
        *,*::before,*::after{box-sizing:border-box}

        /* ── Entrance animations ─────────────────────────────────────────── */
        @keyframes fade-up   {from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
        @keyframes fade-down {from{opacity:0;transform:translateY(-14px)}to{opacity:1;transform:translateY(0)}}
        @keyframes fade-in   {from{opacity:0}to{opacity:1}}
        @keyframes spin      {to{transform:rotate(360deg)}}
        @keyframes float-orb {0%,100%{transform:translate(0,0) scale(1)}33%{transform:translate(15px,-20px) scale(1.05)}66%{transform:translate(-10px,15px) scale(0.95)}}
        @keyframes pulse-dot {0%,100%{opacity:1}50%{opacity:.35}}
        @keyframes count-up  {from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

        /* Hero entrance */
        .hero-badge    {animation:fade-down 0.55s cubic-bezier(.16,1,.3,1) both}
        .hero-title    {animation:fade-up   0.65s cubic-bezier(.16,1,.3,1) 0.10s both}
        .hero-desc     {animation:fade-up   0.65s cubic-bezier(.16,1,.3,1) 0.20s both}
        .crisis-panel  {animation:fade-up   0.65s cubic-bezier(.16,1,.3,1) 0.26s both}
        .live-label    {animation:fade-up   0.65s cubic-bezier(.16,1,.3,1) 0.30s both}
        .hero-stats    {animation:fade-up   0.65s cubic-bezier(.16,1,.3,1) 0.34s both}
        .stat-val      {animation:count-up  0.5s  cubic-bezier(.16,1,.3,1) 0.35s both}
        .feature-card:nth-child(1){animation:fade-up 0.55s cubic-bezier(.16,1,.3,1) 0.40s both}
        .feature-card:nth-child(2){animation:fade-up 0.55s cubic-bezier(.16,1,.3,1) 0.48s both}
        .feature-card:nth-child(3){animation:fade-up 0.55s cubic-bezier(.16,1,.3,1) 0.56s both}
        .feature-card:nth-child(4){animation:fade-up 0.55s cubic-bezier(.16,1,.3,1) 0.64s both}
        .hero-workflow {animation:fade-up   0.55s cubic-bezier(.16,1,.3,1) 0.68s both}
        .hero-trust    {animation:fade-in   0.55s ease                     0.74s both}
        /* Form entrance */
        .auth-box      {animation:fade-up   0.70s cubic-bezier(.16,1,.3,1) 0.12s both}

        /* ── Shell ───────────────────────────────────────────────────────── */
        .auth-shell{display:flex;height:100vh;width:100vw;font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,system-ui,sans-serif;-webkit-font-smoothing:antialiased}

        /* ── Hero (left) ─────────────────────────────────────────────────── */
        .auth-hero{flex:0 0 58%;position:relative;overflow:hidden;overflow-y:auto;display:flex;align-items:flex-start;justify-content:center;scrollbar-width:none}
        .auth-hero::-webkit-scrollbar{display:none}
        .hero-bg{position:fixed;left:0;top:0;width:58%;height:100vh;background:url('https://images.unsplash.com/photo-1584515933487-779824d29309?w=1200&q=80') center/cover no-repeat}
        .hero-gradient{position:fixed;left:0;top:0;width:58%;height:100vh;background:linear-gradient(135deg,rgba(8,28,90,.96) 0%,rgba(18,69,168,.91) 45%,rgba(37,99,235,.86) 100%)}

        .hero-orb{position:fixed;border-radius:50%;pointer-events:none;background:radial-gradient(circle,rgba(147,197,253,.13) 0%,transparent 70%)}
        .hero-orb-1{width:420px;height:420px;top:-90px;left:-70px;animation:float-orb 8s ease-in-out infinite}
        .hero-orb-2{width:320px;height:320px;bottom:50px;left:28%;animation:float-orb 11s ease-in-out infinite reverse}
        .hero-orb-3{width:220px;height:220px;top:38%;left:52%;animation:float-orb 7s ease-in-out infinite 2s}

        .hero-content{position:relative;z-index:2;padding:2.5rem 2.75rem 2.75rem;width:100%;max-width:760px;box-sizing:border-box;color:#fff}

        .hero-badge{display:inline-flex;align-items:center;gap:6px;font-size:.7rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;background:rgba(255,255,255,.1);backdrop-filter:blur(8px);padding:.35rem .85rem;border-radius:999px;margin-bottom:1.35rem;border:1px solid rgba(255,255,255,.14)}
        .hero-dot{width:7px;height:7px;border-radius:50%;background:#60a5fa;box-shadow:0 0 7px #60a5fa;animation:pulse-dot 2s ease-in-out infinite;flex-shrink:0}

        .hero-title{font-size:2.15rem;font-weight:800;line-height:1.13;letter-spacing:-.04em;margin:0 0 .8rem}
        .hero-title em{font-style:normal;background:linear-gradient(120deg,#93c5fd,#60a5fa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .hero-desc{font-size:.875rem;line-height:1.65;opacity:.75;margin-bottom:1.5rem;font-weight:400;max-width:480px}

        /* ── Crisis / urgency panel ──────────────────────────────────────── */
        .crisis-panel{background:#ffffff;border:1px solid #fecaca;border-radius:13px;padding:1rem 1.1rem 1.05rem;margin-bottom:1.25rem;box-shadow:0 10px 28px rgba(0,0,0,.22)}
        .crisis-label{display:flex;align-items:center;gap:.4rem;font-size:.63rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#dc2626;margin-bottom:.75rem}
        .crisis-label svg{width:12px;height:12px;stroke:#dc2626;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
        .crisis-stats{display:flex;gap:.9rem;margin-bottom:.7rem}
        .crisis-stat{flex:1;min-width:0}
        .crisis-val{font-size:1.5rem;font-weight:800;letter-spacing:-.03em;line-height:1;color:#b91c1c}
        .crisis-lbl{font-size:.62rem;line-height:1.4;color:#64748b;font-weight:500;margin-top:.3rem}
        .crisis-note{font-size:.71rem;line-height:1.55;color:#334155;font-weight:500;border-top:1px solid #fee2e2;padding-top:.65rem;margin-bottom:.35rem}
        .crisis-note strong{color:#b91c1c;font-weight:700}
        .crisis-source{font-size:.58rem;color:#94a3b8;font-weight:500;letter-spacing:.01em}

        /* ── "Why This Platform Exists" popup ────────────────────────────── */
        [x-cloak]{display:none!important}
        .crisis-modal-backdrop{position:fixed;inset:0;z-index:100;background:rgba(15,23,42,.72);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;padding:1.5rem;animation:fade-in .25s ease both}
        .crisis-modal{position:relative;background:#fff;border-radius:18px;max-width:480px;width:100%;padding:1.85rem 1.85rem 1.6rem;box-shadow:0 24px 60px rgba(0,0,0,.35);animation:fade-up .35s cubic-bezier(.16,1,.3,1) both;max-height:88vh;overflow-y:auto}
        .crisis-modal-close{position:absolute;top:.9rem;right:.9rem;width:28px;height:28px;border-radius:50%;border:none;background:#f1f5f9;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .15s}
        .crisis-modal-close:hover{background:#e2e8f0}
        .crisis-modal-close svg{width:14px;height:14px;stroke:#64748b;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
        .crisis-modal-label{display:flex;align-items:center;gap:.5rem;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#dc2626;margin-bottom:1.15rem;padding-right:1.5rem}
        .crisis-modal-label svg{width:14px;height:14px;stroke:#dc2626;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0}
        .crisis-modal-stats{display:flex;gap:1rem;margin-bottom:1.15rem;padding-bottom:1.15rem;border-bottom:1px solid #f1f5f9}
        .crisis-modal-stat{flex:1;min-width:0}
        .crisis-modal-val{font-size:1.9rem;font-weight:800;letter-spacing:-.03em;line-height:1;color:#b91c1c;margin-bottom:.35rem}
        .crisis-modal-lbl{font-size:.68rem;line-height:1.4;color:#64748b;font-weight:500}
        .crisis-modal-note{font-size:.82rem;line-height:1.65;color:#334155;font-weight:500;margin-bottom:.9rem}
        .crisis-modal-note strong{color:#b91c1c;font-weight:700}
        .crisis-modal-source{font-size:.65rem;color:#94a3b8;font-weight:500;margin-bottom:1.35rem}
        .crisis-modal-cta{width:100%;padding:.85rem 1.25rem;background:linear-gradient(135deg,#1245A8,#1A54C8);color:#fff;border:none;border-radius:11px;cursor:pointer;font-family:inherit;font-size:.86rem;font-weight:700;transition:all .2s;box-shadow:0 4px 16px rgba(18,69,168,.28)}
        .crisis-modal-cta:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(18,69,168,.36)}
        .crisis-modal-cta:active{transform:translateY(0)}
        @media(max-width:520px){
            .crisis-modal{padding:1.5rem 1.35rem 1.35rem}
            .crisis-modal-stats{flex-direction:column;gap:.85rem}
        }

        .live-label{display:flex;align-items:center;gap:.4rem;font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;opacity:.55;margin-bottom:.6rem}

        .hero-stats{display:flex;border-top:1px solid rgba(255,255,255,.11);border-bottom:1px solid rgba(255,255,255,.11);padding:.85rem 0;margin-bottom:1.5rem}
        .hero-stats > div{flex:1;text-align:center;border-right:1px solid rgba(255,255,255,.09);padding:0 .5rem}
        .hero-stats > div:last-child{border-right:none}
        .stat-val{font-size:1.4rem;font-weight:800;letter-spacing:-.025em;line-height:1}
        .stat-lbl{font-size:.6rem;text-transform:uppercase;letter-spacing:.06em;opacity:.5;margin-top:.3rem;font-weight:600}

        .hero-features{display:grid;grid-template-columns:1fr 1fr;gap:.55rem;margin-bottom:1.4rem}
        .feature-card{display:flex;align-items:flex-start;gap:.6rem;padding:.65rem .7rem;border-radius:11px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.09);transition:background .25s,border-color .25s,transform .25s}
        .feature-card:hover{background:rgba(255,255,255,.13);border-color:rgba(255,255,255,.18);transform:translateY(-2px)}
        .feature-icon{width:30px;height:30px;border-radius:8px;flex-shrink:0;background:rgba(147,197,253,.18);display:flex;align-items:center;justify-content:center;margin-top:1px}
        .feature-icon svg{width:15px;height:15px;stroke:#93c5fd;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .feature-text{display:flex;flex-direction:column;gap:.12rem;min-width:0}
        .feature-text strong{font-size:.74rem;font-weight:700;color:#fff;line-height:1.25}
        .feature-text span{font-size:.67rem;line-height:1.45;opacity:.58;font-weight:400}

        .hero-workflow{background:rgba(0,0,0,.18);backdrop-filter:blur(10px);border-radius:11px;padding:.7rem 1rem;margin-bottom:1.2rem;border:1px solid rgba(255,255,255,.07)}
        .workflow-label{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;opacity:.45;margin-bottom:.5rem}
        .workflow-steps{display:flex;align-items:center;gap:0}
        .wf-step{display:flex;align-items:center;gap:.3rem;flex:1;min-width:0}
        .wf-num{width:20px;height:20px;border-radius:50%;flex-shrink:0;background:rgba(147,197,253,.18);border:1.5px solid rgba(147,197,253,.35);display:flex;align-items:center;justify-content:center;font-size:.58rem;font-weight:800;color:#93c5fd}
        .wf-step span{font-size:.63rem;font-weight:600;opacity:.72;line-height:1.25;white-space:nowrap}
        .wf-arrow{flex-shrink:0;margin:0 .1rem;opacity:.25}
        .wf-arrow svg{width:11px;height:11px;stroke:#fff;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}

        .hero-trust{display:flex;align-items:center;flex-wrap:wrap;gap:.4rem;font-size:.62rem;opacity:.4;font-weight:500;padding-top:.8rem;border-top:1px solid rgba(255,255,255,.08)}
        .trust-label{font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-right:.1rem}
        .trust-sep{opacity:.35}

        /* ── Right panel ─────────────────────────────────────────────────── */
        .auth-right{flex:1;display:flex;align-items:center;justify-content:center;background:#f8faff;background-image:radial-gradient(circle,rgba(26,84,200,.05) 1px,transparent 1px);background-size:22px 22px;overflow-y:auto;padding:2rem 1.5rem}
        .auth-box{width:100%;max-width:420px}

        /* ── MoH Logo ────────────────────────────────────────────────────── */
        .moh-logo-wrap{display:flex;align-items:center;justify-content:flex-start;padding-bottom:1.4rem;margin-bottom:1.5rem;border-bottom:2px solid #DBEAFE}
        .moh-logo-img{height:72px;width:auto;max-width:100%;object-fit:contain;object-position:left center;display:block}

        /* ── Auth headings ───────────────────────────────────────────────── */
        .auth-h1{font-size:1.5rem;font-weight:800;color:#111827;letter-spacing:-.03em;margin:0 0 .35rem;line-height:1.2}
        .auth-sub{font-size:.87rem;color:#6b7280;margin:0 0 1.5rem;line-height:1.5}

        /* ── Submit button ───────────────────────────────────────────────── */
        .auth-btn{width:100%;display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.85rem 1.5rem;margin-top:1.25rem;background:linear-gradient(135deg,#1245A8,#1A54C8);color:#fff;border:none;border-radius:12px;cursor:pointer;font-family:inherit;font-size:.9rem;font-weight:700;letter-spacing:-.01em;transition:all .2s;box-shadow:0 4px 16px rgba(18,69,168,.32)}
        .auth-btn:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(18,69,168,.42)}
        .auth-btn:active{transform:translateY(0);box-shadow:0 2px 8px rgba(18,69,168,.25)}
        .auth-btn:disabled{opacity:.6;cursor:wait;transform:none!important}
        .auth-btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .btn-idle{display:flex;align-items:center;gap:.4rem}
        .btn-loading{display:none;align-items:center;gap:.4rem}

        /* ── Footer links ────────────────────────────────────────────────── */
        .login-footer{display:flex;justify-content:flex-end;margin-top:.5rem;margin-bottom:.1rem}
        .login-footer-center{justify-content:center;margin-top:1.25rem}
        .forgot-link{font-size:.76rem;font-weight:600;color:#1245A8;text-decoration:none;transition:color .15s}
        .forgot-link:hover{color:#0D3A8E}

        /* ── Spinner ─────────────────────────────────────────────────────── */
        .spin{animation:spin .8s linear infinite}

        /* ── Filament input overrides ────────────────────────────────────── */
        .auth-box .fi-fo-field-wrp{margin-bottom:.35rem}
        .auth-box .fi-fo-field-wrp-label label{font-size:.82rem!important;font-weight:600!important;color:#374151!important;margin-bottom:.35rem!important}
        .auth-box .fi-input-wrp{
            border-radius:11px!important;
            border-color:#d1d5db!important;
            background:#fff!important;
            transition:border-color .18s,box-shadow .18s!important;
        }
        .auth-box .fi-input-wrp:focus-within{
            border-color:#1A54C8!important;
            box-shadow:0 0 0 3.5px rgba(26,84,200,.13)!important;
        }

        /* Compact inline prefix — remove separator, tighten spacing */
        .auth-box .fi-input-wrp-prefix{
            border-inline-end:none!important;
            border-right:none!important;
            padding-inline-start:.75rem!important;
            padding-inline-end:.25rem!important;
            gap:.25rem!important;
        }
        /* Icon size */
        .auth-box .fi-input-wrp-icon{
            width:1rem!important;
            height:1rem!important;
            color:#9ca3af!important;
            flex-shrink:0!important;
        }
        /* Reduce input left padding since icon is inline */
        .auth-box .fi-fo-text-input .fi-input{
            padding-inline-start:.35rem!important;
        }

        /* Suffix (password reveal button) */
        .auth-box .fi-input-wrp-suffix{
            border-inline-start:none!important;
            border-left:none!important;
            padding-inline-start:.25rem!important;
            padding-inline-end:.5rem!important;
        }
        .auth-box .fi-input-wrp-suffix .fi-ac-action button,
        .auth-box .fi-input-wrp-suffix button{
            padding:.35rem!important;
            border-radius:6px!important;
            color:#9ca3af!important;
            transition:color .15s,background .15s!important;
        }
        .auth-box .fi-input-wrp-suffix .fi-ac-action button:hover,
        .auth-box .fi-input-wrp-suffix button:hover{
            color:#1A54C8!important;
            background:rgba(26,84,200,.08)!important;
        }
        .auth-box .fi-input-wrp-suffix svg{
            width:1rem!important;
            height:1rem!important;
        }

        /* Fix browser autofill background bleeding */
        .auth-box .fi-input:-webkit-autofill,
        .auth-box .fi-input:-webkit-autofill:hover,
        .auth-box .fi-input:-webkit-autofill:focus,
        .auth-box input:-webkit-autofill,
        .auth-box input:-webkit-autofill:hover,
        .auth-box input:-webkit-autofill:focus{
            -webkit-box-shadow:0 0 0 1000px #ffffff inset!important;
            -webkit-text-fill-color:#111827!important;
            transition:background-color 9999s ease-in-out 0s;
        }

        /* Checkbox styling */
        .auth-box .fi-fo-checkbox label{font-size:.82rem!important;color:#374151!important}

        /* ── Responsive ──────────────────────────────────────────────────── */
        @media(max-width:1100px){
            .hero-features{grid-template-columns:1fr}
            .hero-content{padding:2rem 2.25rem 2.5rem}
        }
        @media(max-width:900px){
            /* Hide blue hero completely on tablet/mobile */
            .auth-hero{display:none!important}
            html,body{overflow:auto!important}
            .auth-right{flex:1;padding:2rem 1.5rem}
        }
        @media(max-width:600px){
            .auth-right{padding:1.5rem 1rem}
            .moh-logo-img{height:56px}
            .auth-h1{font-size:1.3rem}
        }
    </style>
</div>

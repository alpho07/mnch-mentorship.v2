{{-- resources/views/livewire/auth/custom-register.blade.php --}}
<div class="fi-simple-page">
    <div class="auth-shell">

        {{-- ═══════════════════════════════════════════════════════════════
             LEFT — Hero Panel
             ═══════════════════════════════════════════════════════════════ --}}
        <div class="auth-hero">  
            <div class="hero-bg"></div>
            <div class="hero-gradient"></div>
            <div class="hero-content">
                <div class="hero-badge"><span class="hero-dot"></span> Ministry of Health · Kenya</div>
                <h2 class="hero-title"><em>MNCH</em> Mentorship<br>Platform</h2>
                <p class="hero-desc">
                    Join Kenya's largest network of maternal, newborn and child health mentors.
                    Register to connect with structured, trackable mentorship programmes at your facility.
                </p>
                <div class="hero-stats">
                    <div><div class="stat-val">47</div><div class="stat-lbl">Counties</div></div>
                    <div><div class="stat-val">2,400+</div><div class="stat-lbl">Health workers</div></div>
                    <div><div class="stat-val">580+</div><div class="stat-lbl">Facilities</div></div>
                </div>
                <div class="hero-benefits"> 
                    <div class="benefit-item">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span>Access structured mentorship programmes &amp; training modules</span>
                    </div>
                    <div class="benefit-item">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span>Track your attendance &amp; module completion progress</span>
                    </div>
                    <div class="benefit-item">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span>Connect with facility mentors across your county</span>
                    </div>
                    <div class="benefit-item">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span>Access knowledge base resources &amp; AI-powered guidance</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             RIGHT — Registration Form
             ═══════════════════════════════════════════════════════════════ --}}
        <div class="auth-right">
            <div class="auth-box">

                {{-- Ministry of Health Logo --}}
                <div class="moh-logo-wrap">
                    <img src="{{ asset('moh_logo.png') }}" alt="Ministry of Health — Republic of Kenya" class="moh-logo-img">
                </div>

                <h1 class="auth-h1">Create Account</h1>
                <p class="auth-sub">Register to access MNCH mentorship programmes across Kenya.</p>

                <div class="auth-infobox">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>After registering, check your email for further instructions. Click the link to set your password and activate your account.</span>
                </div>

                <form wire:submit="register" novalidate>
                    {{ $this->form }}

                    <button type="submit" class="auth-btn" wire:loading.attr="disabled">
                        <span class="btn-idle" wire:loading.remove wire:target="register">
                            Create Account
                            <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                        </span>
                        <span class="btn-loading" wire:loading wire:target="register">
                            <svg class="spin" viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5">
                                <circle cx="12" cy="12" r="10" style="opacity:0.25;stroke:currentColor"/>
                                <path d="M4 12a8 8 0 018-8" style="opacity:0.85;stroke:currentColor"/>
                            </svg>
                            Sending verification email…
                        </span>
                    </button>
                </form>

                <a href="{{ route('filament.admin.auth.login') }}" class="auth-back auth-back-center">
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    Already have an account? Sign in
                </a>
            </div>
        </div>
    </div>

    <style>
        [x-cloak]{display:none!important}
        html,body{height:100%!important;margin:0!important;padding:0!important;overflow:hidden!important}
        .fi-simple-page,.fi-simple-main,.fi-simple-layout,.fi-simple{max-width:none!important;width:100%!important;padding:0!important;margin:0!important;background:transparent!important;min-height:100vh!important}
        *,*::before,*::after{box-sizing:border-box}

        .auth-shell{display:flex;height:100vh;width:100vw;font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,system-ui,sans-serif;-webkit-font-smoothing:antialiased}

        /* Hero */
        .auth-hero{flex:0 0 40%;position:relative;overflow:hidden;display:flex;align-items:center}
        .hero-bg{position:absolute;inset:0;background:url('https://images.unsplash.com/photo-1584515933487-779824d29309?w=1200&q=80') center/cover no-repeat}
        .hero-gradient{position:absolute;inset:0;background:linear-gradient(135deg,rgba(10,34,104,.95) 0%,rgba(18,69,168,.90) 40%,rgba(37,99,235,.84) 100%)}
        .hero-content{position:relative;z-index:2;padding:2.5rem 2.75rem;max-width:480px;color:#fff}
        .hero-badge{display:inline-flex;align-items:center;gap:6px;font-size:.72rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;background:rgba(255,255,255,.12);backdrop-filter:blur(6px);padding:.35rem .85rem;border-radius:999px;margin-bottom:1.5rem;border:1px solid rgba(255,255,255,.15)}
        .hero-dot{width:6px;height:6px;border-radius:50%;background:#60a5fa;box-shadow:0 0 6px #60a5fa;animation:pulse-dot 2s ease-in-out infinite}
        @keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:.4}}
        .hero-title{font-size:1.9rem;font-weight:800;line-height:1.15;letter-spacing:-.035em;margin:0 0 .9rem}
        .hero-title em{font-style:normal;color:#93c5fd}
        .hero-desc{font-size:.86rem;line-height:1.65;opacity:.82;margin-bottom:1.5rem;font-weight:400}
        .hero-stats{display:flex;gap:0;padding:.85rem 0;border-top:1px solid rgba(255,255,255,.15);border-bottom:1px solid rgba(255,255,255,.15);margin-bottom:1.5rem}
        .hero-stats > div{flex:1;text-align:center;border-right:1px solid rgba(255,255,255,.1)}
        .hero-stats > div:last-child{border-right:none}
        .stat-val{font-size:1.3rem;font-weight:800;letter-spacing:-.02em}
        .stat-lbl{font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;opacity:.55;margin-top:.1rem;font-weight:500}
        .hero-benefits{display:flex;flex-direction:column;gap:.55rem}
        .benefit-item{display:flex;align-items:center;gap:.6rem;font-size:.82rem;font-weight:500;opacity:.85}
        .benefit-item svg{width:16px;height:16px;flex-shrink:0;stroke:#93c5fd;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

        /* Right panel */
        .auth-right{
            flex:1;display:flex;align-items:flex-start;justify-content:center;
            background:#f8faff;
            background-image:radial-gradient(circle,rgba(26,84,200,.05) 1px,transparent 1px);
            background-size:22px 22px;
            overflow-y:auto;padding:2rem 1.5rem;
        }
        .auth-box{width:100%;max-width:600px;padding:1.5rem 0 3rem}

        /* MoH Logo */
        .moh-logo-wrap{
            display:flex;align-items:center;justify-content:flex-start;
            padding-bottom:1.4rem;margin-bottom:1.5rem;
            border-bottom:2px solid #DBEAFE;
        }
        .moh-logo-img{height:72px;width:auto;max-width:100%;object-fit:contain;object-position:left center;display:block}

        /* Auth headings */
        .auth-h1{font-size:1.45rem;font-weight:800;color:#111827;letter-spacing:-.03em;margin:0 0 .3rem;line-height:1.2}
        .auth-sub{font-size:.85rem;color:#6b7280;margin:0 0 1rem;line-height:1.5}

        /* Info box */
        .auth-infobox{display:flex;align-items:flex-start;gap:.6rem;padding:.7rem .9rem;border-radius:9px;margin-bottom:1.25rem;background:#EFF6FF;border:1px solid #BFDBFE}
        .auth-infobox svg{width:15px;height:15px;flex-shrink:0;margin-top:1px;stroke:#1A54C8;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .auth-infobox span{font-size:.78rem;color:#1245A8;line-height:1.5}

        /* Button */
        .auth-btn{width:100%;display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.8rem 1.5rem;margin-top:1rem;background:linear-gradient(135deg,#1245A8,#1A54C8);color:#fff;border:none;border-radius:10px;cursor:pointer;font-family:inherit;font-size:.9rem;font-weight:700;transition:all .2s;box-shadow:0 4px 14px rgba(18,69,168,.3)}
        .auth-btn:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(18,69,168,.4)}
        .auth-btn:active{transform:translateY(0)}
        .auth-btn:disabled{opacity:.6;cursor:wait;transform:none!important}
        .auth-btn svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .btn-idle{display:flex;align-items:center;gap:.4rem}
        .btn-loading{display:none;align-items:center;gap:.4rem}

        /* Back link */
        .auth-back{display:inline-flex;align-items:center;gap:.4rem;font-size:.83rem;font-weight:600;color:#6b7280;text-decoration:none;transition:color .2s;margin-top:1.25rem}
        .auth-back:hover{color:#1245A8}
        .auth-back svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
        .auth-back-center{display:flex;justify-content:center;width:100%}

        /* Filament overrides */
        .auth-box .fi-fo-field-wrp{margin-bottom:.25rem}
        .auth-box .fi-input-wrp{border-radius:10px!important;border-color:#d1d5db!important;transition:all .2s!important}
        .auth-box .fi-input-wrp:focus-within{border-color:#1A54C8!important;box-shadow:0 0 0 3px rgba(26,84,200,.12)!important}

        /* Spinner */
        .spin{animation:spin .8s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}

        /* Responsive */
        @media(max-width:900px){
            .auth-shell{flex-direction:column;height:auto;min-height:100vh}
            html,body{overflow:auto!important}
            .auth-hero{flex:none;min-height:220px}
            .hero-content{padding:2rem}
            .hero-title{font-size:1.5rem}
            .hero-benefits{display:none}
            .auth-right{padding:1.5rem 1rem;overflow-y:visible}
        }
        @media(max-width:600px){
            .hero-content{padding:1.5rem}
            .hero-title{font-size:1.25rem}
            .auth-box{padding:1rem 0 2rem}
            .moh-logo-img{height:56px}
        }
    </style>
</div>

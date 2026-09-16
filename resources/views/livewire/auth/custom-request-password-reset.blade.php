<div class="fi-simple-page">
    <div class="auth-shell">

        {{-- LEFT --}}
        <div class="auth-hero">
            <div class="hero-bg"></div>
            <div class="hero-gradient"></div>
            <div class="hero-orb hero-orb-1"></div>
            <div class="hero-orb hero-orb-2"></div>
            <div class="hero-content">
                <div class="hero-badge"><span class="hero-dot"></span> Ministry of Health · Kenya</div>
                <h2 class="hero-title"><em>MNCH</em> Mentorship<br>Platform</h2>
                <p class="hero-desc">Transforming maternal, newborn and child health outcomes through structured, evidence-based mentorship across Kenya's health facilities.</p>
                <div class="hero-stats">
                    <div><div class="stat-val">47</div><div class="stat-lbl">Counties</div></div>
                    <div><div class="stat-val">2,400+</div><div class="stat-lbl">Health workers</div></div>
                    <div><div class="stat-val">580+</div><div class="stat-lbl">Facilities</div></div>
                </div>
                <div class="hero-tips">
                    <div class="tip-label">Account Tips</div>
                    <div class="tip-item">
                        <div class="tip-icon"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
                        <span>Your account is linked to your email — use the same address you registered with.</span>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                        <span>Check your spam/junk folder if you don't see the reset email within a few minutes.</span>
                    </div>
                    <div class="tip-item">
                        <div class="tip-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
                        <span>Reset links expire after 60 minutes for your security.</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT --}}
        <div class="auth-right">
            <div class="auth-box">

                {{-- Ministry of Health Logo --}}
                <div class="moh-logo-wrap">
                    <img src="{{ asset('moh_logo.png') }}" alt="Ministry of Health — Republic of Kenya" class="moh-logo-img">
                </div>

                {{-- Steps --}}
                <div class="steps">
                    <div class="step {{ !$linkSent ? 'active' : 'done' }}">
                        <span class="step-num">
                            @if($linkSent)
                                <svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:#fff;fill:none;stroke-width:3;stroke-linecap:round;stroke-linejoin:round"><polyline points="20 6 9 17 4 12"/></svg>
                            @else
                                1
                            @endif
                        </span>
                        <span>Enter email</span>
                    </div>
                    <div class="step-connector {{ $linkSent ? 'done' : '' }}"></div>
                    <div class="step {{ $linkSent ? 'active' : '' }}">
                        <span class="step-num">2</span>
                        <span>Check inbox</span>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step">
                        <span class="step-num">3</span>
                        <span>Set password</span>
                    </div>
                </div>

                {{-- STEP 1 — Enter email --}}
                @if (!$linkSent)

                    <h1 class="auth-h1">Forgot your password?</h1>
                    <p class="auth-sub">Enter the email address linked to your account and we'll send you a reset link.</p>

                    <div class="auth-infobox">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>We'll only send a reset link if an account exists for this email address.</span>
                    </div>

                    <form wire:submit.prevent="sendResetLink">
                        {{ $this->form }}

                        <button type="submit" class="auth-btn">
                            <span class="btn-idle" wire:loading.remove wire:target="sendResetLink">
                                Send reset link
                                <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            </span>
                            <span class="btn-loading" wire:loading wire:target="sendResetLink">
                                <svg class="spin" viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5">
                                    <circle cx="12" cy="12" r="10" style="opacity:0.25;stroke:currentColor"/>
                                    <path d="M4 12a8 8 0 018-8" style="opacity:0.85;stroke:currentColor"/>
                                </svg>
                                Sending...
                            </span>
                        </button>
                    </form>

                    <a href="{{ route('filament.admin.auth.login') }}" class="auth-back">
                        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                        Back to sign in
                    </a>

                {{-- STEP 2 — Check inbox --}}
                @else

                    <div class="inbox-panel">
                        <div class="inbox-icon">
                            <svg viewBox="0 0 24 24">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </div>
                        <p class="inbox-title">Check your inbox</p>
                        <p class="inbox-sub">We sent a password reset link to</p>
                        <p class="inbox-email">{{ $sentToEmail }}</p>
                        <p class="inbox-hint">
                            Didn't receive it? Check your spam folder or
                            <button wire:click="$set('linkSent', false)">try another email</button>
                        </p>
                        <a href="{{ route('filament.admin.auth.login') }}" class="auth-back auth-back-center">
                            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                            Back to sign in
                        </a>
                    </div>

                @endif

            </div>
        </div>
    </div>

    <style>
        html,body{height:100%!important;margin:0!important;padding:0!important;overflow:hidden!important}
        .fi-simple-page,.fi-simple-main,.fi-simple-layout,.fi-simple{max-width:none!important;width:100%!important;padding:0!important;margin:0!important;background:transparent!important;min-height:100vh!important}
        *,*::before,*::after{box-sizing:border-box}

        .auth-shell{display:flex;height:100vh;width:100vw;font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,system-ui,sans-serif;-webkit-font-smoothing:antialiased}

        /* Hero */
        .auth-hero{flex:0 0 45%;position:relative;overflow:hidden;display:flex;align-items:center}
        .hero-bg{position:absolute;inset:0;background:url('https://images.unsplash.com/photo-1584515933487-779824d29309?w=1200&q=80') center/cover no-repeat}
        .hero-gradient{position:absolute;inset:0;background:linear-gradient(135deg,rgba(10,34,104,.95) 0%,rgba(18,69,168,.90) 40%,rgba(37,99,235,.84) 100%)}
        .hero-orb{position:absolute;border-radius:50%;pointer-events:none;background:radial-gradient(circle,rgba(147,197,253,.12) 0%,transparent 70%)}
        .hero-orb-1{width:380px;height:380px;top:-80px;left:-60px;animation:float-orb 8s ease-in-out infinite}
        .hero-orb-2{width:280px;height:280px;bottom:40px;left:35%;animation:float-orb 10s ease-in-out infinite reverse}
        @keyframes float-orb{0%,100%{transform:translate(0,0) scale(1)}33%{transform:translate(15px,-20px) scale(1.05)}66%{transform:translate(-10px,15px) scale(0.95)}}
        .hero-content{position:relative;z-index:2;padding:2.5rem 2.75rem;max-width:500px;color:#fff}
        .hero-badge{display:inline-flex;align-items:center;gap:6px;font-size:.72rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;background:rgba(255,255,255,.12);backdrop-filter:blur(6px);padding:.35rem .85rem;border-radius:999px;margin-bottom:1.5rem;border:1px solid rgba(255,255,255,.15)}
        .hero-dot{width:6px;height:6px;border-radius:50%;background:#60a5fa;box-shadow:0 0 6px #60a5fa;animation:pulse-dot 2s ease-in-out infinite}
        @keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:.4}}
        .hero-title{font-size:1.9rem;font-weight:800;line-height:1.15;letter-spacing:-.035em;margin:0 0 .9rem}
        .hero-title em{font-style:normal;color:#93c5fd}
        .hero-desc{font-size:.87rem;line-height:1.65;opacity:.8;margin-bottom:1.5rem}
        .hero-stats{display:flex;gap:0;padding:.85rem 0;border-top:1px solid rgba(255,255,255,.15);border-bottom:1px solid rgba(255,255,255,.15);margin-bottom:1.75rem}
        .hero-stats > div{flex:1;text-align:center;border-right:1px solid rgba(255,255,255,.1)}
        .hero-stats > div:last-child{border-right:none}
        .stat-val{font-size:1.3rem;font-weight:800;letter-spacing:-.02em}
        .stat-lbl{font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;opacity:.55;margin-top:.1rem;font-weight:500}
        .hero-tips{display:flex;flex-direction:column;gap:.7rem}
        .tip-label{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;opacity:.5;margin-bottom:.15rem}
        .tip-item{display:flex;align-items:flex-start;gap:.6rem;font-size:.81rem;opacity:.8;line-height:1.45}
        .tip-icon{width:26px;height:26px;border-radius:7px;flex-shrink:0;background:rgba(147,197,253,.15);display:flex;align-items:center;justify-content:center}
        .tip-icon svg{width:13px;height:13px;stroke:#93c5fd;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

        /* Right panel */
        .auth-right{
            flex:1;display:flex;align-items:center;justify-content:center;
            background:#f8faff;
            background-image:radial-gradient(circle,rgba(26,84,200,.05) 1px,transparent 1px);
            background-size:22px 22px;
            overflow-y:auto;padding:2rem 1.5rem;
        }
        .auth-box{width:100%;max-width:440px}

        /* MoH Logo */
        .moh-logo-wrap{
            display:flex;align-items:center;justify-content:flex-start;
            padding-bottom:1.4rem;margin-bottom:1.5rem;
            border-bottom:2px solid #DBEAFE;
        }
        .moh-logo-img{height:72px;width:auto;max-width:100%;object-fit:contain;object-position:left center;display:block}

        /* Steps */
        .steps{display:flex;align-items:center;gap:.3rem;margin-bottom:1.75rem}
        .step{display:flex;align-items:center;gap:.45rem;font-size:.75rem;font-weight:600;color:#94a3b8}
        .step.active{color:#1245A8}
        .step.done{color:#1A54C8}
        .step-num{width:24px;height:24px;border-radius:50%;border:2px solid #cbd5e1;display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:800;color:#94a3b8;flex-shrink:0;transition:all .3s}
        .step.active .step-num{border-color:#1A54C8;color:#1A54C8;background:#EFF6FF}
        .step.done .step-num{border-color:#1245A8;background:#1245A8;color:#fff}
        .step-connector{flex:1;height:2px;background:#e2e8f0;border-radius:1px;min-width:16px;transition:background .3s}
        .step-connector.done{background:#1A54C8}

        /* Auth headings */
        .auth-h1{font-size:1.45rem;font-weight:800;color:#111827;letter-spacing:-.03em;margin:0 0 .3rem;line-height:1.2}
        .auth-sub{font-size:.85rem;color:#6b7280;margin:0 0 1.1rem;line-height:1.5}

        /* Info box */
        .auth-infobox{display:flex;align-items:flex-start;gap:.6rem;padding:.7rem .9rem;border-radius:9px;margin-bottom:1.25rem;background:#EFF6FF;border:1px solid #BFDBFE}
        .auth-infobox svg{width:15px;height:15px;flex-shrink:0;margin-top:1px;stroke:#1A54C8;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .auth-infobox span{font-size:.78rem;color:#1245A8;line-height:1.5}

        /* Button */
        .auth-btn{width:100%;display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.8rem 1.5rem;margin-top:1.25rem;background:linear-gradient(135deg,#1245A8,#1A54C8);color:#fff;border:none;border-radius:12px;cursor:pointer;font-family:inherit;font-size:.9rem;font-weight:700;transition:all .2s;box-shadow:0 4px 14px rgba(18,69,168,.3)}
        .auth-btn:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(18,69,168,.4)}
        .auth-btn:active{transform:translateY(0)}
        .auth-btn svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .btn-idle{display:flex;align-items:center;gap:.4rem}
        .btn-loading{display:none;align-items:center;gap:.4rem}

        /* Back link */
        .auth-back{display:inline-flex;align-items:center;gap:.4rem;font-size:.83rem;font-weight:600;color:#6b7280;text-decoration:none;transition:color .2s;margin-top:1.25rem}
        .auth-back:hover{color:#1245A8}
        .auth-back svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}
        .auth-back-center{display:flex;justify-content:center;width:100%}

        /* Inbox success panel */
        .inbox-panel{text-align:center;padding-top:.5rem}
        .inbox-icon{width:68px;height:68px;border-radius:18px;background:linear-gradient(135deg,#1245A8,#1A54C8);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;box-shadow:0 8px 24px rgba(18,69,168,.3)}
        .inbox-icon svg{width:30px;height:30px;stroke:#fff;fill:none;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round}
        .inbox-title{font-size:1.2rem;font-weight:800;color:#111827;margin:0 0 .25rem;letter-spacing:-.02em}
        .inbox-sub{font-size:.85rem;color:#6b7280;margin:0}
        .inbox-email{font-size:.92rem;font-weight:700;color:#1245A8;margin:.4rem 0 1rem;word-break:break-all}
        .inbox-hint{font-size:.8rem;color:#94a3b8;margin:0 0 .5rem;line-height:1.6}
        .inbox-hint button{background:none;border:none;color:#1A54C8;font-weight:600;cursor:pointer;font-size:inherit;padding:0;text-decoration:underline}
        .inbox-hint button:hover{color:#1245A8}

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
            .hero-tips{display:none}
            .hero-content{padding:2rem}
            .hero-title{font-size:1.5rem}
            .auth-right{padding:1.5rem 1rem}
        }
        @media(max-width:600px){
            .hero-content{padding:1.5rem}
            .hero-title{font-size:1.25rem}
            .moh-logo-img{height:56px}
        }
    </style>
</div>

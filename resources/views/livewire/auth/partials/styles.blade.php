<style>
    /* ── Resets ─────────────────────────────────────────────────────── */
    html,body{height:100%!important;margin:0!important;padding:0!important;overflow:hidden!important}
    .fi-simple-page,.fi-simple-main,.fi-simple-layout,.fi-simple{max-width:none!important;width:100%!important;padding:0!important;margin:0!important;background:transparent!important;min-height:100vh!important}
    *,*::before,*::after{box-sizing:border-box}

    :root{--navy:#061b43;--blue:#1769c2;--ink:#10213b}

    /* ── Entrance animations ─────────────────────────────────────────── */
    @keyframes fade-up   {from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}
    @keyframes fade-in   {from{opacity:0}to{opacity:1}}
    @keyframes spin      {to{transform:rotate(360deg)}}
    @keyframes pulse-dot {0%,100%{opacity:1}50%{opacity:.35}}

    .hero-badge    {animation:fade-up 0.55s cubic-bezier(.16,1,.3,1) both}
    .hero-title    {animation:fade-up 0.65s cubic-bezier(.16,1,.3,1) 0.10s both}
    .hero-desc     {animation:fade-up 0.65s cubic-bezier(.16,1,.3,1) 0.18s both}
    .hero-programmes{animation:fade-up 0.65s cubic-bezier(.16,1,.3,1) 0.24s both}
    .impact-wrap   {animation:fade-up 0.65s cubic-bezier(.16,1,.3,1) 0.30s both}
    .auth-box      {animation:fade-up 0.70s cubic-bezier(.16,1,.3,1) 0.12s both}

    /* ── Shell ───────────────────────────────────────────────────────── */
    .auth-shell{display:flex;min-height:100vh;width:100vw;font-family:system-ui, -apple-system, Segoe UI, Roboto, Helvetica Neue, Arial, sans-serif"Segoe UI",sans-serif;-webkit-font-smoothing:antialiased;color:var(--ink)}

    /* ── Hero (left) ─────────────────────────────────────────────────── */
    .auth-hero{flex:0 0 calc(58% + 100px);position:relative;overflow:hidden;overflow-y:auto;color:#fff;scrollbar-width:none}
    .auth-hero::-webkit-scrollbar{display:none}
    .hero-bg{position:fixed;left:0;top:0;width:calc(58% + 100px);height:100vh;background:url('{{ asset('images/mnch-mentorship-login-hero.webp') }}') center 40%/cover no-repeat}
    .hero-gradient{position:fixed;left:0;top:0;width:calc(58% + 100px);height:100vh;background:linear-gradient(90deg,rgba(4,22,59,.96) 0%,rgba(5,35,77,.88) 34%,rgba(7,48,102,.55) 65%,rgba(7,48,102,.24) 100%),linear-gradient(0deg,rgba(3,17,47,.9),rgba(3,17,47,.15) 48%)}

    .hero-content{position:relative;z-index:2;display:flex;flex-direction:column;min-height:100vh;padding:clamp(28px,4.2vw,64px) clamp(30px,4.6vw,72px) 28px;width:100%;box-sizing:border-box}

    .hero-badge{align-self:flex-start;display:inline-flex;align-items:center;padding:.4rem;border:1px solid rgba(255,255,255,.32);border-radius:999px;background:rgba(255,255,255,.12);backdrop-filter:blur(10px);margin-bottom:1.5rem;box-shadow:0 1px 0 rgba(255,255,255,.45) inset,0 -1px 2px rgba(0,0,0,.22) inset,0 6px 14px rgba(3,17,47,.35),0 1px 0 rgba(255,255,255,.08)}
    .hero-badge-logo-wrap{display:flex;align-items:center;justify-content:center;height:44px;padding:0 .9rem;border-radius:999px;background:#fff;box-shadow:0 1px 0 rgba(255,255,255,.9) inset,0 1px 3px rgba(0,0,0,.35),0 0 0 1px rgba(255,255,255,.55);flex-shrink:0}
    .hero-badge-logo{height:28px;width:auto;object-fit:contain;display:block}
    .hero-badge-text{line-height:1}
    .hero-dot{width:7px;height:7px;border-radius:50%;background:#70b7ff;box-shadow:0 0 10px #70b7ff;animation:pulse-dot 2s ease-in-out infinite;flex-shrink:0}

    .hero-message{margin:auto 0;max-width:640px;padding:20px 0}
    .hero-overline{margin:0 0 .7rem;color:#98c7ff;text-transform:uppercase;letter-spacing:.14em;font-size:.72rem;font-weight:800}
    .hero-title{margin:0;font-size:clamp(2rem,3.6vw,3.4rem);line-height:1.04;letter-spacing:-.03em;font-weight:800}
    .hero-desc{max-width:36rem;margin:1.15rem 0 0;color:rgba(255,255,255,.8);font-size:.92rem;line-height:1.65}
    .hero-programmes{display:flex;flex-wrap:wrap;gap:9px;margin-top:1.35rem}
    .programme{display:inline-flex;align-items:center;gap:8px;padding:.5rem .8rem;border:1px solid rgba(255,255,255,.2);border-radius:999px;background:rgba(4,22,59,.36);backdrop-filter:blur(9px);font-size:.72rem;font-weight:700}
    .programme::before{content:"";width:7px;height:7px;border-radius:50%;background:currentColor}
    .maternal{color:#f090b4}
    .newborn{color:#c8a0f0}
    .child{color:#a8d77e}

    .impact-wrap{width:100%;margin-top:auto}
    .impact-heading{display:flex;align-items:center;justify-content:space-between;margin-bottom:.7rem;color:rgba(255,255,255,.75);font-size:.62rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
    .why-link{border:0;background:transparent;padding:0;color:#fff;text-decoration:none;letter-spacing:.02em;text-transform:none;font-size:.72rem;font-weight:700;cursor:pointer;font-family:inherit}
    .why-link:hover{text-decoration:underline}
    .impact-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}
    .impact-card{min-height:78px;display:flex;align-items:center;gap:10px;padding:.85rem;border:1px solid rgba(255,255,255,.14);border-radius:13px;background:rgba(255,255,255,.09);backdrop-filter:blur(14px)}
    .impact-icon{flex:0 0 26px;width:26px;height:26px;display:grid;place-items:center;border-radius:8px;background:rgba(126,190,255,.16);color:#9dccff;font-weight:900;font-size:.85rem}
    .impact-card strong,.impact-card span{display:block}
    .impact-card strong{font-size:1.4rem;line-height:1;letter-spacing:-.02em}
    .impact-card div span{margin-top:.35rem;color:rgba(255,255,255,.6);font-size:.58rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;white-space:nowrap}
    .hero-supported{margin:.9rem 0 0;color:rgba(255,255,255,.45);font-size:.62rem;text-align:center}
    .hero-supported b{color:rgba(255,255,255,.75)}

    /* ── "Why this matters" popup ────────────────────────────────────── */
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
    .crisis-modal-cta{width:100%;padding:.85rem 1.25rem;background:linear-gradient(135deg,#0f57ac,#196fd1);color:#fff;border:none;border-radius:11px;cursor:pointer;font-family:inherit;font-size:.86rem;font-weight:700;transition:all .2s;box-shadow:0 4px 16px rgba(23,105,194,.28)}
    .crisis-modal-cta:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(23,105,194,.36)}
    .crisis-modal-cta:active{transform:translateY(0)}
    @media(max-width:520px){
        .crisis-modal{padding:1.5rem 1.35rem 1.35rem}
        .crisis-modal-stats{flex-direction:column;gap:.85rem}
    }

    /* ── Right panel ─────────────────────────────────────────────────── */
    .auth-right{flex:1;position:relative;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#f7f9fc;background-image:radial-gradient(circle at 1px 1px,rgba(23,105,194,.07) 1px,transparent 0);background-size:22px 22px;overflow-y:auto;padding:40px clamp(20px,2.5vw,36px) 40px clamp(30px,4vw,70px)}
    .auth-box{position:relative;z-index:2;width:calc(100% + 50px);max-width:600px;margin-left:clamp(-64px,-4vw,-24px);padding:2.1rem 2.35rem 1.6rem;border:1px solid rgba(165,181,202,.45);border-radius:22px;background:rgba(255,255,255,.97);box-shadow:0 28px 70px rgba(4,25,65,.18),0 4px 16px rgba(4,25,65,.06)}

    /* ── MoH Logo ────────────────────────────────────────────────────── */
    .moh-logo-wrap{display:flex;align-items:center;justify-content:flex-start;padding-bottom:1.1rem;margin-bottom:1.35rem;border-bottom:1px solid #e7edf5}
    .moh-logo-img{height:auto;max-height:60px;width:auto;max-width:100%;object-fit:contain;object-position:left center;display:block}

    /* ── Auth headings ───────────────────────────────────────────────── */
    .auth-intro{margin:0 0 1.4rem}
    .login-kicker{margin:0 0 .5rem;color:var(--blue);font-size:.65rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
    .auth-h1{margin:0;color:#101b2c;font-size:1.6rem;font-weight:800;line-height:1.15;letter-spacing:-.03em}
    .auth-sub{margin:.55rem 0 0;color:#6b7789;font-size:.82rem;line-height:1.55}

    /* ── Submit button ───────────────────────────────────────────────── */
    .auth-btn{width:100%;display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.85rem 1.5rem;margin-top:1.1rem;background:linear-gradient(135deg,#0f57ac,#196fd1);color:#fff;border:none;border-radius:12px;cursor:pointer;font-family:inherit;font-size:.9rem;font-weight:750;letter-spacing:-.01em;transition:all .2s;box-shadow:0 11px 22px rgba(23,105,194,.24)}
    .auth-btn:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(23,105,194,.34)}
    .auth-btn:active{transform:translateY(0);box-shadow:0 2px 8px rgba(23,105,194,.22)}
    .auth-btn:disabled{opacity:.6;cursor:wait;transform:none!important}
    .auth-btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .btn-idle{display:flex;align-items:center;gap:.4rem}
    .btn-loading{display:none;align-items:center;gap:.4rem}

    /* ── Footer links ────────────────────────────────────────────────── */
    .login-footer{display:flex;justify-content:flex-end;margin-top:.35rem;margin-bottom:.1rem}
    .login-footer-center{justify-content:center;margin-top:1.1rem}
    .forgot-link{font-size:.76rem;font-weight:700;color:var(--blue);text-decoration:none;transition:color .15s}
    .forgot-link:hover{color:#0d3a8e}

    /* ── Secure-access footer strip ──────────────────────────────────── */
    .secure-strip{margin:1.4rem -2.35rem -1.6rem;padding:.9rem 1.5rem;border-radius:0 0 21px 21px;background:#f4f8fd;color:#64748b;font-size:.62rem;font-weight:650;text-align:center}
    .secure-strip span{display:inline-grid;place-items:center;width:16px;height:16px;margin-right:.4rem;border-radius:50%;background:#dcedff;color:var(--blue)}

    /* ── Mobile "why this matters" ────────────────────────────────────── */
    .mobile-matters{display:none}
    .mobile-matters summary{color:var(--blue);font-weight:750;cursor:pointer;font-size:.78rem}

    /* ── Spinner ─────────────────────────────────────────────────────── */
    .spin{animation:spin .8s linear infinite}

    /* ── Filament input overrides ────────────────────────────────────── */
    .auth-box .fi-fo-field-wrp{margin-bottom:.35rem}
    .auth-box .fi-fo-field-wrp-label label{font-size:.78rem!important;font-weight:700!important;color:#26364e!important;margin-bottom:.4rem!important}
    .auth-box .fi-input-wrp{
        height:auto!important;
        min-height:49px!important;
        border:1px solid #c3cfdd!important;
        border-radius:12px!important;
        background:#fff!important;
        box-shadow:0 1px 2px rgba(15,35,70,.05)!important;
        transition:border-color .18s,box-shadow .18s!important;
    }
    .auth-box .fi-input-wrp:focus-within{
        border-color:var(--blue)!important;
        box-shadow:0 0 0 4px rgba(23,105,194,.11)!important;
    }

    .auth-box .fi-input-wrp-prefix{
        border-inline-end:none!important;
        border-right:none!important;
        padding-inline-start:.8rem!important;
        padding-inline-end:.25rem!important;
        gap:.25rem!important;
    }
    .auth-box .fi-input-wrp-icon{
        width:1rem!important;
        height:1rem!important;
        color:#9aa7b7!important;
        flex-shrink:0!important;
    }
    .auth-box .fi-input{padding-inline:.9rem!important;font-size:.88rem!important;color:#16243a!important;min-height:47px}
    .auth-box .fi-input-wrp-prefix ~ div .fi-input{padding-inline-start:.35rem!important}
    .auth-box textarea.fi-input{padding-block:.7rem!important;min-height:96px}
    .auth-box select.fi-select-input{padding-inline:.9rem 2.2rem!important;min-height:47px}
    .auth-box .fi-fo-text-input .fi-input::placeholder{color:#a2adbb!important}

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
        color:#9aa7b7!important;
        transition:color .15s,background .15s!important;
    }
    .auth-box .fi-input-wrp-suffix .fi-ac-action button:hover,
    .auth-box .fi-input-wrp-suffix button:hover{
        color:var(--blue)!important;
        background:rgba(23,105,194,.08)!important;
    }
    .auth-box .fi-input-wrp-suffix svg{
        width:1rem!important;
        height:1rem!important;
    }

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

    .auth-box .fi-fo-checkbox label{font-size:.78rem!important;color:#58677b!important;font-weight:500!important}
    .auth-box .fi-checkbox-input{accent-color:var(--blue)!important}

    /* ── Responsive ──────────────────────────────────────────────────── */
    @media(max-width:1100px){
        html,body{overflow:auto!important}
        .auth-shell{flex-wrap:wrap;height:auto;min-height:auto;align-content:flex-start}
        .auth-hero{display:block;flex:0 0 100%;overflow-y:visible;min-height:auto}
        .hero-bg,.hero-gradient{position:absolute;width:100%;height:100%}
        .hero-content{min-height:480px;padding:1.5rem 1.75rem}
        .hero-title{font-size:2.2rem}
        .impact-grid{grid-template-columns:repeat(2,1fr)}
        .hero-supported{display:none}
        .auth-right{flex:0 0 100%;height:auto;min-height:auto;padding:0 1.25rem 1.75rem}
        .auth-box{margin:-24px 0 0;width:100%}
    }
    @media(max-width:780px){
        .hero-content{padding:1.25rem 1.1rem 1rem}
        .hero-title{font-size:1.8rem}
        .hero-desc{font-size:.8rem}
        .impact-card{min-height:auto;display:block;text-align:center;padding:.6rem .4rem}
        .impact-icon{display:none}
        .impact-card strong{font-size:1.1rem}
        .impact-card div span{font-size:.5rem;white-space:normal}
        .auth-box{padding:1.6rem 1.4rem 1.25rem;border-radius:18px}
        .secure-strip{margin:1.2rem -1.4rem -1.25rem;border-radius:0 0 17px 17px}
        .mobile-matters{display:block;width:100%;margin-top:1rem;padding:0 .5rem;color:#617086;font-size:.72rem;text-align:center}
    }
    /* ── Signed-out-for-inactivity notice ────────────────────────────── */
    .auth-notice{display:flex;align-items:flex-start;gap:.65rem;margin:0 0 1.2rem;padding:.8rem .95rem;border:1px solid #f3d98b;border-radius:12px;background:#fef3c7;color:#6b4a00;font-size:.8rem;line-height:1.5}
    .auth-notice svg{flex:0 0 18px;width:18px;height:18px;margin-top:1px;stroke:#b45309;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    /* ── Phones: short photo banner + form card, app-like ───────────── */
    @media(max-width:780px){
        .auth-shell{display:block}
        .auth-hero{display:block;position:relative;overflow:hidden;min-height:0;width:100%!important;max-width:none!important;flex:none!important}
        .hero-bg{position:absolute;inset:0;width:100%;height:100%;background-position:center 22%;background-size:cover}
        .hero-gradient{position:absolute;inset:0;width:100%;height:100%;background:linear-gradient(180deg,rgba(4,22,59,.55) 0%,rgba(4,22,59,.20) 38%,rgba(4,22,59,.88) 100%)}
        .hero-content{min-height:0!important;padding:calc(.9rem + env(safe-area-inset-top)) 1.1rem 3.4rem!important}
        .hero-badge{margin-bottom:.7rem;padding:.3rem}
        .hero-badge-logo-wrap{height:38px;padding:0 .75rem}
        .hero-badge-logo{height:24px}
        .hero-message{margin:2.6rem 0 0;padding:0;max-width:none}
        .hero-overline{font-size:.64rem;margin-bottom:.35rem}
        .hero-title{font-size:1.55rem;line-height:1.1}
        .hero-desc,.hero-programmes,.hero-tips,.hero-supported,.impact-heading{display:none!important}
        .impact-wrap{margin-top:.9rem}
        .impact-grid{grid-template-columns:repeat(4,1fr);gap:6px}
        .impact-card{display:block;min-height:0;padding:.5rem .25rem;text-align:center;border-radius:11px}
        .impact-icon{display:none}
        .impact-card strong{font-size:1.02rem}
        .impact-card div span{font-size:.47rem;white-space:normal;margin-top:.25rem}
        .auth-right{overflow:visible!important;display:block;min-height:0;padding:0 .9rem calc(1.5rem + env(safe-area-inset-bottom));background:#f4f7fb}
        .auth-box{position:relative;z-index:6;width:100%!important;max-width:none!important;margin:-2.2rem 0 0!important;padding:1.4rem 1.15rem 1.15rem;border-radius:22px;box-shadow:0 20px 40px -24px rgba(8,30,70,.5)}
        .moh-logo-wrap{display:none}
        .secure-strip{margin:1.1rem -1.15rem -1.15rem;border-radius:0 0 21px 21px}
        .mobile-matters{display:block;text-align:center;margin-top:1rem}
        /* inputs: 16px stops iOS zooming on focus, 48px+ tap targets */
        .auth-box .fi-input{font-size:16px!important}
        .auth-btn{min-height:52px}
        html,body{overflow:auto!important}
    }
</style>

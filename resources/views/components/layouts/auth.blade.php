<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="height:100%">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'MNCH') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <style>
            /* ═══════════════════════════════════════════════════════
               RESET
            ═══════════════════════════════════════════════════════ */
            *, *::before, *::after {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            html, body {
                height: 100%;
                width: 100%;
                overflow: hidden;
                font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, sans-serif;
                -webkit-font-smoothing: antialiased;
                background: #f8fafc;
                color: #0f172a;
            }


            /* ═══════════════════════════════════════════════════════
               LAYOUT SHELL
            ═══════════════════════════════════════════════════════ */
            .auth-shell {
                display: flex;
                height: 100vh;
                width: 100vw;
                overflow: hidden;
            }


            /* ═══════════════════════════════════════════════════════
               LEFT HERO PANEL — 70%
            ═══════════════════════════════════════════════════════ */
            .auth-hero {
                position: relative;
                width: 70%;
                min-height: 100vh;
                flex-shrink: 0;
                overflow: hidden;
            }

            .hero-bg {
                position: absolute;
                inset: 0;
                background-image: url('https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=1600&q=85&auto=format&fit=crop');
                background-size: cover;
                background-position: center;
                transform: scale(1.04);
                transition: transform 12s ease;
            }
            .auth-hero:hover .hero-bg {
                transform: scale(1.0);
            }

            .hero-gradient {
                position: absolute;
                inset: 0;
                background: linear-gradient(
                    155deg,
                    rgba(30, 58, 138, 0.90) 0%,
                    rgba(79, 70, 229, 0.85) 45%,
                    rgba(109, 40, 217, 0.78) 75%,
                    rgba(15, 23, 42, 0.70) 100%
                    );
            }

            .hero-content {
                position: relative;
                z-index: 10;
                display: flex;
                flex-direction: column;
                justify-content: center;
                height: 100%;
                padding: 4rem 5rem;
                color: #fff;
            }

            .hero-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                background: rgba(255, 255, 255, 0.12);
                border: 1px solid rgba(255, 255, 255, 0.22);
                border-radius: 100px;
                padding: 0.4rem 1.1rem;
                font-size: 0.8rem;
                font-weight: 600;
                letter-spacing: 0.04em;
                margin-bottom: 2.5rem;
                width: fit-content;
                backdrop-filter: blur(10px);
            }

            .hero-dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #4ade80;
                box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.3);
                animation: pulse-dot 2.2s ease-in-out infinite;
            }

            @keyframes pulse-dot {
                0%, 100% {
                    box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.30);
                }
                50%       {
                    box-shadow: 0 0 0 8px rgba(74, 222, 128, 0.10);
                }
            }

            .hero-title {
                font-size: clamp(2.2rem, 3.2vw, 3.6rem);
                font-weight: 800;
                line-height: 1.1;
                letter-spacing: -0.03em;
                margin-bottom: 1.5rem;
            }
            .hero-title em {
                font-style: normal;
                color: #a5f3fc;
            }

            .hero-desc {
                font-size: 1.05rem;
                line-height: 1.8;
                color: rgba(255, 255, 255, 0.78);
                max-width: 44ch;
                margin-bottom: 3rem;
            }

            .hero-stats {
                display: flex;
                gap: 3.5rem;
                padding-top: 2.5rem;
                border-top: 1px solid rgba(255, 255, 255, 0.18);
            }

            .stat-val {
                font-size: 1.9rem;
                font-weight: 800;
                letter-spacing: -0.035em;
                line-height: 1;
                margin-bottom: 0.3rem;
            }

            .stat-lbl {
                font-size: 0.75rem;
                color: rgba(255, 255, 255, 0.58);
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.07em;
            }


            /* ═══════════════════════════════════════════════════════
               RIGHT FORM PANEL — 30%
            ═══════════════════════════════════════════════════════ */
            .auth-right {
                width: 30%;
                height: 100vh;
                overflow-y: auto;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem 1.75rem;
                background: #ffffff;
            }

            .auth-box {
                width: 100%;
                max-width: 360px;
            }


            /* ═══════════════════════════════════════════════════════
               AUTH ICON MARK
            ═══════════════════════════════════════════════════════ */
            .auth-mark {
                width: 50px;
                height: 50px;
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 1.75rem;
                box-shadow: 0 4px 16px rgba(79, 70, 229, 0.38);
            }
            .auth-mark svg {
                width: 24px;
                height: 24px;
                stroke: #ffffff;
                fill: none;
                stroke-width: 2;
                stroke-linecap: round;
                stroke-linejoin: round;
            }


            /* ═══════════════════════════════════════════════════════
               STEP INDICATOR
            ═══════════════════════════════════════════════════════ */
            .steps {
                display: flex;
                align-items: center;
                margin-bottom: 1.75rem;
            }

            .step {
                display: flex;
                align-items: center;
                gap: 0.4rem;
                font-size: 0.72rem;
                font-weight: 500;
                color: #94a3b8;
                white-space: nowrap;
                flex-shrink: 0;
            }
            .step.active {
                color: #4f46e5;
                font-weight: 700;
            }
            .step.done {
                color: #10b981;
                font-weight: 600;
            }

            .step-num {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                background: #e2e8f0;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.68rem;
                font-weight: 700;
                flex-shrink: 0;
                color: #64748b;
                transition: background 0.2s, box-shadow 0.2s;
            }
            .step.active .step-num {
                background: #4f46e5;
                color: #ffffff;
                box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.18);
            }
            .step.done .step-num {
                background: #10b981;
                color: #ffffff;
            }

            .step-connector {
                flex: 1;
                height: 2px;
                background: #e2e8f0;
                margin: 0 0.4rem;
                min-width: 14px;
                transition: background 0.3s;
            }
            .step-connector.done {
                background: #10b981;
            }


            /* ═══════════════════════════════════════════════════════
               PAGE HEADINGS
            ═══════════════════════════════════════════════════════ */
            .auth-h1 {
                font-size: 1.45rem;
                font-weight: 800;
                color: #0f172a;
                letter-spacing: -0.025em;
                margin-bottom: 0.4rem;
                line-height: 1.2;
            }

            .auth-sub {
                font-size: 0.82rem;
                color: #64748b;
                line-height: 1.65;
                margin-bottom: 1.4rem;
            }


            /* ═══════════════════════════════════════════════════════
               INFO BOX
            ═══════════════════════════════════════════════════════ */
            .auth-infobox {
                display: flex;
                align-items: flex-start;
                gap: 0.6rem;
                background: #eff6ff;
                border: 1px solid #bfdbfe;
                border-radius: 10px;
                padding: 0.75rem 0.9rem;
                margin-bottom: 1.25rem;
                font-size: 0.76rem;
                color: #1e40af;
                line-height: 1.6;
            }
            .auth-infobox svg {
                width: 14px;
                height: 14px;
                stroke: #3b82f6;
                fill: none;
                stroke-width: 2;
                stroke-linecap: round;
                flex-shrink: 0;
                margin-top: 1px;
            }


            /* ═══════════════════════════════════════════════════════
               SUBMIT BUTTON
            ═══════════════════════════════════════════════════════ */
            .auth-btn {
                width: 100%;
                padding: 0.8rem 1.25rem;
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                color: #ffffff;
                font-family: inherit;
                font-weight: 700;
                font-size: 0.88rem;
                letter-spacing: 0.01em;
                border: none;
                border-radius: 10px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.45rem;
                box-shadow: 0 4px 16px rgba(79, 70, 229, 0.40);
                transition: opacity 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
                margin-top: 1.1rem;
            }
            .auth-btn:hover {
                opacity: 0.91;
                transform: translateY(-1px);
                box-shadow: 0 7px 22px rgba(79, 70, 229, 0.46);
            }
            .auth-btn:active {
                transform: translateY(0px);
                box-shadow: 0 3px 10px rgba(79, 70, 229, 0.35);
            }
            .auth-btn svg {
                width: 14px;
                height: 14px;
                stroke: currentColor;
                fill: none;
                stroke-width: 2.5;
                stroke-linecap: round;
                stroke-linejoin: round;
                flex-shrink: 0;
            }

            /* Spinner — hidden by default, shown by Livewire during request */
            .btn-loading {
                display: none;
                align-items: center;
                gap: 0.45rem;
            }
            .btn-idle {
                display: flex;
                align-items: center;
                gap: 0.45rem;
            }


            /* ═══════════════════════════════════════════════════════
               SPIN ANIMATION
            ═══════════════════════════════════════════════════════ */
            @keyframes spin {
                from {
                    transform: rotate(0deg);
                }
                to   {
                    transform: rotate(360deg);
                }
            }
            .spin {
                animation: spin 0.85s linear infinite;
            }


            /* ═══════════════════════════════════════════════════════
               BACK LINK
            ═══════════════════════════════════════════════════════ */
            .auth-back {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                margin-top: 1.2rem;
                font-size: 0.8rem;
                font-weight: 500;
                color: #64748b;
                text-decoration: none;
                transition: color 0.15s ease;
            }
            .auth-back:hover {
                color: #0f172a;
            }
            .auth-back svg {
                width: 14px;
                height: 14px;
                stroke: currentColor;
                fill: none;
                stroke-width: 2.5;
                stroke-linecap: round;
                stroke-linejoin: round;
            }
            .auth-back-center {
                justify-content: center;
                display: flex;
            }


            /* ═══════════════════════════════════════════════════════
               STEP 2 — INBOX PANEL
            ═══════════════════════════════════════════════════════ */
            .inbox-panel {
                text-align: center;
                padding: 0.5rem 0;
            }

            .inbox-icon {
                width: 64px;
                height: 64px;
                background: #eff6ff;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1.25rem auto;
                border: 2px solid #dbeafe;
            }
            .inbox-icon svg {
                width: 28px;
                height: 28px;
                stroke: #4f46e5;
                fill: none;
                stroke-width: 1.75;
                stroke-linecap: round;
                stroke-linejoin: round;
            }

            .inbox-title {
                font-size: 1.35rem;
                font-weight: 800;
                color: #0f172a;
                letter-spacing: -0.022em;
                margin-bottom: 0.45rem;
            }

            .inbox-sub {
                font-size: 0.8rem;
                color: #64748b;
                margin-bottom: 0.2rem;
            }

            .inbox-email {
                font-size: 0.88rem;
                font-weight: 700;
                color: #1e293b;
                margin-bottom: 1.4rem;
            }

            .inbox-hint {
                font-size: 0.76rem;
                color: #94a3b8;
                line-height: 1.65;
                margin-bottom: 1.4rem;
            }
            .inbox-hint button {
                background: none;
                border: none;
                cursor: pointer;
                color: #4f46e5;
                font-family: inherit;
                font-weight: 600;
                font-size: 0.76rem;
                text-decoration: underline;
                text-underline-offset: 2px;
                padding: 0;
                transition: color 0.15s;
            }
            .inbox-hint button:hover {
                color: #3730a3;
            }


            /* ═══════════════════════════════════════════════════════
               PASSWORD STRENGTH HINTS
            ═══════════════════════════════════════════════════════ */
            .pw-hints {
                margin-top: 0.65rem;
                margin-bottom: 0.25rem;
                display: flex;
                flex-direction: column;
                gap: 0.3rem;
            }

            .pw-hint {
                display: flex;
                align-items: center;
                gap: 0.4rem;
                font-size: 0.73rem;
                font-weight: 500;
                color: #94a3b8;
                line-height: 1;
            }
            .pw-hint svg {
                width: 12px;
                height: 12px;
                stroke: #cbd5e1;
                fill: none;
                stroke-width: 2.5;
                stroke-linecap: round;
                stroke-linejoin: round;
                flex-shrink: 0;
            }


            /* ═══════════════════════════════════════════════════════
               FILAMENT FORM — COMPLETE OVERRIDE
               Scoped tightly so it never leaks to admin panel
            ═══════════════════════════════════════════════════════ */

            /* Field wrapper spacing */
            .auth-box .fi-fo-field-wrp {
                margin-bottom: 0.9rem;
            }
            .auth-box .fi-fo-field-wrp:last-child {
                margin-bottom: 0;
            }

            /* Label */
            .auth-box .fi-fo-field-wrp-label label,
            .auth-box .fi-label-wrp label {
                font-size: 0.78rem !important;
                font-weight: 600 !important;
                color: #374151 !important;
                margin-bottom: 0.3rem !important;
                display: block !important;
            }

            /* Required asterisk */
            .auth-box .fi-fo-field-wrp-label .fi-required,
            .auth-box sup.fi-required {
                color: #ef4444 !important;
                font-size: 0.7rem !important;
            }

            /* ── Input wrapper — the outer border container ── */
            .auth-box .fi-input-wrp {
                display: flex !important;
                align-items: center !important;
                width: 100% !important;
                background: #ffffff !important;
                border: 1.5px solid #e2e8f0 !important;
                border-radius: 10px !important;
                overflow: hidden !important;
                transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
                /* Remove any inner shadows Filament adds */
                box-shadow: none !important;
            }
            .auth-box .fi-input-wrp:focus-within {
                border-color: #4f46e5 !important;
                box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.13) !important;
            }

            /* ── The actual <input> element ── */
            .auth-box .fi-input-wrp input.fi-input,
            .auth-box input.fi-input {
                flex: 1 !important;
                min-width: 0 !important;
                width: 100% !important;
                height: 42px !important;
                padding: 0 0.85rem !important;
                font-family: inherit !important;
                font-size: 0.85rem !important;
                font-weight: 400 !important;
                color: #0f172a !important;
                background: transparent !important;
                border: none !important;
                outline: none !important;
                box-shadow: none !important;
            }
            .auth-box .fi-input-wrp input.fi-input::placeholder {
                color: #94a3b8 !important;
            }
            /* Disabled input (e.g. email field on reset page) */
            .auth-box .fi-input-wrp input.fi-input:disabled {
                background: #f8fafc !important;
                color: #64748b !important;
                cursor: not-allowed !important;
            }

            /* ── Revealable password toggle button ── */
            /* Target the suffix action button Filament renders */
            .auth-box .fi-input-wrp button,
            .auth-box .fi-input-suffix-action {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 38px !important;
                height: 38px !important;
                min-width: 38px !important;
                padding: 0 !important;
                margin-right: 2px !important;
                background: none !important;
                border: none !important;
                border-radius: 7px !important;
                cursor: pointer !important;
                color: #94a3b8 !important;
                transition: color 0.15s, background 0.15s !important;
                flex-shrink: 0 !important;
            }
            .auth-box .fi-input-wrp button:hover,
            .auth-box .fi-input-suffix-action:hover {
                color: #4f46e5 !important;
                background: rgba(79, 70, 229, 0.07) !important;
            }

            /* Force the SVG icon inside the button to be exactly 16×16 */
            .auth-box .fi-input-wrp button svg,
            .auth-box .fi-input-suffix-action svg {
                width: 16px !important;
                height: 16px !important;
                min-width: 16px !important;
                min-height: 16px !important;
                max-width: 16px !important;
                max-height: 16px !important;
                stroke-width: 2 !important;
                display: block !important;
            }

            /* Hide the text "Show password" / "Hide password" — keep icon only */
            .auth-box .fi-input-wrp button span:not(:has(svg)):not(:empty),
            .auth-box .fi-input-suffix-action span:not(:has(svg)):not(:empty) {
                display: none !important;
            }
            /* Fallback for browsers that don't support :has() */
            .auth-box .fi-input-wrp button > span.fi-icon ~ span,
            .auth-box .fi-input-wrp button > span:not(.fi-icon) {
                font-size: 0 !important;
                width: 0 !important;
                overflow: hidden !important;
            }

            /* ── Validation error messages ── */
            .auth-box .fi-fo-field-wrp-error-message,
            .auth-box p[class*="fi-fo-field-wrp-error"] {
                font-size: 0.73rem !important;
                color: #ef4444 !important;
                margin-top: 0.3rem !important;
                display: flex !important;
                align-items: center !important;
                gap: 0.25rem !important;
            }

            /* ── Checkbox (remember me on login) ── */
            .auth-box .fi-checkbox-input {
                width: 15px !important;
                height: 15px !important;
                border-radius: 4px !important;
                border: 1.5px solid #d1d5db !important;
                accent-color: #4f46e5 !important;
            }
            .auth-box .fi-fo-checkbox .fi-fo-field-wrp-label label {
                font-size: 0.78rem !important;
                font-weight: 400 !important;
                color: #64748b !important;
            }

            /* ── Remove Filament's default ring/shadow on inputs ── */
            .auth-box .fi-input:focus,
            .auth-box .fi-input-wrp input:focus {
                --tw-ring-shadow: none !important;
                --tw-ring-offset-shadow: none !important;
                box-shadow: none !important;
            }

            /* ── Filament form section/grid wrappers — flatten ── */
            .auth-box .fi-form,
            .auth-box .fi-fo-component-ctn,
            .auth-box .fi-fo-grid,
            .auth-box .grid {
                display: block !important;
            }

            /* ── Filament notification toast icon ───────────────────── */
            .fi-no-notification-icon,
            .fi-no-notification-icon svg,
            [class*="fi-no"] svg,
            .fi-notifications svg {
                width: 20px !important;
                height: 20px !important;
                min-width: 20px !important;
                min-height: 20px !important;
                max-width: 20px !important;
                max-height: 20px !important;
                flex-shrink: 0 !important;
            }

            /* Constrain the icon wrapper circle too */
            .fi-no-notification-icon,
            [class*="fi-no-icon"] {
                width: 32px !important;
                height: 32px !important;
                min-width: 32px !important;
                min-height: 32px !important;
                border-radius: 50% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                flex-shrink: 0 !important;
            }


            /* Broad catch-all for any large icon inside toasts */
            [class*="fi-no"] svg,
            [class*="fi-notification"] svg {
                width: 20px !important;
                height: 20px !important;
            }

            /* ═══════════════════════════════════════════════════════
               RESPONSIVE
            ═══════════════════════════════════════════════════════ */
            @media (max-width: 900px) {
                .auth-hero {
                    display: none;
                }
                .auth-right {
                    width: 100%;
                    padding: 2rem 1.5rem;
                }
            }
        </style>

    @filamentStyles
    @livewireStyles
    </head>
    <body style="height:100%; overflow:hidden;">
    <div style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;width:360px;max-width:calc(100vw - 3rem);pointer-events:none;">
        <div style="pointer-events:auto;">
            @livewire('notifications')
        </div>
    </div>

    {{ $slot }}
    @filamentScripts
    @livewireScripts
    @stack('scripts')
</body>
</html>
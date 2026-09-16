<div class="fi-simple-page">
    <div class="auth-shell">

        {{-- LEFT --}}
        <div class="auth-hero">
            <div class="hero-bg"></div>
            <div class="hero-gradient"></div>
            <div class="hero-content">
                <div class="hero-badge"><span class="hero-dot"></span> Ministry of Health · Kenya</div>
                <h2 class="hero-title"><em>MNCH</em> Mentorship<br>Platform</h2>
                <p class="hero-desc">Transforming maternal, newborn and child health outcomes through structured, evidence-based mentorship across Kenya's health facilities.</p>
                <div class="hero-stats">
                    <div><div class="stat-val">47</div><div class="stat-lbl">Counties</div></div>
                    <div><div class="stat-val">2,400+</div><div class="stat-lbl">Health workers</div></div>
                    <div><div class="stat-val">580+</div><div class="stat-lbl">Facilities</div></div>
                </div>
            </div>
        </div>

    {{-- RIGHT --}}
        <div class="auth-right">
            <div class="auth-box">

                <div class="auth-mark">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                </div>

            {{-- Steps --}}
                <div class="steps">
                    <div class="step done">
                        <span class="step-num">
                            <svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:#fff;fill:none;stroke-width:3;stroke-linecap:round;stroke-linejoin:round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        <span>Email sent</span>
                    </div>
                    <div class="step-connector done"></div>
                    <div class="step done">
                        <span class="step-num">
                            <svg viewBox="0 0 24 24" style="width:11px;height:11px;stroke:#fff;fill:none;stroke-width:3;stroke-linecap:round;stroke-linejoin:round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        <span>Link opened</span>
                    </div>
                    <div class="step-connector done"></div>
                    <div class="step active">
                        <span class="step-num">3</span>
                        <span>Set password</span>
                    </div>
                </div>

                <h1 class="auth-h1">Set your new password</h1>
                <p class="auth-sub">Choose a strong password to secure your account.</p>

                <form wire:submit.prevent="resetPassword">
                {{ $this->form }}

                {{-- Password strength indicator --}}
                    <div class="pw-strength" id="pw-strength-block" style="display:none;">
                        <div class="pw-bars">
                            <div class="pw-bar" id="pw-bar-1"></div>
                            <div class="pw-bar" id="pw-bar-2"></div>
                            <div class="pw-bar" id="pw-bar-3"></div>
                            <div class="pw-bar" id="pw-bar-4"></div>
                        </div>
                        <span class="pw-strength-label" id="pw-strength-label">Weak</span>
                    </div>

                    <div class="pw-hints">
                        <div class="pw-hint" id="hint-length">
                            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            At least 8 characters
                        </div>
                        <div class="pw-hint" id="hint-upper">
                            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            One uppercase letter
                        </div>
                        <div class="pw-hint" id="hint-number">
                            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            One number
                        </div>
                    </div>

                    <button type="submit" class="auth-btn">
                        <span class="btn-idle" wire:loading.remove wire:target="resetPassword">
                            Set new password &amp; sign in
                            <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </span>
                        <span class="btn-loading" wire:loading wire:target="resetPassword">
                            <svg class="spin" viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5">
                            <circle cx="12" cy="12" r="10" style="opacity:0.25"/>
                            <path d="M4 12a8 8 0 018-8" style="opacity:0.85"/>
                            </svg>
                            Updating...
                        </span>
                    </button>
                </form>

                <a href="{{ route('filament.admin.auth.login') }}" class="auth-back">
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                    Back to sign in
                </a>

            </div>
        </div>
    </div>

@push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {

        // ── Inject toggle buttons into password inputs ───────────────
        function injectToggle(inputId) {
            const input = document.getElementById(inputId);
            if (!input) return;

            const wrapper = input.closest('.fi-input-wrp') || input.parentElement;
            wrapper.style.position = 'relative';
            wrapper.style.display  = 'flex';
            wrapper.style.alignItems = 'center';

            // Remove any existing Filament suffix buttons (the broken ones)
            wrapper.querySelectorAll('button').forEach(b => b.remove());

            // Create our own toggle button
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.setAttribute('aria-label', 'Toggle password visibility');
            btn.style.cssText = `
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                min-width: 36px;
                background: none;
                border: none;
                cursor: pointer;
                color: #94a3b8;
                border-radius: 6px;
                transition: color 0.15s, background 0.15s;
                flex-shrink: 0;
                margin-right: 2px;
            `;

            const eyeOpen = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
            const eyeOff  = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;

            btn.innerHTML = eyeOpen;
            btn.isRevealed = false;

            btn.addEventListener('mouseenter', function() {
                this.style.color = '#4f46e5';
                this.style.background = 'rgba(79,70,229,0.07)';
            });
            btn.addEventListener('mouseleave', function() {
                this.style.color = '#94a3b8';
                this.style.background = 'none';
            });

            btn.addEventListener('click', function () {
                this.isRevealed = !this.isRevealed;
                input.type = this.isRevealed ? 'text' : 'password';
                this.innerHTML = this.isRevealed ? eyeOff : eyeOpen;
            });

            wrapper.appendChild(btn);
        }

        // Wait briefly for Filament to finish rendering
        setTimeout(function () {
            injectToggle('pw-new');
            injectToggle('pw-confirm');
        }, 100);

        // Re-inject after Livewire re-renders
        document.addEventListener('livewire:navigated', function () {
            setTimeout(function () {
                injectToggle('pw-new');
                injectToggle('pw-confirm');
            }, 100);
        });
    });

    // ── Password strength checker ────────────────────────────────────
    function pwStrength(val) {
        const block  = document.getElementById('pw-strength-block');
        const label  = document.getElementById('pw-strength-label');
        const bars   = [1,2,3,4].map(i => document.getElementById('pw-bar-' + i));
        const hLen   = document.getElementById('hint-length');
        const hUpper = document.getElementById('hint-upper');
        const hNum   = document.getElementById('hint-number');

        if (!block) return;

        if (val.length === 0) {
            block.style.display = 'none';
            [hLen, hUpper, hNum].forEach(h => h && h.classList.remove('met'));
            return;
        }

        block.style.display = 'block';

        // Rule checks
        const hasLen   = val.length >= 8;
        const hasUpper = /[A-Z]/.test(val);
        const hasNum   = /[0-9]/.test(val);
        const hasSpec  = /[^A-Za-z0-9]/.test(val);

        hLen   && (hasLen   ? hLen.classList.add('met')   : hLen.classList.remove('met'));
        hUpper && (hasUpper ? hUpper.classList.add('met') : hUpper.classList.remove('met'));
        hNum   && (hasNum   ? hNum.classList.add('met')   : hNum.classList.remove('met'));

        // Score 0–4
        let score = [hasLen, hasUpper, hasNum, hasSpec].filter(Boolean).length;
        if (val.length >= 12) score = Math.min(4, score + 1);

        const configs = [
            { active: 0, color: '#e2e8f0', text: '' },
            { active: 1, color: '#ef4444', text: 'Weak' },
            { active: 2, color: '#f97316', text: 'Fair' },
            { active: 3, color: '#eab308', text: 'Good' },
            { active: 4, color: '#22c55e', text: 'Strong' },
        ];
        const cfg = configs[score] || configs[0];

        bars.forEach((bar, i) => {
            if (!bar) return;
            bar.style.background = i < cfg.active ? cfg.color : '#e2e8f0';
            bar.style.opacity    = i < cfg.active ? '1' : '0.4';
        });

        label.textContent  = cfg.text;
        label.style.color  = cfg.color;
    }
                </script>
                @endpush

                <style>
        /* ── Password strength bar ─────────────────────────────── */
        .pw-strength {
            margin-top: 0.6rem;
            margin-bottom: 0.1rem;
        }
        .pw-bars {
            display: flex;
            gap: 4px;
            margin-bottom: 0.25rem;
        }
        .pw-bar {
            flex: 1;
            height: 4px;
            border-radius: 100px;
            background: #e2e8f0;
            opacity: 0.4;
            transition: background 0.3s ease, opacity 0.3s ease;
        }
        .pw-strength-label {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.03em;
            transition: color 0.3s;
        }

        /* ── Password hints ─────────────────────────────────────── */
        .pw-hints {
            margin-top: 0.55rem;
            margin-bottom: 0.1rem;
            display: flex;
            flex-direction: column;
            gap: 0.28rem;
        }
        .pw-hint {
            display: flex;
            align-items: center;
            gap: 0.38rem;
            font-size: 0.72rem;
            font-weight: 500;
            color: #94a3b8;
            transition: color 0.2s;
        }
        .pw-hint svg {
            width: 11px;
            height: 11px;
            stroke: #cbd5e1;
            fill: none;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            flex-shrink: 0;
            transition: stroke 0.2s;
        }
        /* met = rule passed */
        .pw-hint.met {
            color: #10b981;
        }
        .pw-hint.met svg {
            stroke: #10b981;
        }

        /* ── Button states ──────────────────────────────────────── */
        .btn-idle   {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-loading {
            display: none;
            align-items: center;
            gap: 0.4rem;
        }
                </style>
</div>
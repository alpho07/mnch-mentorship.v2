<div class="fi-simple-page">
    <div class="auth-shell">

        {{-- LEFT --}}
        @include('livewire.auth.partials.hero', ['overline' => 'Account recovery', 'title' => 'Reset your<br>password', 'desc' => 'We will email you a secure link to set a new password so you can get back to your mentorships.', 'tips' => [['<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>', 'Your account is linked to your email. Use the same address you registered with.'], ['<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>', "Check your spam or junk folder if you don't see the email within a few minutes."], ['<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>', 'Reset links expire after 60 minutes for your security.']]])

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

    @include('livewire.auth.partials.styles')
    @include('livewire.auth.partials.extras')
</div>

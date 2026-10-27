{{-- resources/views/livewire/auth/custom-register.blade.php --}}
<div class="fi-simple-page">
    <div class="auth-shell">

        {{-- ═══════════════════════════════════════════════════════════════
             LEFT — Hero Panel
             ═══════════════════════════════════════════════════════════════ --}}
        @include('livewire.auth.partials.hero', ['overline' => 'Join the network', 'title' => 'Create your<br>MNCH account', 'desc' => 'Register to join structured, trackable mentorship programmes at your facility, with training modules, progress tracking and a shared knowledge base.'])

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

    @include('livewire.auth.partials.styles')
    @include('livewire.auth.partials.extras')
    @include('livewire.auth.partials.wide-form')
</div>

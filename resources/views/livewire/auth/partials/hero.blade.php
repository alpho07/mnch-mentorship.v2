{{-- Shared hero for register / password reset: same look as sign-in, live platform figures. Params: overline, title (HTML), desc, tips (optional [[svg path markup, text]]) --}}
<section class="auth-hero" aria-labelledby="hero-title">
    <div class="hero-bg"></div>
    <div class="hero-gradient"></div>
    <div class="hero-content">
        <div class="hero-badge">
            <span class="hero-badge-logo-wrap"><img src="{{ asset('moh_logo.png') }}" alt="Ministry of Health — Republic of Kenya" class="hero-badge-logo"></span>
        </div>
        <div class="hero-message">
            <p class="hero-overline">{{ $overline ?? 'National MNCH Mentorship Platform' }}</p>
            <h1 id="hero-title" class="hero-title">{!! $title !!}</h1>
            <p class="hero-desc">{{ $desc }}</p>
            @if (!empty($tips))
            <div class="hero-tips">
                <div class="tip-label">Account tips</div>
                @foreach ($tips as [$icon, $text])
                <div class="tip-item"><div class="tip-icon"><svg viewBox="0 0 24 24">{!! $icon !!}</svg></div><span>{{ $text }}</span></div>
                @endforeach
            </div>
            @else
            <div class="hero-programmes">
                <span class="programme maternal">Maternal Health</span>
                <span class="programme newborn">Newborn Care</span>
                <span class="programme child">Infant &amp; Child Care</span>
            </div>
            @endif
        </div>
        <div class="impact-wrap">
            <div class="impact-heading"><span>Live nationwide impact</span></div>
            <div class="impact-grid">
                <article class="impact-card"><span class="impact-icon">✦</span><div><strong>{{ number_format($platformStats['mentorships']) }}</strong><span>Mentorships</span></div></article>
                <article class="impact-card"><span class="impact-icon">◎</span><div><strong>{{ number_format($platformStats['mentees']) }}</strong><span>Mentees enrolled</span></div></article>
                <article class="impact-card"><span class="impact-icon">+</span><div><strong>{{ number_format($platformStats['facilities']) }}</strong><span>Facilities reached</span></div></article>
                <article class="impact-card"><span class="impact-icon">⌖</span><div><strong>{{ number_format($platformStats['counties']) }}</strong><span>Counties covered</span></div></article>
            </div>
            <p class="hero-supported">Supported by <b>Division of RMNCAH</b> · County Health Departments · Development Partners</p>
        </div>
    </div>
</section>

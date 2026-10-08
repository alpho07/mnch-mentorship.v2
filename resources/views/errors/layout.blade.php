{{--
    Shared shell for every error page.

    Deliberately self-contained: no @vite, no CDN fonts, no database, session
    or auth calls — error pages must still render when those are what failed.

    Pages set these sections:
      code (required)  headline (required)  lead (required)  icon  tips (HTML <li>s)
      signin  -> show a "Sign in" button      retry -> show "Try again"
      refresh -> seconds before the page reloads itself (e.g. maintenance)
--}}
@php
    $code = trim($__env->yieldContent('code', '500'));
    $supportEmail = 'mnch@health.go.ke';
    $retryAfter = isset($exception) && method_exists($exception, 'getHeaders')
        ? (int) ($exception->getHeaders()['Retry-After'] ?? 0) : 0;
    $refresh = (int) trim($__env->yieldContent('refresh', '0'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    @if ($refresh > 0)<meta http-equiv="refresh" content="{{ $refresh }}">@endif
    <title>@yield('headline') · {{ config('app.name', 'MNCH') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        :root { --brand:#2E93D6; --brand-dark:#1D78B8; --ink:#12344D; --muted:#4A6478; --line:#D9E6F0; --soft:#EAF7FE; }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        html, body { margin: 0; min-height: 100%; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--ink); line-height: 1.55; -webkit-font-smoothing: antialiased;
            background: radial-gradient(1200px 500px at 50% -10%, var(--soft), #fff 70%);
            display: flex; flex-direction: column; min-height: 100vh;
            padding: env(safe-area-inset-top) 16px env(safe-area-inset-bottom);
        }
        header { max-width: 720px; width: 100%; margin: 0 auto; padding: 22px 0 0; display: flex; align-items: center; gap: 10px; }
        header a { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; color: var(--ink); font-weight: 700; }
        header img { height: 34px; width: auto; }
        main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 28px 0; }
        .card {
            width: 100%; max-width: 560px; background: #fff; border: 1px solid var(--line); border-radius: 22px;
            padding: 36px 28px 28px; text-align: center; box-shadow: 0 18px 50px rgba(18, 52, 77, .08);
        }
        .badge {
            width: 104px; height: 104px; margin: 0 auto 6px; border-radius: 50%; color: var(--brand);
            background: var(--soft); display: flex; align-items: center; justify-content: center;
        }
        .badge svg { width: 56px; height: 56px; }
        .code { font-size: 13px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--brand-dark); margin: 14px 0 4px; }
        h1 { font-size: clamp(24px, 5vw, 30px); line-height: 1.2; margin: 0 0 10px; letter-spacing: -.01em; }
        .lead { font-size: 16.5px; color: var(--muted); margin: 0 auto; max-width: 440px; }
        .tips { text-align: left; margin: 22px auto 0; max-width: 440px; padding: 14px 18px 14px 36px; background: #F6FAFD; border: 1px solid var(--line); border-radius: 14px; color: var(--muted); font-size: 15px; }
        .tips li { margin: 5px 0; padding-left: 2px; }
        .tips strong { color: var(--ink); }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 26px; }
        .btn {
            appearance: none; cursor: pointer; text-decoration: none; font: inherit; font-weight: 600; font-size: 15.5px;
            padding: 12px 22px; border-radius: 12px; border: 1.5px solid var(--brand); color: var(--brand-dark); background: #fff;
            min-height: 46px; display: inline-flex; align-items: center; justify-content: center; transition: background .15s, color .15s, transform .05s;
        }
        .btn:hover { background: var(--soft); }
        .btn:active { transform: translateY(1px); }
        .btn.primary { background: var(--brand); color: #fff; }
        .btn.primary:hover { background: var(--brand-dark); border-color: var(--brand-dark); }
        .btn[disabled] { opacity: .55; cursor: not-allowed; }
        .btn:focus-visible, summary:focus-visible, a:focus-visible { outline: 3px solid #9EDDFA; outline-offset: 2px; }
        .help { margin-top: 26px; font-size: 14.5px; color: var(--muted); }
        .help a { color: var(--brand-dark); font-weight: 600; }
        details { margin-top: 16px; text-align: left; font-size: 13.5px; color: var(--muted); }
        summary { cursor: pointer; text-align: center; color: #6B8296; list-style: none; padding: 6px; border-radius: 8px; }
        summary::-webkit-details-marker { display: none; }
        summary:hover { color: var(--ink); }
        details dl { margin: 10px auto 0; max-width: 360px; display: grid; grid-template-columns: auto 1fr; gap: 4px 14px; background: #F6FAFD; border: 1px solid var(--line); border-radius: 12px; padding: 12px 16px; }
        details dt { font-weight: 600; color: var(--ink); } details dd { margin: 0; word-break: break-word; }
        footer { text-align: center; font-size: 12.5px; color: #7C93A5; padding: 6px 0 22px; }
        @media (max-width: 480px) { .card { padding: 28px 18px 22px; border-radius: 18px; } .btn { width: 100%; } }
        @media (prefers-reduced-motion: no-preference) { .card { animation: rise .35s ease-out both; } @keyframes rise { from { transform: translateY(8px); } } }
    </style>
</head>
<body>
    <header>
        <a href="{{ url('/') }}" aria-label="{{ config('app.name') }} home">
            <img src="{{ asset('moh_logo.png') }}" alt="" onerror="this.style.display='none'">
            <span>MNCH Kenya</span>
        </a>
    </header>

    <main>
        <section class="card" role="alert" aria-live="polite">
            <div class="badge">@include('errors._icon', ['name' => trim($__env->yieldContent('icon', 'alert'))])</div>
            <div class="code">@yield('label', 'Error '.$code)</div>
            <h1>@yield('headline')</h1>
            <p class="lead">@yield('lead')</p>

            @hasSection('tips')
                <ul class="tips">@yield('tips')</ul>
            @endif

            <div class="actions">
                @hasSection('retry')
                    <button type="button" class="btn primary" id="retry" onclick="location.reload()" @if ($retryAfter > 0) disabled @endif>
                        <span id="retry-label">Try again</span>
                    </button>
                @endif
                @hasSection('signin')
                    <a class="btn primary" href="{{ url('/admin/login') }}">Sign in</a>
                @endif
                <a class="btn {{ $__env->hasSection('retry') || $__env->hasSection('signin') ? '' : 'primary' }}" href="{{ url('/') }}">Back to home</a>
                <button type="button" class="btn" id="back" onclick="history.back()" hidden>Go back</button>
            </div>

            <p class="help">Still stuck? Email <a href="mailto:{{ $supportEmail }}?subject={{ rawurlencode('Problem on the MNCH site (error '.$code.')') }}">{{ $supportEmail }}</a>
                and tell us what you were doing.</p>

            <details>
                <summary>Details for support</summary>
                <dl>
                    <dt>Error</dt><dd>{{ $code }}</dd>
                    <dt>Time</dt><dd>{{ now()->timezone('Africa/Nairobi')->format('d M Y, H:i:s') }} (EAT)</dd>
                    <dt>Site</dt><dd>{{ request()->getHost() }}</dd>
                </dl>
            </details>
        </section>
    </main>

    <footer>&copy; {{ date('Y') }} Ministry of Health, Kenya · MNCH Programme</footer>

    <script>
        // "Go back" only when there is somewhere to go back to.
        if (history.length > 1 && document.referrer) { document.getElementById('back').hidden = false; }
        @if ($retryAfter > 0)
        // Rate-limited: count down, then re-enable "Try again".
        (function () {
            var left = {{ min($retryAfter, 3600) }}, btn = document.getElementById('retry'), label = document.getElementById('retry-label');
            function tick() {
                if (left <= 0) { btn.disabled = false; label.textContent = 'Try again'; return; }
                label.textContent = 'Try again in ' + left + 's'; left--; setTimeout(tick, 1000);
            }
            tick();
        })();
        @endif
    </script>
</body>
</html>

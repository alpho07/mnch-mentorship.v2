{{-- Inactivity timer for signed-in users: warning, then automatic sign-out back to the front page. --}}
@auth
<script defer src="{{ asset('js/idle-timeout.js') }}?v={{ filemtime(public_path('js/idle-timeout.js')) }}"
        data-timeout="{{ config('session.idle_timeout') }}"
        data-warn="{{ config('session.idle_warning') }}"
        data-keep-alive="{{ route('session.keep-alive') }}"
        data-expire="{{ route('session.expire') }}"
        data-home="{{ url('/') }}"></script>
@endauth

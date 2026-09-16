<!DOCTYPE html>
<html lang="en" class="h-full">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Verify Your Account — MNCH Mentorship Platform</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] } } } }</script>
    </head>
    <body class="min-h-full bg-slate-50 font-sans antialiased flex items-center justify-center px-4 py-12">

    <div class="w-full max-w-md">

        {{-- Logo / Brand --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 shadow-lg mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <h2 class="text-sm font-bold uppercase tracking-widest text-indigo-600">MNCH Mentorship Platform</h2>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden">

            {{-- Top accent --}}
            <div class="h-1 bg-gradient-to-r from-indigo-500 via-violet-500 to-purple-500"></div>

            <div class="px-8 pt-8 pb-2">
                <h1 class="text-2xl font-bold text-slate-900 leading-tight">
                    Hello, {{ $user->full_name }} 👋
                </h1>

                <p class="mt-3 text-slate-600 text-sm leading-relaxed">
                        An account has been created for you on the
                    <span class="font-semibold text-slate-800">MNCH Kenya Mentorship Platform</span>
                        as:
                </p>
                <span class="inline-block mt-2 mb-4 px-3 py-1 bg-indigo-50 text-indigo-700 text-sm font-semibold rounded-full border border-indigo-100">
                    {{ $roleName }}
                </span>
                <p class="text-slate-500 text-sm leading-relaxed mb-6">
                        Please set a secure password below to verify and activate your account.
                        You will be logged in automatically once complete.
                </p>
            </div>

            {{-- Validation errors --}}
        @if ($errors->any())
                <div class="mx-8 mb-2 rounded-xl bg-red-50 border border-red-200 px-4 py-3">
                    <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Form --}}
            <form method="POST"
                      action="{{ route('account.verify.update', ['user' => $user->id] + request()->query()) }}"
                      class="px-8 pb-8 space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5" for="password">
                            New Password
                    </label>
                    <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-900 text-sm placeholder-slate-400
                            focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                            placeholder="Minimum 8 characters"
                            >
                    <p class="mt-1.5 text-xs text-slate-400">Must be at least 8 characters, include one uppercase letter and one number.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5" for="password_confirmation">
                            Confirm Password
                    </label>
                    <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-slate-900 text-sm placeholder-slate-400
                            focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                            placeholder="Re-enter your password"
                            >
                </div>

                <button type="submit"
                            class="w-full py-3 px-4 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700
                            text-white font-semibold rounded-lg text-sm transition-all active:scale-95 shadow-sm mt-2">
                        Verify &amp; Activate My Account
                </button>

            </form>

        </div>

        <p class="text-center text-xs text-slate-400 mt-6">
                Having trouble? Contact your programme coordinator.
        </p>

    </div>

</body>
</html>
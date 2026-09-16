{{-- resources/views/mentee/enroll-invalid.blade.php --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Enrollment Link Invalid</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }</style>
    </head>
    <body class="min-h-full bg-gradient-to-br from-slate-50 to-blue-50 flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-2xl shadow-sm border border-slate-200 p-8 text-center">
        <div class="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-5">
            <svg class="w-7 h-7 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
        </div>
        <h1 class="text-lg font-bold text-slate-800 mb-2">Enrollment Link Invalid</h1>
        <p class="text-sm text-slate-500 mb-6">{{ $reason }}</p>
        <p class="text-xs text-slate-400">
                Please contact your mentor or training coordinator for assistance.
        </p>
    </div>

</body>
</html>
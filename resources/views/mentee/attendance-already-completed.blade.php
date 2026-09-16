<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module Already Completed</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-center">
                    <div class="mx-auto h-16 w-16 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="h-10 w-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Module Already Completed</h2>
                    <p class="text-gray-600 mb-6">
                        {{ $message ?? 'You have already completed this module.' }}
                    </p>
                    
                    <div class="p-4 bg-blue-50 rounded-lg mb-6">
                        <h3 class="font-semibold text-gray-900 mb-2">Module Information</h3>
                        @if(isset($module))
                            <p class="text-sm text-gray-700"><strong>Module:</strong> {{ $module->programModule->name ?? 'Module' }}</p>
                        @endif
                        @if(isset($class))
                            <p class="text-sm text-gray-700"><strong>Class:</strong> {{ $class->name }}</p>
                        @endif
                        @if(isset($user))
                            <p class="text-sm text-gray-700"><strong>Student:</strong> {{ $user->name }}</p>
                        @endif
                    </div>

                    <div class="p-4 bg-green-50 border border-green-200 rounded-lg mb-6">
                        <p class="text-sm text-green-800">
                            <strong>Good news!</strong> You don't need to retake this module. You've already successfully completed it in a previous class.
                        </p>
                    </div>

                    @auth
                        <a href="{{ route('filament.admin.pages.mentee-dashboard') }}" class="inline-block w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-150 shadow-sm">
                            Go to Dashboard
                        </a>
                    @else
                        <p class="text-sm text-gray-500">
                            If you have questions, please contact your mentor.
                        </p>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</body>
</html>
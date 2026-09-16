<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Closed</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-center">
                    <div class="mx-auto h-12 w-12 bg-yellow-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Attendance Closed</h2>
                    <p class="text-gray-600 mb-6">
                        {{ $message ?? 'This attendance link is no longer active.' }}
                    </p>
                    
                    @if(isset($classModule))
                        <div class="p-4 bg-gray-50 rounded-lg mb-4">
                            <p class="text-sm text-gray-700"><strong>Module:</strong> {{ $classModule->programModule->name ?? 'Module' }}</p>
                            <p class="text-sm text-gray-700"><strong>Class:</strong> {{ $classModule->class->name ?? 'Class' }}</p>
                            @if($classModule->status === 'completed')
                                <p class="text-sm text-gray-600 mt-1">This module has been completed.</p>
                            @endif
                        </div>
                    @endif

                    <p class="text-sm text-gray-500">
                        Please contact your mentor if you need assistance.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
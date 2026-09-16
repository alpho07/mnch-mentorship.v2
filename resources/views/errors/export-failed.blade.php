{{-- resources/views/errors/export-failed.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Failed</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-center w-16 h-16 mx-auto bg-red-100 rounded-full mb-4">
            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        
        <div class="text-center">
            <h1 class="text-xl font-semibold text-gray-900 mb-2">Export Failed</h1>
            <p class="text-gray-600 mb-4">{{ $message ?? 'An error occurred while generating your export.' }}</p>
            
            @if(isset($suggestion))
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                <p class="text-sm text-yellow-800">
                    <strong>Suggestion:</strong> {{ $suggestion }}
                </p>
            </div>
            @endif
            
            <div class="space-y-2">
                <a href="javascript:history.back()" 
                   class="inline-flex items-center justify-center w-full px-4 py-2 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Go Back & Try Again
                </a>
                
                <a href="{{ route('filament.admin.resources.training-exports.index') }}" 
                   class="inline-flex items-center justify-center w-full px-4 py-2 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors">
                    Export Dashboard
                </a>
            </div>
        </div>
        
        <div class="mt-6 pt-4 border-t border-gray-200">
            <p class="text-xs text-gray-500 text-center">
                If this problem persists, please contact your system administrator.
            </p>
        </div>
    </div>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-center mb-8">
                    <div class="mx-auto h-12 w-12 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900">Mark Attendance</h2>
                    <p class="mt-2 text-sm text-gray-600">{{ $classModule->class->name }}</p>
                </div>

                <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                    <h3 class="font-semibold text-gray-900 mb-2">Module Information</h3>
                    <p class="text-sm text-gray-700"><strong>Module:</strong> {{ $classModule->programModule->name ?? 'Module' }}</p>
                    @if($classModule->programModule->description ?? false)
                        <p class="text-sm text-gray-600 mt-1">{{ $classModule->programModule->description }}</p>
                    @endif
                </div>

                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-sm text-green-800">{{ session('success') }}</p>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        @foreach($errors->all() as $error)
                            <p class="text-sm text-red-800">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ url('/module/attend/' . $token) }}" class="space-y-4">
                    @csrf
                    
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Phone Number
                        </label>
                        <input 
                            type="text" 
                            id="phone" 
                            name="phone" 
                            required 
                            value="{{ old('phone') }}"
                            placeholder="Enter your phone number"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                        >
                        <p class="mt-1 text-xs text-gray-500">Enter the phone number you enrolled with</p>
                    </div>

                    <button 
                        type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition duration-150 shadow-sm"
                    >
                        Confirm Attendance
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-xs text-gray-500">
                        Make sure you're enrolled in this class before marking attendance
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Enroll in {{ $class->name }}</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl w-full space-y-8">
                <!-- Header -->
            <div class="text-center">
                <h2 class="text-3xl font-extrabold text-gray-900">
                        Class Enrollment
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    {{ $class->training->title }}
                </p>
            </div>

                <!-- Class Details Card -->
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <!-- Class Header -->
                <div class="bg-blue-600 px-6 py-4">
                    <h3 class="text-xl font-bold text-white">{{ $class->name }}</h3>
                    <p class="text-blue-100 text-sm mt-1">
                        {{ $class->training->facility->name }}
                    </p>
                </div>

                    <!-- Class Information -->
                <div class="px-6 py-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Lead Mentor</h4>
                            <p class="mt-1 text-base text-gray-900">{{ $class->training->mentor->full_name }}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Duration</h4>
                            <p class="mt-1 text-base text-gray-900">
                                {{ $class->start_date->format('M j, Y') }} - {{ $class->end_date->format('M j, Y') }}
                            </p>
                        </div>
                    </div>

                        <!-- Modules -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Modules ({{ $class->classModules->count() }})</h4>
                        <div class="space-y-2">
                            @foreach($class->classModules->take(5) as $classModule)
                                <div class="flex items-start p-3 bg-gray-50 rounded-lg">
                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-600 font-semibold text-sm">
                                        {{ $loop->iteration }}
                                    </span>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $classModule->programModule->name }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                            @if($class->classModules->count() > 5)
                                <p class="text-sm text-gray-600 pl-11">
                                    +{{ $class->classModules->count() - 5 }} more modules
                                </p>
                            @endif
                        </div>
                    </div>

                        <!-- Enrollment Form -->
                    <form method="POST" action="{{ route('mentee.enroll.submit', ['token' => $token]) }}" class="space-y-6 border-t pt-6">
                        @csrf

                        <div>
                            <h4 class="text-lg font-medium text-gray-900 mb-4">Enter Your Details</h4>

                                <!-- Phone Number (Required) -->
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">
                                        E-mail *
                                </label>
                                <input type="email" 
                                           name="phone" 
                                           id="phone" 
                                           required
                                           value="{{ old('phone') }}"
                                           placeholder="user@email.com"
                                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">
                                        If you're already registered, we'll enroll you automatically. Otherwise, please fill in the details below.
                                </p>
                            </div>

                                <!-- New User Fields (shown via JS if needed) -->
                            <div id="newUserFields" class="mt-4 space-y-4 hidden">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="first_name" class="block text-sm font-medium text-gray-700">
                                                First Name *
                                        </label>
                                        <input type="text" 
                                                   name="first_name" 
                                                   id="first_name"
                                                   value="{{ old('first_name') }}"
                                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        @error('first_name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="last_name" class="block text-sm font-medium text-gray-700">
                                                Last Name *
                                        </label>
                                        <input type="text" 
                                                   name="last_name" 
                                                   id="last_name"
                                                   value="{{ old('last_name') }}"
                                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        @error('last_name')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">
                                            Email (Optional)
                                    </label>
                                    <input type="email" 
                                               name="email" 
                                               id="email"
                                               value="{{ old('email') }}"
                                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    @error('email')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="facility_id" class="block text-sm font-medium text-gray-700">
                                            Facility *
                                    </label>
                                    <select name="facility_id" 
                                                id="facility_id"
                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        <option value="{{ $class->training->facility_id }}">
                                            {{ $class->training->facility->name }}
                                        </option>
                                    </select>
                                    @error('facility_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                            <!-- Submit Button -->
                        <div>
                            <button type="submit"
                                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Enroll in Class
                            </button>
                        </div>
                    </form>

                        <!-- Auto-exemption Notice -->
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                        <div class="flex">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Note:</strong> If you have completed any of these modules in a previous class, they will be automatically marked as exempted and you won't need to attend them again.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Show new user fields if there are validation errors
            @if($errors->has('first_name') || $errors->has('last_name') || $errors->has('facility_id'))
                document.getElementById('newUserFields').classList.remove('hidden');
                                    @endif
                                </script>
    </body>
</html>
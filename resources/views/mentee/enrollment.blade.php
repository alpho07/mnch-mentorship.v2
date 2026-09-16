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
                        Review the details below and enroll in this mentorship class
                </p>
            </div>

                <!-- Class Details Card -->
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <!-- Class Header -->
                <div class="bg-blue-600 px-6 py-4">
                    <h3 class="text-xl font-bold text-white">{{ $class->name }}</h3>
                    <p class="text-blue-100 text-sm mt-1">
                        {{ $class->training->title }}
                    </p>
                </div>

                    <!-- Class Information -->
                <div class="px-6 py-6 space-y-6">
                        <!-- Mentorship Details -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Facility</h4>
                            <p class="mt-1 text-base text-gray-900">{{ $class->training->facility->name }}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Lead Mentor</h4>
                            <p class="mt-1 text-base text-gray-900">{{ $class->training->mentor->full_name }}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Start Date</h4>
                            <p class="mt-1 text-base text-gray-900">{{ $class->start_date->format('M j, Y') }}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">End Date</h4>
                            <p class="mt-1 text-base text-gray-900">{{ $class->end_date->format('M j, Y') }}</p>
                        </div>
                    </div>

                    @if($class->description)
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Description</h4>
                            <p class="mt-1 text-base text-gray-700">{{ $class->description }}</p>
                        </div>
                    @endif

                        <!-- Modules List -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Modules in this Class</h4>
                        <div class="space-y-2">
                            @foreach($class->classModules as $classModule)
                                <div class="flex items-start p-3 bg-gray-50 rounded-lg">
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-600 font-semibold text-sm">
                                            {{ $loop->iteration }}
                                        </span>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $classModule->programModule->name }}
                                        </p>
                                    @if($classModule->programModule->description)
                                            <p class="text-sm text-gray-600 mt-1">
                                                {{ Str::limit($classModule->programModule->description, 120) }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                        <!-- Important Notice -->
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Important:</strong> If you have completed any of these modules in a previous class, they will be automatically marked as exempted and you won't need to attend them again.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row gap-3 justify-end">
                    @guest
                        <a href="{{ route('login') }}" 
                           class="inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Login to Enroll
                        </a>
                    @else
                        <form method="POST" action="{{ route('mentee.enroll.process', ['token' => $class->enrollment_token]) }}" class="w-full sm:w-auto">
                            @csrf
                            <button type="submit"
                                    class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Enroll in this Class
                            </button>
                        </form>
                    @endguest
                    </div>
                </div>

                <!-- Help Text -->
                <div class="text-center text-sm text-gray-600">
                    <p>Questions about this class? Contact {{ $class->training->mentor->full_name }}</p>
                @if($class->training->mentor->phone)
                    <p class="mt-1">Phone: {{ $class->training->mentor->phone }}</p>
                @endif
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
    @if(session('success'))
        <div class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg">
        {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg">
        {{ session('error') }}
        </div>
    @endif

    @if(session('info'))
        <div class="fixed top-4 right-4 bg-blue-500 text-white px-6 py-3 rounded-lg shadow-lg">
        {{ session('info') }}
                                </div>
    @endif
    </body>
</html>
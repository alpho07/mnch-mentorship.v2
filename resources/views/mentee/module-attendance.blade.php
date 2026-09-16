<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Mark Attendance - {{ $module->programModule->name }}</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl w-full space-y-8">
                <!-- Header -->
            <div class="text-center">
                <h2 class="text-3xl font-extrabold text-gray-900">
                        Module Attendance
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                        Confirm your attendance for this module
                </p>
            </div>

                <!-- Module Details Card -->
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <!-- Module Header -->
                <div class="bg-green-600 px-6 py-4">
                    <h3 class="text-xl font-bold text-white">{{ $module->programModule->name }}</h3>
                    <p class="text-green-100 text-sm mt-1">
                        {{ $module->mentorshipClass->name }}
                    </p>
                </div>

                    <!-- Module Information -->
                <div class="px-6 py-6 space-y-6">
                        <!-- Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Mentorship Program</h4>
                            <p class="mt-1 text-base text-gray-900">{{ $module->mentorshipClass->training->title }}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Facility</h4>
                            <p class="mt-1 text-base text-gray-900">{{ $module->mentorshipClass->training->facility->name }}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Class</h4>
                            <p class="mt-1 text-base text-gray-900">{{ $module->mentorshipClass->name }}</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Module Sequence</h4>
                            <p class="mt-1 text-base text-gray-900">Module {{ $module->order_sequence }}</p>
                        </div>
                    </div>

                    @if($module->programModule->description)
                        <div>
                            <h4 class="text-sm font-medium text-gray-500">Module Description</h4>
                            <p class="mt-1 text-base text-gray-700">{{ $module->programModule->description }}</p>
                        </div>
                    @endif

                        <!-- Sessions in this Module -->
                    @if($module->sessions->count() > 0)
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Sessions in this Module</h4>
                            <div class="space-y-2">
                                @foreach($module->sessions as $session)
                                    <div class="flex items-start p-3 bg-gray-50 rounded-lg">
                                        <div class="flex-shrink-0">
                                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-green-100 text-green-600 font-semibold text-sm">
                                                {{ $session->session_number }}
                                            </span>
                                        </div>
                                        <div class="ml-3 flex-1">
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ $session->name }}
                                            </p>
                                            <div class="flex items-center gap-4 mt-1 text-xs text-gray-600">
                                                @if($session->scheduled_date)
                                                    <span>📅 {{ $session->scheduled_date->format('M j, Y') }}</span>
                                                @endif
                                        @if($session->duration)
                                                    <span>⏱️ {{ $session->duration }}</span>
                                                @endif
                                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                            {{ $session->status === 'completed' ? 'bg-green-100 text-green-800' : 
                                               ($session->status === 'in_progress' ? 'bg-yellow-100 text-yellow-800' : 
                                               'bg-gray-100 text-gray-800') }}">
                                                    {{ ucfirst(str_replace('_', ' ', $session->status)) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                        <!-- Important Notice -->
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-blue-700">
                                    <strong>Notice:</strong> By marking your attendance, you confirm that you have attended this module. Once marked, this module will be recorded as completed and you will not be able to attend it again in any other class.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                    <!-- Action Buttons -->
                <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row gap-3 justify-end">
                    @guest
                    <a href="{{ route('login') }}" 
                           class="inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            Login to Mark Attendance
                    </a>
                @else
                    <form method="POST" action="{{ route('module.attend.mark', ['token' => $module->attendance_token]) }}" class="w-full sm:w-auto">
                        @csrf
                        <button type="submit"
                                    class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                ✓ Mark as Present
                        </button>
                    </form>
                    @endguest
                </div>
            </div>

                <!-- Help Text -->
            <div class="text-center text-sm text-gray-600">
                <p>Questions about this module? Contact your mentor</p>
                <p class="mt-1">{{ $module->mentorshipClass->training->mentor->full_name }}</p>
                @if($module->mentorshipClass->training->mentor->phone)
                    <p class="text-xs mt-1">Phone: {{ $module->mentorshipClass->training->mentor->phone }}</p>
                @endif
            </div>
        </div>
    </div>

        <!-- Flash Messages -->
    @if(session('success'))
        <div class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('error') }}
        </div>
    @endif

    @if(session('info'))
        <div class="fixed top-4 right-4 bg-blue-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('info') }}
        </div>
    @endif

    <script>
            // Auto-hide flash messages after 5 seconds
            setTimeout(() => {
                document.querySelectorAll('[class*="fixed top-4"]').forEach(el => {
                    el.style.opacity = '0';
                    el.style.transition = 'opacity 0.5s';
                    setTimeout(() => el.remove(), 500);
                });
            }, 5000);
    </script>
</body>
</html>
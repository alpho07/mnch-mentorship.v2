<div class="space-y-6">
    <!-- Personal Information -->
    <div class="bg-gray-50 rounded-lg p-4">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Personal Information</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-500">Full Name</label>
                <p class="mt-1 text-sm text-gray-900">{{ $participant->user->full_name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Email</label>
                <p class="mt-1 text-sm text-gray-900">{{ $participant->user->email ?: 'Not provided' }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Phone</label>
                <p class="mt-1 text-sm text-gray-900">{{ $participant->user->phone }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Status</label>
                <p class="mt-1 text-sm text-gray-900">{{ $participant->user->status }}</p>
            </div>
        </div>
    </div>

    <!-- Work Information -->
    <div class="bg-gray-50 rounded-lg p-4">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Work Information</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-500">Facility</label>
                <p class="mt-1 text-sm text-gray-900">{{ $participant->user->facility?->name ?: 'Not assigned' }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Department</label>
                <p class="mt-1 text-sm text-gray-900">{{ $participant->user->department?->name ?: 'Not assigned' }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Cadre</label>
                <p class="mt-1 text-sm text-gray-900">{{ $participant->user->cadre?->name ?: 'Not assigned' }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Role</label>
                <p class="mt-1 text-sm text-gray-900">{{ $participant->user->role ?: 'Not specified' }}</p>
            </div>
        </div>
    </div>

    <!-- Training Information -->
    <div class="bg-gray-50 rounded-lg p-4">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Training Information</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-500">Registration Date</label>
                <p class="mt-1 text-sm text-gray-900">{{ $participant->registration_date?->format('M j, Y') ?: 'Not set' }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500">Attendance Status</label>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                    @if($participant->attendance_status === 'completed') bg-green-100 text-green-800
                    @elseif($participant->attendance_status === 'attending') bg-yellow-100 text-yellow-800
                    @elseif($participant->attendance_status === 'registered') bg-gray-100 text-gray-800
                    @else bg-red-100 text-red-800
                    @endif">
                    {{ ucfirst($participant->attendance_status) }}
                </span>
            </div>
            @if($participant->completion_date)
            <div>
                <label class="block text-sm font-medium text-gray-500">Completion Date</label>
                <p class="mt-1 text-sm text-gray-900">{{ $participant->completion_date->format('M j, Y') }}</p>
            </div>
            @endif
            @if($participant->certificate_issued)
            <div>
                <label class="block text-sm font-medium text-gray-500">Certificate</label>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Issued
                </span>
            </div>
            @endif
        </div>

        @if($participant->notes)
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-500">Notes</label>
            <p class="mt-1 text-sm text-gray-900">{{ $participant->notes }}</p>
        </div>
        @endif
    </div>
</div>

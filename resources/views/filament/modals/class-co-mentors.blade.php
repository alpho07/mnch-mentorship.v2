<div class="p-4 space-y-4">
    @if($coMentors->isEmpty())
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <x-heroicon-o-user-group class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" />
            <p class="font-medium">No active co-mentors for this mentorship yet.</p>
            <p class="text-sm mt-1">Invite co-mentors from the Co-Mentors tab.</p>
        </div>
    @else
        <div class="text-sm text-gray-500 dark:text-gray-400 mb-2">
            These co-mentors can facilitate all classes in this mentorship.
        </div>
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($coMentors as $coMentor)
                <div class="py-3 flex items-center justify-between">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ $coMentor->user->full_name ?? 'Unknown' }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $coMentor->user->email ?? '' }}
                            @if($coMentor->user->cadre)
                                &bull; {{ $coMentor->user->cadre->name }}
                            @endif
                        </p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        Active
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>

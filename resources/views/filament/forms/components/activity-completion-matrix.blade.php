@php
    $participants = $getParticipants();
    $activities = $getActivities();
    $completedActivityIds = $getCompletedActivityIds();
    $videoReviews = $getVideoReviews();
    $certificateStatuses = $getCertificateStatuses();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{
        state: @entangle($getStatePath()),
        participants: @js($participants),
        activities: @js($activities),
        completed: @js($completedActivityIds),
        init() {
            this.sync();
        },
        toggle(participantId, activityId) {
            const list = this.completed[participantId] || [];
            const idx = list.indexOf(activityId);
            if (idx > -1) {
                list.splice(idx, 1);
            } else {
                list.push(activityId);
            }
            this.completed[participantId] = list;
            this.sync();
        },
        isCompleted(participantId, activityId) {
            return (this.completed[participantId] || []).includes(activityId);
        },
        toggleAllForActivity(activityId, checked) {
            this.participants.forEach(p => {
                const completed = this.isCompleted(p.id, activityId);
                if (checked && !completed) {
                    this.toggle(p.id, activityId);
                } else if (!checked && completed) {
                    this.toggle(p.id, activityId);
                }
            });
        },
        toggleAllForParticipant(participantId, checked) {
            this.activities.forEach(a => {
                const completed = this.isCompleted(participantId, a.id);
                if (checked && !completed) {
                    this.toggle(participantId, a.id);
                } else if (!checked && completed) {
                    this.toggle(participantId, a.id);
                }
            });
        },
        sync() {
            const payload = Object.entries(this.completed)
                .map(([participantId, activityIds]) => ({
                    participantId: parseInt(participantId),
                    activityIds: activityIds,
                }));
            this.state = JSON.stringify(payload);
        }
    }">
        @if (empty($participants))
            <div class="text-center py-8 text-gray-500">
                <x-heroicon-o-users class="w-12 h-12 mx-auto mb-3 text-gray-400" />
                <p class="font-medium">No mentees enrolled in this class.</p>
                <p class="text-sm mt-1">Add mentees to the class before marking activities complete.</p>
            </div>
        @elseif (empty($activities))
            <div class="text-center py-8 text-gray-500">
                <x-heroicon-o-clipboard-document-list class="w-12 h-12 mx-auto mb-3 text-gray-400" />
                <p class="font-medium">No activities configured for this module.</p>
                <p class="text-sm mt-1">Add activities to the track from the program curriculum first.</p>
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 sticky left-0 bg-gray-50 dark:bg-gray-700 z-10 min-w-[200px]">
                                Mentee
                            </th>
                            @foreach ($activities as $activity)
                                <th class="px-3 py-3 text-center min-w-[100px]">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="text-xs">{{ $activity['name'] }}</span>
                                        <input type="checkbox"
                                               class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                                               title="Mark complete for all mentees"
                                               @change="toggleAllForActivity({{ $activity['id'] }}, $event.target.checked)">
                                    </div>
                                </th>
                            @endforeach
                            <th class="px-3 py-3 text-center">All done?</th>
                            <th class="px-3 py-3 text-center">Video</th>
                            <th class="px-3 py-3 text-left min-w-[140px]">Certificate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($participants as $participant)
                            @php
                                $pId = $participant['id'];
                                $completedIds = $completedActivityIds[$pId] ?? [];
                                $allDone = count($activities) > 0 && count($completedIds) === count($activities);
                                $videoStatus = $videoReviews[$pId] ?? 'not_submitted';
                                $certStatus = $certificateStatuses[$pId] ?? ['mentor_approved' => false, 'head_drmh_approved' => false, 'certified' => false];
                            @endphp
                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 sticky left-0 bg-white dark:bg-gray-800 z-10">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox"
                                               class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                                               title="Mark all activities complete for this mentee"
                                               @change="toggleAllForParticipant({{ $pId }}, $event.target.checked)">
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $participant['name'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $participant['email'] ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                @foreach ($activities as $activity)
                                    <td class="px-3 py-3 text-center">
                                        <input type="checkbox"
                                               class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                                               :checked="isCompleted({{ $pId }}, {{ $activity['id'] }})"
                                               @change="toggle({{ $pId }}, {{ $activity['id'] }})">
                                    </td>
                                @endforeach
                                <td class="px-3 py-3 text-center">
                                    @if ($allDone)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-700">Yes ✓</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-700">No</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @php
                                        $vColor = match($videoStatus) {
                                            'passed' => 'text-emerald-600',
                                            'failed' => 'text-red-600',
                                            'pending' => 'text-amber-600',
                                            default => 'text-slate-400',
                                        };
                                    @endphp
                                    <span class="text-xs font-semibold {{ $vColor }}">{{ ucfirst(str_replace('_', ' ', $videoStatus)) }}</span>
                                </td>
                                <td class="px-3 py-3 text-xs">
                                    @if ($certStatus['certified'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-100 text-emerald-700">Certified ✓</span>
                                    @elseif (! $allDone)
                                        <span class="text-red-600 font-medium">Activities incomplete</span>
                                    @elseif ($videoStatus !== 'passed')
                                        <span class="text-amber-600 font-medium">Video not passed</span>
                                    @elseif (! $certStatus['mentor_approved'])
                                        <span class="text-blue-600 font-medium">Awaiting mentor approval</span>
                                    @elseif (! $certStatus['head_drmh_approved'])
                                        <span class="text-violet-600 font-medium">Awaiting Head DRMH</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-dynamic-component>

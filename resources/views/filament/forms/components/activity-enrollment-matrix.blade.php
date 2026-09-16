@php
    $participants = $getParticipants();
    $activities = $getActivities();
    $enrolledActivityIds = $getEnrolledActivityIds();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{
        state: @entangle($getStatePath()),
        participants: @js($participants),
        activities: @js($activities),
        enrolled: @js($enrolledActivityIds),
        init() {
            this.sync();
        },
        toggle(participantId, activityId) {
            const list = this.enrolled[participantId] || [];
            const idx = list.indexOf(activityId);
            if (idx > -1) {
                list.splice(idx, 1);
            } else {
                list.push(activityId);
            }
            this.enrolled[participantId] = list;
            this.sync();
        },
        isEnrolled(participantId, activityId) {
            return (this.enrolled[participantId] || []).includes(activityId);
        },
        toggleAllForActivity(activityId, checked) {
            this.participants.forEach(p => {
                const enrolled = this.isEnrolled(p.id, activityId);
                if (checked && !enrolled) {
                    this.toggle(p.id, activityId);
                } else if (!checked && enrolled) {
                    this.toggle(p.id, activityId);
                }
            });
        },
        toggleAllForParticipant(participantId, checked) {
            this.activities.forEach(a => {
                const enrolled = this.isEnrolled(participantId, a.id);
                if (checked && !enrolled) {
                    this.toggle(participantId, a.id);
                } else if (!checked && enrolled) {
                    this.toggle(participantId, a.id);
                }
            });
        },
        sync() {
            const payload = Object.entries(this.enrolled)
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
                <p class="text-sm mt-1">Add mentees to the class before enrolling them in activities.</p>
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
                                               title="Select all mentees"
                                               @change="toggleAllForActivity({{ $activity['id'] }}, $event.target.checked)">
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($participants as $participant)
                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 sticky left-0 bg-white dark:bg-gray-800 z-10">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox"
                                               class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500"
                                               title="Select all activities for this mentee"
                                               @change="toggleAllForParticipant({{ $participant['id'] }}, $event.target.checked)">
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
                                               :checked="isEnrolled({{ $participant['id'] }}, {{ $activity['id'] }})"
                                               @change="toggle({{ $participant['id'] }}, {{ $activity['id'] }})">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-dynamic-component>

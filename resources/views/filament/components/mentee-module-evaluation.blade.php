@php
    $videoUrl = $progress?->hands_on_video_url;
    $embedUrl = $progress?->youtubeEmbedUrl();
    $isDirectVideo = $progress?->isDirectVideoUrl() ?? false;
    $videoPassed = $progress?->video_review_status === 'passed';
@endphp

<div class="space-y-6">
    {{-- Header --}}
    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 p-4">
        <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Mentee</p>
        <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $record->user?->full_name ?? '—' }}</p>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-0.5">{{ $module->programModule?->name ?? 'Module' }} · {{ $module->mentorshipClass?->name ?? 'Class' }}</p>
    </div>

    {{-- Pre-test --}}
    <div>
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Pre-Test</h3>
        @if($preTestStatus['exists'])
            @if($preTestStatus['completed'])
                @include('mentee.partials.quiz-review', ['status' => $preTestStatus])
            @else
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4 text-sm text-slate-600 dark:text-slate-400">
                    Not attempted yet.
                </div>
            @endif
        @else
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4 text-sm text-slate-600 dark:text-slate-400">
                No pre-test configured.
            </div>
        @endif
    </div>

    {{-- Post-test --}}
    <div>
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Post-Test</h3>
        @if($postTestStatus['exists'])
            @if($postTestStatus['completed'])
                @include('mentee.partials.quiz-review', ['status' => $postTestStatus])
            @else
                <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4 text-sm text-slate-600 dark:text-slate-400">
                    Not attempted yet.
                </div>
            @endif
        @else
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4 text-sm text-slate-600 dark:text-slate-400">
                No post-test configured.
            </div>
        @endif
    </div>

    {{-- Activities --}}
    @if($isEmonc && ! empty($activities))
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Activities</h3>
            <div class="space-y-2">
                @foreach($activities as $activity)
                    <div class="flex items-center justify-between px-3 py-2 rounded-lg border
                        {{ $activity['completed'] ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30' : ($activity['enrolled'] ? 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/30' : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900') }}">
                        <span class="text-sm font-medium {{ $activity['completed'] ? 'text-emerald-800 dark:text-emerald-200' : ($activity['enrolled'] ? 'text-blue-800 dark:text-blue-200' : 'text-slate-600 dark:text-slate-400') }}">
                            {{ $activity['name'] }}
                        </span>
                        <span class="text-xs {{ $activity['completed'] ? 'text-emerald-600 dark:text-emerald-400' : ($activity['enrolled'] ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400') }}">
                            {{ $activity['completed'] ? 'Completed' : ($activity['enrolled'] ? 'Enrolled' : 'Not enrolled') }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Hands-on Video --}}
    <div>
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Hands-on Video</h3>
        @if($videoUrl)
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
                @include('filament.components.video-preview', [
                    'embedUrl' => $embedUrl,
                    'videoUrl' => $videoUrl,
                    'isDirectVideo' => $isDirectVideo,
                ])
            </div>
        @else
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4 text-sm text-slate-600 dark:text-slate-400">
                No video submitted yet.
            </div>
        @endif
    </div>

    {{-- Current Review Status --}}
    @if($progress?->video_review_status)
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Current Video Review</p>
            <p class="text-sm font-semibold
                {{ match($progress->video_review_status) {
                    'passed' => 'text-emerald-700 dark:text-emerald-300',
                    'failed' => 'text-red-700 dark:text-red-300',
                    default => 'text-amber-700 dark:text-amber-300',
                } }}">
                {{ ucfirst($progress->video_review_status) }}
            </p>
            @if($progress->video_review_notes)
                <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ $progress->video_review_notes }}</p>
            @endif
        </div>
    @endif
</div>

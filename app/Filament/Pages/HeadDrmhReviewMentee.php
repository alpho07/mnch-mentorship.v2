<?php

namespace App\Filament\Pages;

use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Services\EmoncNotificationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class HeadDrmhReviewMentee extends Page
{
    protected static string $view = 'filament.pages.head-drmh-review-mentee';

    protected static ?string $slug = 'head-drmh-review-mentee';

    protected static bool $shouldRegisterNavigation = false;

    private const ALLOWED_ROLES = ['head_drmh', 'super_admin', 'admin'];

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('page_HeadDrmhReviewMentee');
    }

    // ─── State ───────────────────────────────────────────────────────────────
    public ClassParticipant $participant;

    public array $modules = [];

    public bool $isCertified = false;

    public bool $canCertify = false;

    // ─── Mount ───────────────────────────────────────────────────────────────
    public function mount(): void
    {
        $participantId = request()->integer('participant');
        abort_if(! $participantId, 404);

        $this->participant = ClassParticipant::with([
            'user.facility.subcounty.county',
            'user.cadre',
            'user.department',
            'mentorshipClass.training.facility.subcounty.county',
            'mentorshipClass.training.program',
            'mentorshipClass.training.mentor',
            'mentorshipClass.classModules.programModule.activities',
            'mentorApprovedBy',
            'headDrmhApprovedBy',
        ])->findOrFail($participantId);

        abort_if(! auth()->user()->can('page_HeadDrmhReviewMentee'), 403);

        $this->isCertified = $this->participant->isHeadDrmhApproved();
        $this->canCertify  = $this->participant->isReadyForHeadDrmhCertification() && ! $this->isCertified;

        $this->loadModules();
    }

    public function getTitle(): string
    {
        return 'Review — ' . ($this->participant->user?->full_name ?? 'Mentee');
    }

    // ─── Module data ─────────────────────────────────────────────────────────
    private function loadModules(): void
    {
        $classModules = $this->participant->mentorshipClass?->classModules ?? collect();

        $progressRecords = MenteeModuleProgress::with([
            'preTestAttempt.quiz',
            'preTestAttempt.responses.question.options',
            'preTestAttempt.responses.option',
            'postTestAttempt.quiz',
            'postTestAttempt.responses.question.options',
            'postTestAttempt.responses.option',
            'videoReviewedBy',
        ])
            ->where('class_participant_id', $this->participant->id)
            ->whereIn('class_module_id', $classModules->pluck('id'))
            ->get()
            ->keyBy('class_module_id');

        $activityEnrollments = ClassModuleActivityParticipant::where('class_participant_id', $this->participant->id)
            ->whereIn('class_module_id', $classModules->pluck('id'))
            ->get();

        $this->modules = $classModules->map(function ($cm) use ($progressRecords, $activityEnrollments) {
            $prog = $progressRecords->get($cm->id);
            $pmActivities = $cm->programModule?->activities ?? collect();

            $activities = $pmActivities->map(function ($act) use ($cm, $activityEnrollments) {
                $enrollment = $activityEnrollments->first(
                    fn ($e) => $e->class_module_id === $cm->id && $e->activity_id === $act->id
                );

                return [
                    'name'      => $act->name,
                    'enrolled'  => (bool) $enrollment,
                    'completed' => $enrollment && $enrollment->status === 'completed',
                ];
            })->toArray();

            $preTest  = $prog?->preTestAttempt;
            $postTest = $prog?->postTestAttempt;

            return [
                'id'            => $cm->id,
                'name'          => $cm->programModule?->name ?? "Module {$cm->id}",
                'status'        => $prog?->status ?? 'not_started',
                'activities'    => $activities,
                'pre_test'      => [
                    'exists'    => (bool) $preTest,
                    'score'     => $preTest ? (float) $preTest->score : null,
                    'passed'    => $preTest?->isPassed() ?? false,
                    'completed' => (bool) $preTest,
                    'questions' => $this->buildQuestionPreview($preTest),
                ],
                'post_test'     => [
                    'exists'    => (bool) $postTest,
                    'score'     => $postTest ? (float) $postTest->score : null,
                    'passed'    => $postTest?->isPassed() ?? false,
                    'completed' => (bool) $postTest,
                    'questions' => $this->buildQuestionPreview($postTest),
                ],
                'video'         => [
                    'has_video'       => $prog?->hasSubmittedVideo() ?? false,
                    'review_status'   => $prog?->video_review_status ?? 'pending',
                    'review_notes'    => $prog?->video_review_notes,
                    'reviewed_by'     => $prog?->videoReviewedBy?->full_name,
                    'video_url'       => $prog?->hands_on_video_url ?? null,
                    'embed_url'       => $prog?->youtubeEmbedUrl() ?? null,
                    'is_direct'       => $prog?->isDirectVideoUrl() ?? false,
                    'passed'          => $prog?->isVideoPassed() ?? false,
                ],
            ];
        })->toArray();
    }

    private function buildQuestionPreview(?\App\Models\QuizAttempt $attempt): array
    {
        if (! $attempt) {
            return [];
        }

        return $attempt->responses->map(function ($response) {
            $question    = $response->question;
            $chosenOption = $response->option;
            $correctOption = $question?->options->firstWhere('is_correct', true);

            return [
                'text'           => $question?->question_text ?? '—',
                'chosen_text'    => $chosenOption?->option_text ?? '—',
                'correct_text'   => $correctOption?->option_text ?? '—',
                'is_correct'     => (bool) $response->is_correct,
                'all_options'    => ($question?->options ?? collect())->map(fn ($o) => [
                    'text'       => $o->option_text,
                    'is_correct' => (bool) $o->is_correct,
                    'was_chosen' => $chosenOption && $o->id === $chosenOption->id,
                ])->toArray(),
            ];
        })->toArray();
    }

    // ─── Actions ─────────────────────────────────────────────────────────────
    public function certify(): void
    {
        if (! auth()->user()->can('page_HeadDrmhReviewMentee')) {
            Notification::make()->danger()->title('Not authorized')->send();

            return;
        }

        if ($this->participant->isHeadDrmhApproved()) {
            Notification::make()->warning()->title('Already certified')->send();

            return;
        }

        if (! $this->participant->isReadyForHeadDrmhCertification()) {
            Notification::make()->danger()
                ->title('Not Ready for Certification')
                ->body('This mentee has not yet met the requirements for certification.')
                ->send();

            return;
        }

        $this->participant->markHeadDrmhApproved(auth()->id());

        app(EmoncNotificationService::class)->headDrmhCertified($this->participant->fresh());

        $this->participant = $this->participant->fresh([
            'user.facility.subcounty.county',
            'user.cadre',
            'user.department',
            'mentorshipClass.training.facility.subcounty.county',
            'mentorApprovedBy',
            'headDrmhApprovedBy',
        ]);
        $this->isCertified = true;
        $this->canCertify  = false;

        Notification::make()->success()
            ->title('Certificate Issued')
            ->body("{$this->participant->user?->full_name} has been certified. A certificate is now available for download.")
            ->send();
    }

    public function revertCertification(): void
    {
        if (! auth()->user()->hasRole(['super_admin', 'admin'])) {
            Notification::make()->danger()->title('Only super admins can revert certification')->send();

            return;
        }

        $this->participant->update([
            'head_drmh_approved_at' => null,
            'head_drmh_approved_by' => null,
        ]);

        $this->participant = $this->participant->fresh([
            'user.facility.subcounty.county',
            'user.cadre',
            'mentorApprovedBy',
            'headDrmhApprovedBy',
        ]);
        $this->isCertified = false;
        $this->canCertify  = true;

        Notification::make()->warning()->title('Certification Reverted')->send();
    }
}

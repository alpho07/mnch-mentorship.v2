<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Filament\Resources\RubricAssessmentResource;
use App\Models\ClassModule;
use App\Models\ClassModuleActivityParticipant;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\ModuleRubric;
use App\Models\RubricAssessment;
use App\Models\Training;
use App\Services\EmoncNotificationService;
use App\Services\QuizAttemptService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ReviewModuleMentee extends Page
{
    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.review-module-mentee';

    protected static bool $shouldRegisterNavigation = false;

    public Training $training;

    public MentorshipClass $class;

    public ClassModule $module;

    public ClassParticipant $participant;

    public ?MenteeModuleProgress $progress = null;

    public array $preTestStatus = [];

    public array $postTestStatus = [];

    public array $activities = [];

    public bool $isEmonc = false;

    public ?ModuleRubric $moduleRubric = null;

    public ?RubricAssessment $latestRubricAssessment = null;

    public function mount(Training $training, MentorshipClass $class, ClassModule $module, ClassParticipant $participant): void
    {
        $this->training = $training->load(['facility', 'program', 'mentor']);
        $this->class = $class;
        $this->module = $module->load(['programModule.activities', 'programModule.quizzes.questions.options', 'programModule.contents']);
        $this->participant = $participant->load(['user.facility.subcounty.county', 'user.cadre', 'user.department']);

        $this->progress = MenteeModuleProgress::with(['preTestAttempt.responses.option', 'postTestAttempt.responses.option'])
            ->where('class_participant_id', $this->participant->id)
            ->where('class_module_id', $this->module->id)
            ->first();

        $this->isEmonc = $this->detectEmonc();

        $this->loadQuizStatuses();
        $this->loadActivities();
        $this->loadRubricData();
    }

    protected function getViewData(): array
    {
        $programModuleId = $this->module->program_module_id;
        $contents = $this->module->programModule?->contents ?? collect();

        return [
            'canMentorApprove' => $this->canMentorApprove(),
            'isReadyForMentorApproval' => $this->isReadyForMentorApproval(),
            'moduleRubric' => $this->moduleRubric,
            'latestRubricAssessment' => $this->latestRubricAssessment,
            'mentorCourseIntro' => $contents->where('type', 'mentor_course_intro')->first(),
            'mentorMaterials' => $contents->where('type', 'mentor_materials')->sortBy('order_sequence')->values(),
            'conductAssessmentUrl' => $this->moduleRubric
                ? RubricAssessmentResource::getUrl('create').'?rubric_id='.$this->moduleRubric->id.'&mentee_id='.$this->participant->user_id.'&class_module_id='.$this->module->id
                : RubricAssessmentResource::getUrl('create'),
        ];
    }

    public function getTitle(): string
    {
        return "Review — {$this->participant->user?->full_name}";
    }

    public function getSubheading(): ?string
    {
        return "{$this->module->programModule?->name} · {$this->class->name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Module Mentees')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('module-mentees', [
                    'training' => $this->training->id,
                    'class' => $this->class->id,
                    'module' => $this->module->id,
                ])),
        ];
    }

    public function mentorApprove(): void
    {
        if (! $this->canMentorApprove()) {
            Notification::make()->danger()->title('Not authorized to approve')->send();

            return;
        }

        if ($this->participant->mentor_approved_at) {
            Notification::make()->warning()->title('Already approved')->send();

            return;
        }

        if (! $this->isReadyForMentorApproval()) {
            Notification::make()->danger()
                ->title('Not Ready for Approval')
                ->body('All modules must be completed, and any module with a hands-on video must have that video reviewed and passed.')
                ->send();

            return;
        }

        try {
            $this->participant->markMentorApproved(auth()->id());
        } catch (\DomainException $e) {
            Notification::make()->danger()->title('Not Ready for Approval')->body($e->getMessage())->send();

            return;
        }

        app(EmoncNotificationService::class)->mentorApproved($this->participant->fresh());

        $this->participant = $this->participant->fresh(['user.facility.subcounty.county', 'user.cadre', 'user.department']);

        Notification::make()->success()->title('Mentor Approval Saved')
            ->body("{$this->participant->user?->full_name} has been mentor-approved for {$this->class->name}.")
            ->send();
    }

    public function revertApproval(): void
    {
        if (! $this->canMentorApprove()) {
            Notification::make()->danger()->title('Not authorized')->send();

            return;
        }

        if (! $this->participant->mentor_approved_at) {
            Notification::make()->warning()->title('Not yet approved')->send();

            return;
        }

        if ($this->participant->head_drmh_approved_at) {
            Notification::make()->danger()
                ->title('Cannot Revert')
                ->body('Head DRMH has already certified this mentee. Reverting is not allowed.')
                ->send();

            return;
        }

        $this->participant->update([
            'mentor_approved_at' => null,
            'mentor_approved_by' => null,
        ]);

        $this->participant = $this->participant->fresh(['user.facility.subcounty.county', 'user.cadre', 'user.department']);

        Notification::make()->warning()->title('Approval Reverted')
            ->body("Mentor approval for {$this->participant->user?->full_name} has been reverted.")
            ->send();
    }

    /**
     * Manual override for a mentor to lock this module as complete for the
     * mentee, independent of the automatic pretest+video+post-test trigger
     * (e.g. the mentee finished everything that matters but a step can't be
     * satisfied through the normal flow).
     */
    public function markModuleComplete(): void
    {
        if (! $this->canMentorApprove()) {
            Notification::make()->danger()->title('Not authorized')->send();

            return;
        }

        if (! $this->progress) {
            Notification::make()->danger()->title('Cannot Complete')
                ->body('This mentee has not started this module yet.')
                ->send();

            return;
        }

        if ($this->progress->isLockedForMentee()) {
            Notification::make()->warning()->title('Already Completed')->send();

            return;
        }

        $this->progress->markCompleted();
        $this->progress = $this->progress->fresh(['preTestAttempt.responses.option', 'postTestAttempt.responses.option']);

        Notification::make()->success()->title('Module Marked Complete')
            ->body("{$this->participant->user?->full_name}'s module is now locked and marked complete.")
            ->send();
    }

    /**
     * Escape hatch for correcting a mistake on a locked module — reopens
     * the pre-test/video/post-test and Activity Completion Matrix for
     * editing again. Refuses once the mentee is already mentor-approved,
     * since that approval was granted on the assumption every module
     * (including this one) was genuinely complete.
     */
    public function unlockModule(): void
    {
        if (! $this->canMentorApprove()) {
            Notification::make()->danger()->title('Not authorized')->send();

            return;
        }

        if (! $this->progress || ! $this->progress->isLockedForMentee()) {
            Notification::make()->warning()->title('Not Locked')->send();

            return;
        }

        if ($this->participant->mentor_approved_at) {
            Notification::make()->danger()
                ->title('Cannot Unlock')
                ->body('This mentee is already mentor-approved for certification — revert that approval first if this module genuinely needs reopening.')
                ->send();

            return;
        }

        // isLockedForMentee() (and the video-reassessment guard) key off
        // the video's own pass state, not `status` — reverting only
        // `status` would leave the mentee still locked out, so reopen the
        // video review too, back to pending.
        $this->progress->update([
            'status' => 'in_progress',
            'completed_at' => null,
            'video_review_status' => 'pending',
        ]);
        $this->progress = $this->progress->fresh(['preTestAttempt.responses.option', 'postTestAttempt.responses.option']);

        Notification::make()->warning()->title('Module Unlocked')
            ->body("{$this->participant->user?->full_name}'s module has been reopened for editing.")
            ->send();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Data loading helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function detectEmonc(): bool
    {
        $name = strtolower($this->training->program?->name ?? '');

        return str_contains($name, 'maternal') && str_contains($name, 'emonc');
    }

    private function loadQuizStatuses(): void
    {
        $service = app(QuizAttemptService::class);
        $mentee = $this->participant->user;

        $preTestQuizzes = $this->module->programModule?->quizzes?->filter(fn ($q) => $q->isPreTest()) ?? collect();
        $postTestQuizzes = $this->module->programModule?->quizzes?->filter(fn ($q) => $q->isPostTest()) ?? collect();

        $this->preTestStatus = $this->buildQuizStatus($mentee, $preTestQuizzes, $service, 'pre_test_attempt_id');
        $this->postTestStatus = $this->buildQuizStatus($mentee, $postTestQuizzes, $service, 'post_test_attempt_id');
    }

    private function buildQuizStatus(?\App\Models\User $mentee, Collection $quizzes, QuizAttemptService $service, string $attemptIdColumn): array
    {
        if ($quizzes->isEmpty() || ! $mentee) {
            return ['exists' => false, 'passed' => true, 'completed' => true, 'attempt' => null, 'quiz' => null];
        }

        $quiz = $quizzes->first();
        $latestCompleted = $service->getLatestAttempts($mentee, $quiz);
        $key = $attemptIdColumn === 'pre_test_attempt_id' ? 'pre_test' : 'post_test';
        $latest = $latestCompleted[$key];

        if ($latest) {
            return [
                'exists' => true,
                'passed' => $latest->isPassed(),
                'completed' => true,
                'attempt' => $latest,
                'quiz' => $quiz,
            ];
        }

        return [
            'exists' => true,
            'passed' => false,
            'completed' => false,
            'attempt' => null,
            'quiz' => $quiz,
        ];
    }

    public function canMentorApprove(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return $this->training->mentor_id === $user->id
            || $this->training->isCoMentor($user->id);
    }

    public function isReadyForMentorApproval(): bool
    {
        return $this->participant->hasCompletedAllModules();
    }

    private function loadRubricData(): void
    {
        $programModuleId = $this->module->program_module_id;

        $this->moduleRubric = ModuleRubric::where('program_module_id', $programModuleId)
            ->where('is_active', true)
            ->first();

        if ($this->moduleRubric && $this->participant->user_id) {
            // Scoped to this class_module_id — otherwise a mentee retaking
            // the same program module in a different cohort/class would
            // surface the previous class's rubric result here.
            $this->latestRubricAssessment = RubricAssessment::where('module_rubric_id', $this->moduleRubric->id)
                ->where('mentee_id', $this->participant->user_id)
                ->where('class_module_id', $this->module->id)
                ->with(['mentor'])
                ->latest('assessed_at')
                ->first();
        }
    }

    private function loadActivities(): void
    {
        $activityIds = $this->module->programModule?->activities?->pluck('id')->toArray() ?? [];
        $enrolled = [];
        $completed = [];

        if (! empty($activityIds)) {
            $enrolled = ClassModuleActivityParticipant::where('class_module_id', $this->module->id)
                ->where('class_participant_id', $this->participant->id)
                ->whereIn('activity_id', $activityIds)
                ->pluck('activity_id')
                ->toArray();

            $completed = ClassModuleActivityParticipant::where('class_module_id', $this->module->id)
                ->where('class_participant_id', $this->participant->id)
                ->whereIn('activity_id', $activityIds)
                ->where('status', 'completed')
                ->pluck('activity_id')
                ->toArray();
        }

        $this->activities = $this->module->programModule?->activities?->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'enrolled' => in_array($a->id, $enrolled),
            'completed' => in_array($a->id, $completed),
        ])->toArray() ?? [];
    }
}

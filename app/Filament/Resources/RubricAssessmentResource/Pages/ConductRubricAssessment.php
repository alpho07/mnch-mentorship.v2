<?php

namespace App\Filament\Resources\RubricAssessmentResource\Pages;

use App\Filament\Resources\RubricAssessmentResource;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\ModuleRubric;
use App\Models\RubricAssessment;
use App\Models\RubricItemResponse;
use App\Models\User;
use App\Services\EmoncNotificationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class ConductRubricAssessment extends Page
{
    protected static string $resource = RubricAssessmentResource::class;

    protected static string $view = 'filament.pages.conduct-rubric-assessment';

    protected static ?string $title = 'Conduct Practical Assessment';

    // Step 1: selection
    #[Url(as: 'rubric_id')]
    public ?int $module_rubric_id = null;

    #[Url]
    public ?int $mentee_id = null;

    public ?int $mentor_id = null;

    // Which ClassModule this assessment is being conducted for — arrives
    // from ReviewModuleMentee's "Conduct Practical Assessment" link so the
    // resulting RubricAssessment (and the MenteeModuleProgress it drives,
    // see submitAssessment()) are scoped to the right class/cohort instead
    // of only to mentee+rubric globally (a mentee retaking the same
    // program module in a different class would otherwise see/affect the
    // wrong class's result).
    #[Url]
    public ?int $class_module_id = null;

    public string $assessed_at = '';

    public string $notes = '';

    // Step 2: scoring
    public int $step = 1;

    public array $responses = [];   // [rubric_item_id => bool]

    // Computed
    public ?ModuleRubric $rubric = null;

    public ?User $menteeUser = null;

    public function mount(): void
    {
        $this->mentor_id = auth()->id();
        $this->assessed_at = now()->format('Y-m-d\TH:i');

        // Arrived from ReviewModuleMentee's "Conduct Practical Assessment"
        // link with rubric + mentee already known — skip the manual
        // picker in Step 1 and go straight to scoring. "Back" still lets
        // the mentor reach the picker if they need to correct something.
        if ($this->module_rubric_id && $this->mentee_id) {
            $this->loadRubric();

            if ($this->rubric) {
                $this->step = 2;
            }
        }
    }

    public function loadRubric(): void
    {
        if (! $this->module_rubric_id) {
            return;
        }

        $this->rubric = ModuleRubric::with('items')->find($this->module_rubric_id);
        $this->menteeUser = $this->mentee_id ? User::find($this->mentee_id) : null;

        if ($this->rubric) {
            // Pre-populate all items as false
            $this->responses = $this->rubric->items
                ->mapWithKeys(fn ($item) => [$item->id => false])
                ->all();
        }
    }

    public function proceedToScoring(): void
    {
        $this->validate([
            'module_rubric_id' => 'required|exists:module_rubrics,id',
            'mentee_id' => 'required|exists:users,id',
            'mentor_id' => 'required|exists:users,id',
            'assessed_at' => 'required',
        ]);

        $this->loadRubric();
        $this->step = 2;
    }

    public function toggleItem(int $itemId): void
    {
        $this->responses[$itemId] = ! ($this->responses[$itemId] ?? false);
    }

    public function submitAssessment(): void
    {
        if (! $this->rubric) {
            return;
        }

        $score = collect($this->responses)->filter(fn ($v) => $v === true)->count();
        $passed = $score >= $this->rubric->pass_marks;

        DB::transaction(function () use ($score, $passed) {
            $assessment = RubricAssessment::create([
                'module_rubric_id' => $this->module_rubric_id,
                'class_module_id' => $this->class_module_id,
                'mentee_id' => $this->mentee_id,
                'mentor_id' => $this->mentor_id,
                'score' => $score,
                'passed' => $passed,
                'notes' => $this->notes ?: null,
                'assessed_at' => $this->assessed_at,
            ]);

            foreach ($this->responses as $itemId => $performed) {
                RubricItemResponse::create([
                    'rubric_assessment_id' => $assessment->id,
                    'rubric_item_id' => $itemId,
                    'performed' => $performed,
                ]);
            }

            $this->syncVideoReviewStatus($passed);
        });

        $label = $passed ? 'PASS' : 'FAIL';
        $pct = $this->rubric->total_marks > 0
            ? round(($score / $this->rubric->total_marks) * 100, 1)
            : 0;

        Notification::make()
            ->title("Assessment saved — {$label}")
            ->body("Score: {$score}/{$this->rubric->total_marks} ({$pct}%)")
            ->when($passed, fn ($n) => $n->success())
            ->when(! $passed, fn ($n) => $n->danger())
            ->send();

        $this->redirect(RubricAssessmentResource::getUrl('index'));
    }

    /**
     * The rubric score is the single source of truth for the hands-on
     * pass/fail outcome (per EmONC meeting item 4) — mirror it onto
     * MenteeModuleProgress so the mentor review page's Video Review badge
     * and hasCompletedAllModules() gating both reflect it automatically,
     * with no separate manual entry.
     */
    private function syncVideoReviewStatus(bool $passed): void
    {
        if (! $this->class_module_id) {
            return;
        }

        $classModule = ClassModule::find($this->class_module_id);
        if (! $classModule) {
            return;
        }

        $participant = ClassParticipant::where('mentorship_class_id', $classModule->mentorship_class_id)
            ->where('user_id', $this->mentee_id)
            ->first();

        if (! $participant) {
            return;
        }

        $progress = MenteeModuleProgress::firstOrNew([
            'class_participant_id' => $participant->id,
            'class_module_id' => $this->class_module_id,
        ]);
        $progress->save();

        $progress->recordVideoReview(
            $passed ? 'passed' : 'failed',
            'Derived from practical (rubric) assessment.',
            $this->mentor_id
        );

        app(EmoncNotificationService::class)->videoReviewed($progress->fresh());
    }

    public function backToStep1(): void
    {
        $this->step = 1;
    }

    public function getScore(): int
    {
        return collect($this->responses)->filter(fn ($v) => $v === true)->count();
    }

    public function getRubrics(): \Illuminate\Support\Collection
    {
        return ModuleRubric::with('programModule')
            ->where('is_active', true)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'label' => $r->programModule->name.' — '.$r->title,
            ]);
    }

    public function getMentees(): \Illuminate\Support\Collection
    {
        return User::role('mentee')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'label' => $u->full_name.' ('.$u->email.')']);
    }

    public function getMentors(): \Illuminate\Support\Collection
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'like', '%mentor%'))
            ->orderBy('first_name')
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'label' => $u->full_name]);
    }
}

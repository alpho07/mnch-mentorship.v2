<?php

namespace App\Filament\Resources\RubricAssessmentResource\Pages;

use App\Filament\Resources\RubricAssessmentResource;
use App\Models\ModuleRubric;
use App\Models\RubricItemResponse;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class EditRubricAssessment extends Page
{
    protected static string $resource = RubricAssessmentResource::class;

    protected static string $view = 'filament.pages.edit-rubric-assessment';

    protected static ?string $title = 'Edit Practical Assessment';

    public \App\Models\RubricAssessment $record;

    public ?ModuleRubric $rubric = null;

    public array $responses = [];

    public string $notes = '';

    public string $assessed_at = '';

    public function mount(\App\Models\RubricAssessment $record): void
    {
        $this->record = $record->load(['rubric.items', 'itemResponses', 'mentee', 'mentor']);

        $this->rubric = $this->record->rubric;
        $this->notes = $this->record->notes ?? '';
        $this->assessed_at = $this->record->assessed_at->format('Y-m-d\TH:i');

        $performedIds = $this->record->itemResponses
            ->where('performed', true)
            ->pluck('rubric_item_id')
            ->flip();

        $this->responses = $this->rubric?->items
            ->mapWithKeys(fn ($item) => [$item->id => $performedIds->has($item->id)])
            ->all() ?? [];
    }

    public function toggleItem(int $itemId): void
    {
        $this->responses[$itemId] = ! ($this->responses[$itemId] ?? false);
    }

    public function getScore(): int
    {
        return collect($this->responses)->filter(fn ($v) => $v === true)->count();
    }

    public function saveAssessment(): void
    {
        if (! $this->rubric) {
            return;
        }

        $score = $this->getScore();
        $passed = $score >= $this->rubric->pass_marks;

        DB::transaction(function () use ($score, $passed) {
            $this->record->update([
                'score'       => $score,
                'passed'      => $passed,
                'notes'       => $this->notes ?: null,
                'assessed_at' => $this->assessed_at,
            ]);

            foreach ($this->responses as $itemId => $performed) {
                RubricItemResponse::updateOrCreate(
                    [
                        'rubric_assessment_id' => $this->record->id,
                        'rubric_item_id'       => $itemId,
                    ],
                    ['performed' => $performed]
                );
            }
        });

        $label = $passed ? 'PASS' : 'FAIL';
        $total = $this->rubric->total_marks;
        $pct = $total > 0 ? round(($score / $total) * 100, 1) : 0;

        Notification::make()
            ->title("Assessment updated — {$label}")
            ->body("Score: {$score}/{$total} ({$pct}%)")
            ->when($passed, fn ($n) => $n->success())
            ->when(! $passed, fn ($n) => $n->danger())
            ->send();

        $this->redirect(RubricAssessmentResource::getUrl('view', ['record' => $this->record->id]));
    }
}

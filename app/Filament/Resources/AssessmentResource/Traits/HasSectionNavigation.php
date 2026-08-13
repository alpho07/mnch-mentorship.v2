<?php

namespace App\Filament\Resources\AssessmentResource\Traits;

use App\Filament\Resources\AssessmentResource;

trait HasSectionNavigation
{
    /**
     * Get all real (non-informational) sections on the assessment's own
     * template, with their completion status and the route to edit each —
     * dynamic per template, rather than a fixed list of 6 hardcoded pages.
     */
    protected function getAllSections(): array
    {
        $progress = $this->record->section_progress ?? [];
        $sections = $this->record->assessmentType
            ?->sections()
            ->where('is_active', true)
            ->orderBy('order')
            ->get() ?? collect();

        $responsesByCode = \App\Models\AssessmentQuestionResponse::query()
            ->where('assessment_id', $this->record->id)
            ->join('assessment_questions', 'assessment_questions.id', '=', 'assessment_question_responses.assessment_question_id')
            ->pluck('assessment_question_responses.response_value', 'assessment_questions.question_code')
            ->all();

        $sections = $sections->filter(function (\App\Models\AssessmentSection $section) use ($responsesByCode) {
            if (empty($section->display_conditions)) {
                return true;
            }

            return \App\Services\ConditionalLogicEvaluator::isVisible(
                $section->display_conditions,
                fn (string $code) => $responsesByCode[$code] ?? null
            );
        });

        $result = [];

        foreach ($sections as $section) {
            $route = match ($section->resolvedKind()) {
                'question_group' => AssessmentResource::getUrl('edit-section', [
                    'record' => $this->record->id,
                    'sectionCode' => $section->code,
                ]),
                'human_resources' => AssessmentResource::getUrl('edit-human-resources', ['record' => $this->record->id]),
                'commodity_matrix' => AssessmentResource::getUrl('edit-health-products', ['record' => $this->record->id]),
                default => null, // informational — not an editable section
            };

            if ($route === null) {
                continue;
            }

            $result[$section->code] = [
                'label' => $section->name,
                'done' => $progress[$section->code] ?? false,
                'route' => $route,
            ];
        }

        return $result;
    }

    /**
     * Get current section key - must be implemented by each page
     */
    abstract protected function getCurrentSectionKey(): string;

    /**
     * Get the route to the next incomplete section
     */
    protected function getNextSectionRoute(): string
    {
        $sections = $this->getAllSections();
        $currentSectionKey = $this->getCurrentSectionKey();

        // Find current section index
        $sectionKeys = array_keys($sections);
        $currentIndex = array_search($currentSectionKey, $sectionKeys);

        if ($currentIndex === false) {
            return AssessmentResource::getUrl('dashboard', ['record' => $this->record->id]);
        }

        // Look for next incomplete section
        for ($i = $currentIndex + 1; $i < count($sectionKeys); $i++) {
            $nextSection = $sections[$sectionKeys[$i]];

            if (! $nextSection['done']) {
                return $nextSection['route'];
            }
        }

        // All sections after this are complete, return to dashboard
        return AssessmentResource::getUrl('dashboard', ['record' => $this->record->id]);
    }

    /**
     * CRITICAL: Override getRedirectUrl to use our custom logic
     * This is the method Filament calls after saving
     */
    protected function getRedirectUrl(): string
    {
        return $this->getNextSectionRoute();
    }
}

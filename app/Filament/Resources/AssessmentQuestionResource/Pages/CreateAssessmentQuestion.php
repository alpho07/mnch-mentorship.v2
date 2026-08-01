<?php

// app/Filament/Resources/AssessmentQuestionResource/Pages/CreateAssessmentQuestion.php

namespace App\Filament\Resources\AssessmentQuestionResource\Pages;

use App\Filament\Resources\AssessmentQuestionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssessmentQuestion extends CreateRecord
{
    protected static string $resource = AssessmentQuestionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return static::resolveConditionalLogic($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Builds display_conditions (the real, live DB column) from the form's
     * helper fields. Persisted key is display_conditions — conditional_logic
     * is not a real column on assessment_questions.
     */
    public static function resolveConditionalLogic(array $data): array
    {
        // If conditional_logic_parent was set and no multi-condition raw JSON,
        // ensure display_conditions.question_code is set properly
        if (! empty($data['conditional_logic_parent']) && empty($data['display_conditions']['question_code'])) {
            $data['display_conditions'] = array_merge($data['display_conditions'] ?? [], [
                'question_code' => $data['conditional_logic_parent'],
            ]);
        }

        unset($data['conditional_logic_parent']);

        // Handle raw JSON override from the advanced textarea
        if (! empty($data['conditional_logic_raw'])) {
            $parsed = json_decode($data['conditional_logic_raw'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                $data['display_conditions'] = $parsed;
            }
        }
        unset($data['conditional_logic_raw']);

        // Empty display_conditions should be stored as null
        if (isset($data['display_conditions']) && empty(array_filter($data['display_conditions'] ?? []))) {
            $data['display_conditions'] = null;
        }

        return $data;
    }
}

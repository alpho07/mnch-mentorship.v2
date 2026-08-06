<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentSectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'assessment_type_id' => $this->assessment_type_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'section_type' => $this->section_type,
            'is_scored' => (bool) $this->is_scored,
            'icon' => $this->icon,
            'color' => $this->color,
            'order' => $this->order,
            'is_active' => (bool) $this->is_active,
            'questions_count' => $this->whenCounted('questions'),
            'questions' => $this->whenLoaded('questions', fn () => $this->questions->map(fn ($q) => [
                'id' => $q->id,
                'question_code' => $q->question_code,
                'question_text' => $q->question_text,
                'help_text' => $q->help_text,
                'question_type' => $q->question_type,
                'options' => $q->options,
                'is_required' => (bool) $q->is_required,
                'display_conditions' => $q->display_conditions,
                'requires_explanation_on' => $q->requires_explanation_on,
                'explanation_label' => $q->explanation_label,
                'skip_logic' => $q->skip_logic,
                'scoring_map' => $q->scoring_map,
                'is_scored' => (bool) $q->is_scored,
                'order' => $q->order,
                'group' => $q->group,
            ])->values()),
        ];
    }
}

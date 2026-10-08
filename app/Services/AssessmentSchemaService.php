<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentSection;
use App\Models\AssessmentType;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the form schema (sections + questions) the mobile app renders, per
 * assessment template. Works for ANY template — active, retired or
 * soft-deleted — so assessments started on a previous template can always
 * be opened, continued and reported on.
 */
class AssessmentSchemaService
{
    /** Template used for legacy clients that don't say which one they want. */
    public const DEFAULT_TEMPLATE_CODE = 'STANDARD_FACILITY_ASSESSMENT';

    private const CACHE_TTL_MINUTES = 10;

    /**
     * Template a legacy create request (no assessment_type_id) falls back to.
     */
    public function defaultTemplate(): ?AssessmentType
    {
        return AssessmentType::where('code', self::DEFAULT_TEMPLATE_CODE)->first()
            ?? AssessmentType::active()->orderBy('id')->first();
    }

    public function templateSummary(AssessmentType $type): array
    {
        return [
            'id' => $type->id,
            'name' => $type->name,
            'code' => $type->code,
            'version' => $type->version,
            'description' => $type->description,
            'is_active' => (bool) $type->is_active,
            'is_retired' => $type->trashed(),
            'category' => $type->category ? ['id' => $type->category->id, 'name' => $type->category->name] : null,
            'period_start' => $type->period_start?->toDateString(),
            'period_end' => $type->period_end?->toDateString(),
        ];
    }

    /**
     * Sections + questions for one template. Informational-only sections are
     * left out, matching what the app has always received.
     */
    public function forTemplate(AssessmentType $type): array
    {
        $stamp = AssessmentSection::where('assessment_type_id', $type->id)->max('updated_at');

        return Cache::remember(
            "api.assessment_schema.{$type->id}.".md5((string) $stamp),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => $this->build($type)
        );
    }

    public function forAssessment(Assessment $assessment): ?array
    {
        $type = $assessment->assessmentType;

        return $type ? ['template' => $this->templateSummary($type), 'sections' => $this->forTemplate($type)] : null;
    }

    private function build(AssessmentType $type): array
    {
        return AssessmentSection::active()
            ->ordered()
            ->where('assessment_type_id', $type->id)
            ->whereNotIn('code', AssessmentSection::INFORMATIONAL_CODES)
            ->with(['questions' => fn ($q) => $q->where('is_active', true)->orderBy('order')])
            ->get()
            ->map(fn (AssessmentSection $section) => [
                'id' => $section->id,
                'code' => $section->code,
                'name' => $type->interpolate($section->name),
                'description' => $type->interpolate($section->description),
                'icon' => $section->icon,
                'color' => $section->color ?? '#059669',
                'order' => $section->order,
                'section_type' => $section->section_type,
                'kind' => $section->resolvedKind(),
                'is_scored' => (bool) $section->is_scored,
                'display_conditions' => $section->display_conditions,
                'questions' => $section->questions->map(fn ($q) => [
                    'id' => $q->id,
                    'question_code' => $q->question_code,
                    'question_text' => $type->interpolate($q->question_text),
                    'help_text' => $type->interpolate($q->help_text),
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
                    'indent_level' => (int) $q->indent_level,
                ])->values(),
            ])->values()->all();
    }
}

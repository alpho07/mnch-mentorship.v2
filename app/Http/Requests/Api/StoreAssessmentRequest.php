<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentRequest extends FormRequest {

    public function authorize(): bool {
        return true;
    }

    /**
     * Mirrors the web CreateAssessment form: facility + template + round
     * (+ label for "other") + date, with optional team invites.
     *
     * Backwards compatible: older app builds send only `assessment_type`
     * (baseline/midline/endline) and no template — they get the default
     * template and that value is used as the round.
     */
    public function rules(): array {
        return [
            'facility_id' => 'required|exists:facilities,id',
            'assessment_type_id' => 'nullable|integer|exists:assessment_types,id',
            'round' => 'nullable|in:baseline,midline,endline,other',
            'round_label' => 'nullable|string|max:100|required_if:round,other',
            'assessment_type' => 'nullable|in:baseline,midline,endline',
            'assessment_date' => 'required|date|after_or_equal:today',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'integer|exists:users,id',
        ];
    }
}

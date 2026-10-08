<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentRequest extends FormRequest {

    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'assessment_type' => 'sometimes|nullable|in:baseline,midline,endline',
            'assessment_type_id' => 'sometimes|integer|exists:assessment_types,id',
            'round' => 'sometimes|in:baseline,midline,endline,other',
            'round_label' => 'nullable|string|max:100|required_if:round,other',
            'assessment_date' => 'sometimes|required|date|after_or_equal:today',
        ];
    }
}

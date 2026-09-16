<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssessmentRequest extends FormRequest {

    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'assessment_type' => 'sometimes|required|in:baseline,midline,endline',
            'assessment_date' => 'sometimes|required|date|after_or_equal:today',
        ];
    }
}

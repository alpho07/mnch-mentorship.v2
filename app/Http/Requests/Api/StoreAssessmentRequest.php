<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentRequest extends FormRequest {

    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'facility_id' => 'required|exists:facilities,id',
            'assessment_type' => 'required|in:baseline,midline,endline',
            'assessment_date' => 'required|date|after_or_equal:today',
        ];
    }
}

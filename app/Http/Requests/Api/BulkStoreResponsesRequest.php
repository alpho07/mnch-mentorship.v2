<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreResponsesRequest extends FormRequest {

    public function authorize(): bool {
        return true;
    }

    public function rules(): array {
        return [
            'section_code' => 'required|string|exists:assessment_sections,code',
            'responses' => 'required|array|min:1',
            'responses.*' => ['nullable', function ($attribute, $value, $fail) {
                    if ($value !== null && !is_string($value) && !is_numeric($value) && !is_bool($value)) {
                        $fail("The {$attribute} must be a string, number, or boolean.");
                    }
                }],
            'explanations' => 'nullable|array',
            'explanations.*' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array {
        return [
            'section_code.exists' => 'The specified section code does not exist.',
            'responses.required' => 'At least one response is required.',
        ];
    }

    /**
     * Normalise all response values to strings before validation.
     * Keeps response_value column and scoring_map keys consistent.
     */
    protected function prepareForValidation(): void {
        $responses = $this->input('responses', []);

        if (is_array($responses)) {
            $this->merge([
                'responses' => array_map(
                        fn($v) => ($v !== null && $v !== '') ? (string) $v : $v,
                        $responses
                ),
            ]);
        }
    }
}

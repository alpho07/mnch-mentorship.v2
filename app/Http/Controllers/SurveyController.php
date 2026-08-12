<?php

namespace App\Http\Controllers;

use App\Models\Survey;

class SurveyController extends Controller
{
    public function show(string $token)
    {
        $survey = Survey::where('access_token', $token)
            ->where('is_public', true)
            ->where('is_active', true)
            ->first();

        if (! $survey) {
            return view('survey.invalid', [
                'reason' => 'This survey link is no longer active or does not exist.',
            ]);
        }

        return view('survey.public', compact('survey'));
    }
}

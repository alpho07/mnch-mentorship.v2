<?php

namespace Tests\Unit;

use App\Http\Requests\Api\StoreAssessmentRequest;
use App\Http\Requests\Api\UpdateAssessmentRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AssessmentDateValidationTest extends TestCase
{
    public function test_store_request_accepts_today_and_future_dates(): void
    {
        $rule = (new StoreAssessmentRequest())->rules()['assessment_date'];

        $today = Validator::make(
            ['assessment_date' => now()->toDateString()],
            ['assessment_date' => $rule]
        );
        $future = Validator::make(
            ['assessment_date' => now()->addDay()->toDateString()],
            ['assessment_date' => $rule]
        );

        $this->assertTrue($today->passes());
        $this->assertTrue($future->passes());
    }

    public function test_store_request_rejects_past_dates(): void
    {
        $rule = (new StoreAssessmentRequest())->rules()['assessment_date'];

        $validator = Validator::make(
            ['assessment_date' => now()->subDay()->toDateString()],
            ['assessment_date' => $rule]
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('assessment_date', $validator->errors()->toArray());
    }

    public function test_update_request_accepts_today_and_future_dates(): void
    {
        $rule = (new UpdateAssessmentRequest())->rules()['assessment_date'];

        $today = Validator::make(
            ['assessment_date' => now()->toDateString()],
            ['assessment_date' => $rule]
        );
        $future = Validator::make(
            ['assessment_date' => now()->addDay()->toDateString()],
            ['assessment_date' => $rule]
        );

        $this->assertTrue($today->passes());
        $this->assertTrue($future->passes());
    }

    public function test_update_request_rejects_past_dates(): void
    {
        $rule = (new UpdateAssessmentRequest())->rules()['assessment_date'];

        $validator = Validator::make(
            ['assessment_date' => now()->subDay()->toDateString()],
            ['assessment_date' => $rule]
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('assessment_date', $validator->errors()->toArray());
    }
}

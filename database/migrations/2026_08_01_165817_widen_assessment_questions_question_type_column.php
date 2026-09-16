<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * The original creation migration types question_type as an ENUM
     * limited to yes_no/yes_no_partial/number/text/proportion/select/radio
     * — but the real database already has it as a plain VARCHAR(100), and
     * real data uses a value outside that enum (mortality_three_month,
     * see AssessmentQuestionConfigSeeder). Matches reality: a plain string
     * column, so DynamicFormBuilder can support new question types going
     * forward without needing a migration each time.
     */
    public function up(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->string('question_type', 100)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->enum('question_type', [
                'yes_no',
                'yes_no_partial',
                'number',
                'text',
                'proportion',
                'select',
                'radio',
            ])->change();
        });
    }
};

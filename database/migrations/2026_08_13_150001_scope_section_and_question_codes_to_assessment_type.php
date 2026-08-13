<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // assessment_sections.code and assessment_questions.question_code
        // are already effectively scoped per-template (every section has a
        // required assessment_type_id, every question belongs to a
        // section), but both still carry a leftover GLOBAL unique index
        // from before that scoping existed. A 2026 template reusing the
        // same codes as 2025 (e.g. "infrastructure", "INFRA_Q1" — expected,
        // since they're the same conceptual question) would fail to insert
        // without this fix.
        Schema::table('assessment_sections', function (Blueprint $table) {
            $table->dropUnique('assessment_sections_code_unique');
            $table->unique(['assessment_type_id', 'code']);
        });

        // question_code's scope key is its section's assessment_type_id, one
        // join away — not a column on this table — so a plain composite
        // unique isn't expressible here. Uniqueness of question_code WITHIN
        // a template is enforced at the application level where Phase 2's
        // seeder writes questions (each seeder run scopes its own codes),
        // matching how AssessmentSectionResource/AssessmentQuestionResource
        // already validate section codes without a matching DB constraint
        // for cross-template scoping. The original creation migration
        // already added a plain (non-unique) index on question_code
        // alongside the unique one, so lookups by code stay fast without
        // adding a second index here.
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->dropUnique('assessment_questions_question_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_questions', function (Blueprint $table) {
            $table->unique('question_code');
        });
        Schema::table('assessment_sections', function (Blueprint $table) {
            $table->dropUnique(['assessment_type_id', 'code']);
            $table->unique('code');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rag_eval_cases')) {
            return;
        }

        $cases = [
            ['oxygen therapy, what is it?', ['safe use', 'oxygen']],
            ['tell me more about oxygen therapy', ['oxygen']],
            ['how about hypothermia', ['newborn', 'cold']],
            ['emonc', ['Emergency']],
            ['what does emonc stand for?', ['Emergency']],
            ['tell me more about care of preterms', ['preterm']],
            ['resuscitation module should take how long and what is the breakdown of the sessions', ['135 minutes', 'Resuscitation video', 'Skills teaching', 'Case scenarios']],
            ['show me assessment of newborn', []],
            ['describe assessment of newborn', ['newborn']],
            ['what documents are available', ['documents']],
            ['what is the dosage of surfactant in module 12', []],
            ['who signed the 2027 national guideline', []],
            ['how many sessions are in module 99', []],
        ];

        foreach ($cases as [$question, $mustInclude]) {
            DB::table('rag_eval_cases')->updateOrInsert(
                ['question_hash' => hash('sha256', Str::lower(trim($question)))],
                [
                    'question' => $question,
                    'origin' => 'seed',
                    'frozen' => true,
                    'enabled' => true,
                    'must_include' => json_encode($mustInclude),
                    'expected_decision' => in_array($question, [
                        'what is the dosage of surfactant in module 12',
                        'who signed the 2027 national guideline',
                        'how many sessions are in module 99',
                    ], true) ? 'abstain' : null,
                    'expected_route' => str_contains($question, 'resuscitation module') ? 'local-curriculum' : null,
                    'forbid_title_only' => true,
                    'require_citations' => ! str_contains($question, 'documents available'),
                    'notes' => 'Seeded from docs/rag-adaptive-intelligence-spec.md',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('rag_eval_cases')
            ->where('origin', 'seed')
            ->where('notes', 'Seeded from docs/rag-adaptive-intelligence-spec.md')
            ->delete();
    }
};

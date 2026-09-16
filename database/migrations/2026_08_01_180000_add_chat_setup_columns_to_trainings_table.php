<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            // Which UI produced this pending/completed guided setup —
            // lets PendingGuidedSetupNotice route "Continue" to the right
            // page (guided-setup vs chat-setup). Null for pre-existing rows
            // and for trainings created outside either guided flow.
            $table->string('guided_setup_method')->nullable()->after('guided_setup_draft');

            // Append-only chat transcript for the chat assistant — mirrors
            // guided_setup_draft's role for the wizard, but stores the full
            // rendered message log (not just filled slot values) so a
            // resumed session can replay the conversation instead of just
            // jumping back to the next question.
            $table->json('chat_setup_transcript')->nullable()->after('guided_setup_method');
        });
    }

    public function down(): void
    {
        Schema::table('trainings', function (Blueprint $table) {
            $table->dropColumn(['guided_setup_method', 'chat_setup_transcript']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->foreignId('survey_event_id')->nullable()->after('survey_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('event_instance_number')->nullable()->after('survey_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('survey_event_id');
            $table->dropColumn('event_instance_number');
        });
    }
};

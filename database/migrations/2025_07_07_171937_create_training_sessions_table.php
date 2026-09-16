<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_training_sessions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends  Migration
{
    public function up()
    {
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->onDelete('cascade');
            $table->foreignId('topic_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->string('name'); // e.g., Session 1: Hand Hygiene
            $table->dateTime('session_time');
            $table->foreignId('methodology_id')->nullable()->constrained('methodologies')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('training_sessions');
    }
};

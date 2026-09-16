<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_session_attendance_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends  Migration
{
    public function up()
    {
        Schema::create('session_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained('training_sessions')->onDelete('cascade');
            $table->foreignId('training_participant_id')->constrained('training_participants')->onDelete('cascade');
            $table->boolean('present')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('session_attendance');
    }
};

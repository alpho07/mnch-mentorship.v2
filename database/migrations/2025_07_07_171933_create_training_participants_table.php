<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_training_participants_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrainingParticipantsTable extends Migration
{
    public function up()
    {
        Schema::create('training_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable(); // for non-system participants
            $table->foreignId('cadre_id')->nullable()->constrained('cadres')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_tot')->default(false);
            $table->foreignId('outcome_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('training_participants');
    }
}

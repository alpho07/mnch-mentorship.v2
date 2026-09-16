<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_participant_objective_results_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateParticipantObjectiveResultsTable extends Migration
{
    public function up()
    {
        Schema::create('participant_objective_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objective_id')->constrained('objectives')->onDelete('cascade');
            $table->foreignId('training_participant_id')->constrained('training_participants')->onDelete('cascade');
            $table->bigInteger('grade')->unsigned()->nullable();
            $table->enum('result', ['pass', 'fail']);
            $table->string('comments')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('participant_objective_results');
    }
}

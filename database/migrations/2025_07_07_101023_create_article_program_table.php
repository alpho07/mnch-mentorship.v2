<?php

// database/migrations/2024_07_07_000005_create_article_program_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticleProgramTable extends Migration
{
    public function up()
    {
        Schema::create('article_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('knowledge_base_articles')->onDelete('cascade');
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('article_program');
    }
}

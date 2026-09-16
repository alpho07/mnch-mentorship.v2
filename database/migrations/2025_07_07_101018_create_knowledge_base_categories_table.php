<?php

// database/migrations/2024_07_07_000002_create_knowledge_base_categories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKnowledgeBaseCategoriesTable extends Migration
{
    public function up()
    {
        Schema::create('knowledge_base_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('icon')->nullable(); // For nice look
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('knowledge_base_categories');
    }
}

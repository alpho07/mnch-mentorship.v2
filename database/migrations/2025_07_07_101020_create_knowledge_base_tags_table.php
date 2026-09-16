<?php

// database/migrations/2024_07_07_000003_create_knowledge_base_tags_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKnowledgeBaseTagsTable extends Migration
{
    public function up()
    {
        Schema::create('knowledge_base_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color')->nullable(); // For tag highlight
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('knowledge_base_tags');
    }
}

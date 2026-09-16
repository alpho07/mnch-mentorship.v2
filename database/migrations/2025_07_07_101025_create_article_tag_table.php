<?php

// database/migrations/2024_07_07_000006_create_article_tag_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticleTagTable extends Migration
{
    public function up()
    {
        Schema::create('article_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('knowledge_base_articles')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('knowledge_base_tags')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('article_tag');
    }
}

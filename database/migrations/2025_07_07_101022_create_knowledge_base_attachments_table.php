<?php

// database/migrations/2024_07_07_000004_create_knowledge_base_attachments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKnowledgeBaseAttachmentsTable extends Migration
{
    public function up()
    {
        Schema::create('knowledge_base_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('knowledge_base_articles')->onDelete('cascade');
            $table->string('type'); // pdf, video, image, link, etc.
            $table->string('file_path')->nullable();
            $table->string('external_url')->nullable(); // for links/videos
            $table->string('display_name');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('knowledge_base_attachments');
    }
}

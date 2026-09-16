<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('featured_image')->nullable();
            $table->foreignId('resource_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('resource_categories')->onDelete('set null');
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->enum('visibility', ['public', 'authenticated', 'restricted'])->default('public');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_downloadable')->default(false);
            $table->unsignedBigInteger('download_count')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('like_count')->default(0);
            $table->unsignedBigInteger('dislike_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_type')->nullable();
            $table->string('external_url')->nullable();
            $table->integer('duration')->nullable(); // in seconds
            $table->enum('difficulty_level', ['beginner', 'intermediate', 'advanced'])->nullable();
            $table->json('prerequisites')->nullable();
            $table->json('learning_outcomes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['visibility']);
            $table->index(['resource_type_id']);
            $table->index(['category_id']);
            $table->index(['author_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('resources');
    }
};

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeBaseArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'category_id',
        'author_id',
        'is_published'
    ];

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'article_program', 'article_id', 'program_id');
    }

    public function category()
    {
        return $this->belongsTo(KnowledgeBaseCategory::class, 'category_id');
    }

    public function tags()
    {
        return $this->belongsToMany(KnowledgeBaseTag::class, 'article_tag', 'article_id', 'tag_id');
    }

    public function attachments()
    {
        return $this->hasMany(KnowledgeBaseAttachment::class, 'article_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

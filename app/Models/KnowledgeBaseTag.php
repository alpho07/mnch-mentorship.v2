<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeBaseTag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color'];

    public function articles()
    {
        return $this->belongsToMany(KnowledgeBaseArticle::class, 'article_tag', 'tag_id', 'article_id');
    }
}

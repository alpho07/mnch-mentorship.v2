<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeBaseCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'icon'];

    public function articles()
    {
        return $this->hasMany(KnowledgeBaseArticle::class, 'category_id');
    }
}

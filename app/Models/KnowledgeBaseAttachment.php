<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeBaseAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'article_id',
        'type',
        'file_path',
        'external_url',
        'display_name'
    ];

    public function article()
    {
        return $this->belongsTo(KnowledgeBaseArticle::class, 'article_id');
    }

    // Helper: Get file type
    public function isPdf()
    {
        return $this->type === 'pdf';
    }
    public function isWord()
    {
        return $this->type === 'docx';
    }

    public function isText()
    {
        return $this->type === 'text';
    }

    public function isExcel()
    {
        return $this->type === 'xlsx';
    }
    public function isVideo()
    {
        return $this->type === 'video';
    }
    public function isImage()
    {
        return $this->type === 'image';
    }
    public function isLink()
    {
        return $this->type === 'link';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricItemResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'rubric_assessment_id',
        'rubric_item_id',
        'performed',
    ];

    protected $casts = [
        'performed' => 'boolean',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(RubricAssessment::class, 'rubric_assessment_id');
    }

    public function rubricItem(): BelongsTo
    {
        return $this->belongsTo(RubricItem::class);
    }
}

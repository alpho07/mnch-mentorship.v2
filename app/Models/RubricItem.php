<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubricItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_rubric_id',
        'description',
        'guidance',
        'order_sequence',
        'is_active',
    ];

    protected $casts = [
        'order_sequence' => 'integer',
        'is_active' => 'boolean',
    ];

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(ModuleRubric::class, 'module_rubric_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(RubricItemResponse::class);
    }
}

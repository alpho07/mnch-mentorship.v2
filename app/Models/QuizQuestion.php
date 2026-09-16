<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_module_quiz_id',
        'question_text',
        'explanation',
        'order_sequence',
        'is_active',
    ];

    protected $casts = [
        'order_sequence' => 'integer',
        'is_active' => 'boolean',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(ProgramModuleQuiz::class, 'program_module_quiz_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizOption::class, 'quiz_question_id')
            ->orderBy('order_sequence');
    }

    public function correctOption(): ?QuizOption
    {
        return $this->options()->where('is_correct', true)->first();
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuizResponse::class, 'quiz_question_id');
    }
}

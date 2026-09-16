<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingObjective extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_id',
        'title',
        'description',
        'type', // 'knowledge', 'skill', 'attitude'
        'weight', // percentage weight for final grade
        'pass_criteria', // minimum score to pass
        'order',
    ];

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function participantResults(): HasMany
    {
        return $this->hasMany(ParticipantObjectiveResult::class, 'objective_id');
    }

    public function getPassRateAttribute(): float
    {
        $total = $this->participantResults()->count();
        if ($total === 0) return 0;

        $passed = $this->participantResults()
            ->where('score', '>=', $this->pass_criteria)
            ->count();

        return round(($passed / $total) * 100, 2);
    }
}

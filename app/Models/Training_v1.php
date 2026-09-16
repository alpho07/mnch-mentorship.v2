<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Training_v1 extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'program_id',
        'facility_id',
        'organizer_id',
        'location',
        'start_date',
        'end_date',
        'approach',
        'notes',
        'identifier',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected $with = ['facility', 'program', 'organizer'];

    // Relationships
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'training_departments',
            'training_id',
            'department_id'
        );
    }

    // Query Scopes
    public function scopeByDateRange($query, $start, $end)
    {
        return $query->whereBetween('start_date', [$start, $end]);
    }

    public function scopeByApproach($query, string $approach)
    {
        return $query->where('approach', $approach);
    }

    public function scopeByProgram($query, int $programId)
    {
        return $query->where('program_id', $programId);
    }

    public function scopeByFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>=', now());
    }

    public function scopeCompleted($query)
    {
        return $query->where('end_date', '<', now());
    }

    public function scopeOngoing($query)
    {
        return $query->where('start_date', '<=', now())
                    ->where('end_date', '>=', now());
    }

    // Computed Attributes
    public function getStatusAttribute(): string
    {
        $now = now();

        if ($this->start_date > $now) {
            return 'upcoming';
        } elseif ($this->end_date < $now) {
            return 'completed';
        } else {
            return 'ongoing';
        }
    }

    public function getDurationDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function getParticipantCountAttribute(): int
    {
        return $this->participants()->count();
    }
}

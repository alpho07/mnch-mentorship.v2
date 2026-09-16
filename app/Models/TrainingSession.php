<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainingSession extends Model {

    use HasFactory;

    protected $fillable = [
        'training_id',
        'module_id',
        'methodology_id',
        'title',
        'session_date',
        'start_time',
        'end_time',
        'facilitator_id',
        'location',
        'materials_used',
        'attendance_count',
        'session_notes',
        'status',
    ];
    protected $casts = [
        'session_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'materials_used' => 'array',
    ];

    public function training(): BelongsTo {
        return $this->belongsTo(Training::class);
    }

    public function module(): BelongsTo {
        return $this->belongsTo(Module::class);
    }

    public function methodology(): BelongsTo {
        return $this->belongsTo(Methodology::class);
    }

    public function facilitator(): BelongsTo {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    public function getDurationHoursAttribute(): float {
        if (!$this->start_time || !$this->end_time)
            return 0;

        return $this->start_time->diffInHours($this->end_time);
    }

   
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassModuleActivityParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_module_id',
        'class_participant_id',
        'activity_id',
        'status',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function classModule(): BelongsTo
    {
        return $this->belongsTo(ClassModule::class, 'class_module_id');
    }

    public function classParticipant(): BelongsTo
    {
        return $this->belongsTo(ClassParticipant::class, 'class_participant_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function markCompleted(?int $userId = null): bool
    {
        if ($this->status === 'completed') {
            return false;
        }

        return $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $userId,
        ]);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSession extends Model {

    use HasFactory;

    protected $fillable = [
        'class_module_id',
        'module_session_id',
        'session_number',
        'title',
        'description',
        'scheduled_date',
        'scheduled_time',
        'actual_date',
        'actual_time',
        'duration_minutes',
        'facilitator_id',
        'location',
        'status',
        'attendance_taken',
        'notes',
    ];
    protected $casts = [
        'scheduled_date' => 'date',
        'actual_date' => 'date',
        'session_number' => 'integer',
        'duration_minutes' => 'integer',
        'attendance_taken' => 'boolean',
    ];

    // Relationships
    public function classModule(): BelongsTo {
        return $this->belongsTo(ClassModule::class, 'class_module_id');
    }

    public function moduleSession(): BelongsTo {
        return $this->belongsTo(\App\Models\ModuleSession::class, 'module_session_id');
    }

    public function facilitator(): BelongsTo {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    public function attendanceRecords(): HasMany {
        return $this->hasMany(SessionAttendance::class, 'class_session_id');
    }

    // Query Scopes
    public function scopeScheduled($query) {
        return $query->where('status', 'scheduled');
    }

    public function scopeInProgress($query) {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query) {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query) {
        return $query->where('status', 'cancelled');
    }

    public function scopeByModule($query, int $moduleId) {
        return $query->where('class_module_id', $moduleId);
    }

    public function scopeUpcoming($query) {
        return $query->where('scheduled_date', '>=', now())
                        ->where('status', 'scheduled')
                        ->orderBy('scheduled_date');
    }

    public function scopePast($query) {
        return $query->where('scheduled_date', '<', now())
                        ->orderBy('scheduled_date', 'desc');
    }

    // Computed Attributes
    public function getAttendanceRateAttribute(): float {
        if (!$this->attendance_taken) {
            return 0;
        }

        $total = $this->attendanceRecords()->count();

        if ($total === 0) {
            return 0;
        }

        $present = $this->attendanceRecords()->where('status', 'present')->count();
        return round(($present / $total) * 100, 1);
    }

    // Helper Methods
    public function start(): bool {
        return $this->update([
                    'status' => 'in_progress',
                    'actual_date' => now()->toDateString(),
                    'actual_time' => now()->toTimeString(),
        ]);
    }

    public function complete(): bool {
        return $this->update([
                    'status' => 'completed',
                    'actual_date' => $this->actual_date ?? now()->toDateString(),
        ]);
    }

    public function cancel(): bool {
        return $this->update(['status' => 'cancelled']);
    }

    public function isCompleted(): bool {
        return $this->status === 'completed';
    }
}

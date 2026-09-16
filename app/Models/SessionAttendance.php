<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAttendance extends Model {

    use HasFactory;

    protected $table = 'class_session_attendance';
    protected $fillable = [
        'class_session_id',
        'class_participant_id',
        'status',
        'notes',
        'marked_at',
        'marked_by',
    ];
    protected $casts = [
        'marked_at' => 'datetime',
    ];

    // Relationships
    public function classSession(): BelongsTo {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function classParticipant(): BelongsTo {
        return $this->belongsTo(ClassParticipant::class, 'class_participant_id');
    }

    public function markedBy(): BelongsTo {
        return $this->belongsTo(User::class, 'marked_by');
    }

    // Query Scopes
    public function scopePresent($query) {
        return $query->where('status', 'present');
    }

    public function scopeAbsent($query) {
        return $query->where('status', 'absent');
    }

    public function scopeExcused($query) {
        return $query->where('status', 'excused');
    }

    public function scopeLate($query) {
        return $query->where('status', 'late');
    }

    public function scopeBySession($query, int $sessionId) {
        return $query->where('session_id', $sessionId);
    }

    public function scopeByParticipant($query, int $participantId) {
        return $query->where('class_participant_id', $participantId);
    }

    // Helper Methods
    public function markPresent(): bool {
        return $this->update(['status' => 'present']);
    }

    public function markAbsent(): bool {
        return $this->update(['status' => 'absent']);
    }

    public function markExcused(string $reason = null): bool {
        return $this->update([
                    'status' => 'excused',
                    'notes' => $reason,
        ]);
    }

    public function markLate(string $reason = null): bool {
        return $this->update([
                    'status' => 'late',
                    'notes' => $reason,
        ]);
    }

    public function isPresent(): bool {
        return $this->status === 'present';
    }

    public function isAbsent(): bool {
        return $this->status === 'absent';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Immutable attendance record.
 *
 * DOMAIN INVARIANTS:
 * - UNIQUE(class_id, COALESCE(session_id, 0), user_id)
 * - Records are IMMUTABLE once created (no update, no delete)
 * - Created only via AttendanceService
 */
class ClassAttendance extends Model
{
    protected $table = 'class_attendances';

    protected $fillable = [
        'class_id',
        'class_module_id',
        'session_id',
        'user_id',
        'marked_by',
        'marked_at',
        'source',
    ];

    protected $casts = [
        'marked_at' => 'datetime',
    ];

    // ==========================================
    // IMMUTABILITY ENFORCEMENT
    // ==========================================

    /**
     * Prevent updates. Attendance records are immutable once confirmed.
     */
    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new LogicException(
                'ClassAttendance records are immutable. Cannot update attendance ID: '.$this->id
            );
        }

        return parent::save($options);
    }

    /**
     * Prevent deletes. Attendance records are immutable.
     */
    public function delete()
    {
        throw new LogicException(
            'ClassAttendance records are immutable. Cannot delete attendance ID: '.$this->id
        );
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function mentorshipClass(): BelongsTo
    {
        return $this->belongsTo(MentorshipClass::class, 'class_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function classModule(): BelongsTo
    {
        return $this->belongsTo(ClassModule::class, 'class_module_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeForClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeForSession($query, ?int $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeAutoMarked($query)
    {
        return $query->where('source', 'auto');
    }

    public function scopeManuallyMarked($query)
    {
        return $query->where('source', 'manual');
    }
}

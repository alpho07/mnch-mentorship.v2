<?php

namespace App\Models;

use App\Services\EmoncNotificationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClassModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'mentorship_class_id',
        'program_module_id',
        'status',
        'started_at',
        'completed_at',
        'requires_assessment',
        'min_attendance_percentage',
        'order_sequence',
        'start_date',
        'end_date',
        'notes',
        'attendance_token',
        'attendance_link_active',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'requires_assessment' => 'boolean',
        'attendance_link_active' => 'boolean',
        'min_attendance_percentage' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function mentorshipClass(): BelongsTo
    {
        return $this->belongsTo(MentorshipClass::class, 'mentorship_class_id');
    }

    public function programModule(): BelongsTo
    {
        return $this->belongsTo(ProgramModule::class, 'program_module_id');
    }

    public function activityEnrollments(): HasMany
    {
        return $this->hasMany(ClassModuleActivityParticipant::class, 'class_module_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'class_module_id')
            ->orderBy('session_number');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ModuleAssessment::class, 'class_module_id');
    }

    public function menteeProgress(): HasMany
    {
        return $this->hasMany(MenteeModuleProgress::class, 'class_module_id');
    }

    /**
     * Authoritative module-level attendance records.
     * Written by:
     *   - Mentee clicking attendance link  → source = 'link'
     *   - Mentor marking manually          → source = 'manual'
     * NOT written by class enrollment (those have class_module_id = null).
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(ClassAttendance::class, 'class_module_id');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lifecycle Guards
    // ─────────────────────────────────────────────────────────────────────────

    public function canStart(): bool
    {
        if ($this->status !== 'not_started') {
            return false;
        }

        // Query fresh from DB — in-memory relationship may hold stale class status.
        $classStatus = \App\Models\MentorshipClass::where('id', $this->mentorship_class_id)
            ->value('status');

        return $classStatus === 'active';
    }

    public function canComplete(): bool
    {
        return $this->status === 'in_progress';
    }

    public function canBeRemoved(): bool
    {
        return $this->status === 'not_started' && $this->sessions()->count() === 0 && $this->menteeProgress()->count() === 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lifecycle Transitions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Start the module.
     *
     * What changes:
     *  - module.status          → 'in_progress'
     *  - attendance_token       → generated (once)
     *  - attendance_link_active → true
     *  - mentee_module_progress → 'not_started' rows created for every enrolled mentee
     *                             (status is NOT forced to in_progress — mentees must confirm
     *                              attendance themselves via the link)
     */
    public function start(): void
    {
        if (! $this->canStart()) {
            throw new \LogicException(
                "Module [{$this->id}] cannot be started in its current state: {$this->status}"
            );
        }

        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'attendance_token' => $this->attendance_token ?? Str::random(32),
            'attendance_link_active' => true,
        ]);

        // Ensure every enrolled mentee has a progress placeholder row.
        // Status = 'not_started' until THEY confirm attendance via the link.
        $this->ensureMenteeProgressRecords();
    }

    /**
     * Complete the module (mentor action after teaching is done).
     *
     * What changes:
     *  - module.status          → 'completed'
     *  - attendance_link_active → false  (link deactivated)
     *  - mentee_module_progress:
     *      • Has ClassAttendance record  → 'completed', attendance_pct = 100
     *      • No  ClassAttendance record  → stays 'not_started' (absent)
     *      • Already 'exempted'          → untouched
     */
    public function complete(): void
    {
        if (! $this->canComplete()) {
            throw new \LogicException(
                "Module [{$this->id}] cannot be completed in its current state: {$this->status}"
            );
        }

        DB::transaction(function () {
            $this->update([
                'status' => 'completed',
                'completed_at' => now(),
                'attendance_link_active' => false,
            ]);

            $enrolledParticipants = ClassParticipant::where('mentorship_class_id', $this->mentorship_class_id)
                ->whereIn('status', ['enrolled', 'active'])
                ->get();

            if ($enrolledParticipants->isEmpty()) {
                return;
            }

            // Collect user IDs that are confirmed present for THIS module.
            // Uses MenteeModuleProgress (in_progress/completed) as source of truth —
            // handles both new records (class_module_id set on ClassAttendance) and
            // legacy records (class_module_id = null on ClassAttendance).
            $confirmedParticipantIds = MenteeModuleProgress::where('class_module_id', $this->id)
                ->whereIn('status', ['in_progress', 'completed'])
                ->pluck('class_participant_id')
                ->toArray();

            foreach ($enrolledParticipants as $participant) {
                $attended = in_array($participant->id, $confirmedParticipantIds);

                if ($attended) {
                    // Attended → mark completed.
                    // Two-step firstOrCreate then update avoids passing DB::raw()
                    // in the updateOrCreate values array, which triggers a TypeError
                    // when Eloquent's casting layer calls preg_match on the Expression object.
                    $progress = MenteeModuleProgress::firstOrCreate(
                        [
                            'class_participant_id' => $participant->id,
                            'class_module_id' => $this->id,
                        ],
                        [
                            'status' => 'completed',
                            'started_at' => now(),
                            'completed_at' => now(),
                            'attendance_percentage' => 100.0,
                        ]
                    );

                    if (! $progress->wasRecentlyCreated) {
                        $progress->update([
                            'status' => 'completed',
                            'started_at' => $progress->started_at ?? now(),
                            'completed_at' => now(),
                            'attendance_percentage' => 100.0,
                        ]);
                    }

                    app(EmoncNotificationService::class)->moduleCompleted($progress);
                } else {
                    // Absent → leave 'exempted' untouched, others go to 'not_started'
                    MenteeModuleProgress::where('class_participant_id', $participant->id)
                        ->where('class_module_id', $this->id)
                        ->whereNotIn('status', ['exempted'])
                        ->update([
                            'status' => 'not_started',
                            'attendance_percentage' => 0.0,
                            'completed_at' => null,
                        ]);
                }
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Auto-Session Creation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Auto-populate class_sessions from program module template sessions.
     * Safe to call multiple times — skips if sessions already exist.
     */
    public function autoCreateSessions(): int
    {
        if ($this->sessions()->exists()) {
            return 0;
        }

        $templateSessions = ModuleSession::where('program_module_id', $this->program_module_id)
            ->where('is_active', true)
            ->orderBy('order_sequence')
            ->get();

        if ($templateSessions->isEmpty()) {
            return 0;
        }

        $created = 0;
        foreach ($templateSessions as $index => $template) {
            ClassSession::create([
                'class_module_id' => $this->id,
                'module_session_id' => $template->id,
                'session_number' => $template->order_sequence ?: ($index + 1),
                'title' => $template->name,
                'description' => $template->description,
                'duration_minutes' => $template->time_minutes,
                'status' => 'scheduled',
                'attendance_taken' => false,
            ]);
            $created++;
        }

        return $created;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Attendance Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Number of mentees confirmed present for this module.
     *
     * Uses MenteeModuleProgress as source of truth (status = in_progress or completed).
     * This correctly handles both:
     *  - New records: ClassAttendance with class_module_id set + progress updated
     *  - Legacy records: ClassAttendance with class_module_id = null + progress updated
     */
    public function confirmedAttendanceCount(): int
    {
        return MenteeModuleProgress::where('class_module_id', $this->id)
            ->whereIn('status', ['in_progress', 'completed'])
            ->count();
    }

    /**
     * Total enrolled mentees in the parent class.
     */
    public function enrolledMenteeCount(): int
    {
        return ClassParticipant::where('mentorship_class_id', $this->mentorship_class_id)
            ->whereIn('status', ['enrolled', 'active'])
            ->count();
    }

    /**
     * Attendance rate as percentage (0–100).
     */
    public function attendanceRate(): float
    {
        $total = $this->enrolledMenteeCount();
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->confirmedAttendanceCount() / $total) * 100, 1);
    }

    /**
     * Whether a given user is confirmed present for this module.
     *
     * Checks MenteeModuleProgress (status = in_progress or completed) as primary source.
     * Falls back to ClassAttendance for forward compatibility.
     */
    public function hasUserConfirmedAttendance(int $userId): bool
    {
        $participant = ClassParticipant::where('mentorship_class_id', $this->mentorship_class_id)
            ->where('user_id', $userId)
            ->first();

        if ($participant) {
            $hasProgress = MenteeModuleProgress::where('class_participant_id', $participant->id)
                ->where('class_module_id', $this->id)
                ->whereIn('status', ['in_progress', 'completed'])
                ->exists();

            if ($hasProgress) {
                return true;
            }
        }

        // Fallback: direct ClassAttendance record (new records have class_module_id set)
        return $this->attendanceRecords()->where('user_id', $userId)->exists();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a mentee_module_progress placeholder row for every enrolled mentee.
     * Called on start(). Status is 'not_started' — mentees must confirm attendance
     * themselves via the attendance link to move to 'in_progress'.
     *
     * Idempotent — never overwrites existing progress rows.
     */
    private function ensureMenteeProgressRecords(): void
    {
        $participants = ClassParticipant::where('mentorship_class_id', $this->mentorship_class_id)
            ->whereIn('status', ['enrolled', 'active'])
            ->get();

        foreach ($participants as $participant) {
            MenteeModuleProgress::firstOrCreate(
                [
                    'class_participant_id' => $participant->id,
                    'class_module_id' => $this->id,
                ],
                [
                    'status' => 'not_started',
                ]
            );
            // Do NOT update existing rows here — if a mentee already confirmed
            // attendance (progress = in_progress), we must not reset them.
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Display Helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'not_started' => 'gray',
            'in_progress' => 'warning',
            'completed' => 'success',
            default => 'gray',
        };
    }
}

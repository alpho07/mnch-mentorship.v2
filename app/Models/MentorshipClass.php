<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class MentorshipClass extends Model {

    use HasFactory,
        SoftDeletes;

    protected $fillable = [
        'training_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'created_by',
        'notes',
        'enrollment_token',
        'enrollment_link_active',
    ];
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'enrollment_link_active' => 'boolean',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function training(): BelongsTo {
        return $this->belongsTo(Training::class);
    }

    public function creator(): BelongsTo {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function classModules(): HasMany {
        return $this->hasMany(ClassModule::class, 'mentorship_class_id');
    }

    public function participants(): HasMany {
        return $this->hasMany(ClassParticipant::class, 'mentorship_class_id');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Computed Attributes
    // ─────────────────────────────────────────────────────────────────────────

    public function getModuleCountAttribute(): int {
        return $this->classModules()->count();
    }

    public function getSessionCountAttribute(): int {
        return ClassSession::whereHas('classModule', function ($query) {
                    $query->where('mentorship_class_id', $this->id);
                })->count();
    }

    public function getCompletedModulesCountAttribute(): int {
        return $this->classModules()->where('status', 'completed')->count();
    }

    public function getProgressPercentageAttribute(): float {
        $totalModules = $this->module_count;
        if ($totalModules === 0) {
            return 0;
        }
        return round(($this->completed_modules_count / $totalModules) * 100, 1);
    }

    public function getDurationDaysAttribute(): int {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Query Scopes
    // ─────────────────────────────────────────────────────────────────────────

    public function scopeActive($query) {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query) {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled($query) {
        return $query->where('status', 'cancelled');
    }

    public function scopeByTraining($query, int $trainingId) {
        return $query->where('training_id', $trainingId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lifecycle Guards
    // ─────────────────────────────────────────────────────────────────────────

    public function canStart(): bool {
        return $this->status === 'draft' && $this->classModules()->exists() && $this->participants()->exists();
    }

    public function canEnd(): bool {
        return $this->status === 'active';
    }

    public function canBeDeleted(): bool {
        return $this->classModules()
                        ->whereHas('sessions', fn($q) => $q->where('status', 'completed'))
                        ->doesntExist();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lifecycle Transitions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * START CLASS
     *
     * - Sets class status = active
     * - Starts ALL modules (not_started → in_progress)
     * - Each module.start() opens attendance link + creates progress records
     * - Enrollment link stays open so late enrollees can still join
     */
    public function start(): void {
        if (!$this->canStart()) {
            throw new \LogicException(
                            "Class [{$this->id}] cannot be started. Status: {$this->status}. " .
                            "Ensure the class has modules and enrolled mentees."
                    );
        }

        DB::transaction(function () {
            $this->update([
                'status' => 'active',
                'start_date' => $this->start_date ?? now()->toDateString(),
            ]);

            // Start every module that is still not_started
            $modules = $this->classModules()->where('status', 'not_started')->get();
            foreach ($modules as $module) {
                $module->start();
            }
        });
    }

    /**
     * END CLASS (complete)
     *
     * - Completes ALL in_progress modules (closes attendance links, finalizes progress)
     * - Disables enrollment link — no new enrollees after this
     * - Sets class status = completed
     * - Marks all still-enrolled participants as completed
     *
     * After this: enrollment links dead, attendance links dead, no changes possible.
     */
    public function complete(): void {
        if (!$this->canEnd()) {
            throw new \LogicException(
                            "Class [{$this->id}] cannot be ended. Status: {$this->status}."
                    );
        }

        DB::transaction(function () {
            // 1. Complete every module still in_progress
            $modules = $this->classModules()->where('status', 'in_progress')->get();
            foreach ($modules as $module) {
                $module->complete();
            }

            // 2. Lock the enrollment link — no more mentees can join
            $this->update([
                'status' => 'completed',
                'end_date' => now()->toDateString(),
                'enrollment_link_active' => false,
            ]);

            // 3. Mark all still-enrolled participants as completed
            $this->participants()
                    ->whereIn('status', ['enrolled', 'active'])
                    ->update([
                        'status' => 'completed',
                        'completed_at' => now(),
            ]);
        });
    }

    /**
     * Activate without starting modules — kept for backward compat.
     * Use start() for the full flow.
     */
    public function activate(): bool {
        return $this->update(['status' => 'active']);
    }

    public function cancel(): bool {
        DB::transaction(function () {
            // Close all links
            $this->update([
                'status' => 'cancelled',
                'enrollment_link_active' => false,
            ]);

            // Close all module attendance links
            $this->classModules()->update(['attendance_link_active' => false]);
        });

        return true;
    }
}

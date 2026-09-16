<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantStatusLog extends Model {

    use HasFactory;

    protected $fillable = [
        'training_participant_id',
        'mentorship_participant_id',
        'month_number',
        'status_type',
        'old_value',
        'new_value',
        'notes',
        'recorded_by',
        'recorded_at',
    ];
    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    // Status Types Constants
    const STATUS_TYPE_OVERALL = 'overall_status';
    const STATUS_TYPE_CADRE = 'cadre_change';
    const STATUS_TYPE_DEPARTMENT = 'department_change';
    const STATUS_TYPE_FACILITY = 'facility_change';
    const STATUS_TYPE_COUNTY = 'county_change';
    const STATUS_TYPE_SUBCOUNTY = 'subcounty_change';
    // Overall Status Values
    const OVERALL_ACTIVE = 'active';
    const OVERALL_RETIRED = 'retired';
    const OVERALL_DECEASED = 'deceased';
    const OVERALL_TRANSFERRED = 'transferred';
    const OVERALL_STUDY_LEAVE = 'study_leave';
    const OVERALL_TERMINATED = 'terminated';
    const OVERALL_UNKNOWN = 'unknown';

    public static function getStatusTypes(): array {
        return [
            self::STATUS_TYPE_OVERALL => 'Overall Status',
            self::STATUS_TYPE_CADRE => 'Cadre Change',
            self::STATUS_TYPE_DEPARTMENT => 'Department Change',
            self::STATUS_TYPE_FACILITY => 'Facility Change',
            self::STATUS_TYPE_COUNTY => 'County Change',
            self::STATUS_TYPE_SUBCOUNTY => 'Subcounty Change',
        ];
    }

    public static function getOverallStatuses(): array {
        return MenteeStatus::where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'name')
                        ->toArray();
    }

    public static function getMonthOptions(): array {
        return [
            3 => '3 Months Post-Training',
            6 => '6 Months Post-Training',
            12 => '12 Months Post-Training',
        ];
    }

    // Boot method to auto-set recorded_at and recorded_by
    protected static function boot() {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->recorded_at) {
                $model->recorded_at = now();
            }
            if (!$model->recorded_by && auth()->check()) {
                $model->recorded_by = auth()->id();
            }
        });
    }

    // Relationships
    public function trainingParticipant(): BelongsTo {
        return $this->belongsTo(TrainingParticipant::class);
    }

    public function mentorshipParticipant(): BelongsTo {
        return $this->belongsTo(TrainingParticipant::class, 'mentorship_participant_id');
        // ⬆️ Replace with MentorshipParticipant if you later separate mentees into their own table
    }

    public function recorder(): BelongsTo {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}

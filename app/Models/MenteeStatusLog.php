<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenteeStatusLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'previous_status',
        'new_status',
        'effective_date',
        'reason',
        'notes',
        'changed_by',
        'facility_id',
    ];

    protected $casts = [
        'effective_date' => 'date',
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_RESIGNED = 'resigned';
    const STATUS_TRANSFERRED = 'transferred';
    const STATUS_RETIRED = 'retired';
    const STATUS_DEFECTED = 'defected';
    const STATUS_STUDY_LEAVE = 'study_leave';
    const STATUS_DECEASED = 'deceased';
    const STATUS_SUSPENDED = 'suspended';

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    // Computed Attributes
    public function getStatusColorAttribute(): string
    {
        return match($this->new_status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_STUDY_LEAVE => 'info',
            self::STATUS_TRANSFERRED => 'warning',
            self::STATUS_RESIGNED, self::STATUS_RETIRED => 'secondary',
            self::STATUS_DEFECTED, self::STATUS_DECEASED, self::STATUS_SUSPENDED => 'danger',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->new_status));
    }

    public function getIsActiveStatusAttribute(): bool
    {
        return in_array($this->new_status, [self::STATUS_ACTIVE, self::STATUS_STUDY_LEAVE]);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('new_status', [self::STATUS_ACTIVE, self::STATUS_STUDY_LEAVE]);
    }

    public function scopeInactive($query)
    {
        return $query->whereNotIn('new_status', [self::STATUS_ACTIVE, self::STATUS_STUDY_LEAVE]);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('new_status', $status);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Static methods
    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_RESIGNED => 'Resigned',
            self::STATUS_TRANSFERRED => 'Transferred',
            self::STATUS_RETIRED => 'Retired',
            self::STATUS_DEFECTED => 'Defected',
            self::STATUS_STUDY_LEAVE => 'Study Leave',
            self::STATUS_DECEASED => 'Deceased',
            self::STATUS_SUSPENDED => 'Suspended',
        ];
    }

    public static function getAttritionStatuses(): array
    {
        return [
            self::STATUS_RESIGNED,
            self::STATUS_TRANSFERRED,
            self::STATUS_RETIRED,
            self::STATUS_DEFECTED,
            self::STATUS_DECEASED,
            self::STATUS_SUSPENDED,
        ];
    }
}
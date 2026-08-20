<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens,Notifiable;
    use HasFactory,
        HasRoles,
        Notifiable,
        SoftDeletes;

    // HasResourceInteractions; // Add the resource interactions trait

    protected $fillable = [
        'facility_id',
        'department_id',
        'cadre_id',
        'name',
        'role',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'id_number',
        'phone',
        'status',
        'can_create_mentorships',
        'supervisor_id',
        'password',
        'email_verified_at',
        'county_id', // Add if missing
        'program_scope',
        'report_section_preferences',
    ];

    /**
     * Mentor-tier roles eligible for per-user Program Scope. Senior/admin
     * roles (super_admin, admin, division, national_mentor_lead, etc.) are
     * deliberately excluded — they always see every program.
     */
    public const PROGRAM_SCOPED_ROLES = [
        'facility_mentor',
        'facility_mentor_lead',
        'spoke_mentor',
        'spoke_mentor_lead',
        'county_mentor_lead',
        'subcounty_mentor_lead',
        'national_mentor',
    ];

    public const PROGRAM_SCOPE_OPTIONS = [
        'both' => 'Both (All Programs)',
        'emonc' => 'EmONC Only',
        'newborn' => 'Newborn Care Only',
        'infant_child' => 'Infant & Child Care Only',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_create_mentorships' => 'boolean',
            'supervisor_id' => 'integer',
            'report_section_preferences' => 'array',
        ];
    }

    /**
     * Which toggleable PDF report sections this user has chosen to include
     * — the keys from AssessmentPdfReportService::TOGGLEABLE_SECTIONS. All
     * sections are enabled by default until the user saves a selection.
     *
     * @return array<int, string>
     */
    public function enabledReportSections(): array
    {
        return $this->report_section_preferences ?? array_keys(\App\Services\AssessmentPdfReportService::TOGGLEABLE_SECTIONS);
    }

    /**
     * Program IDs this user is restricted to, or null when unrestricted
     * (Program Scoping is off in Mentorship Settings, this user is a
     * super_admin, this user's role isn't in PROGRAM_SCOPED_ROLES, or their
     * scope is "both"). Callers should treat null as "no filter" rather
     * than "empty result".
     *
     * super_admin is checked explicitly here — not just left to
     * PROGRAM_SCOPED_ROLES exclusion — so a user holding super_admin
     * alongside a mentor-tier role (multi-role) is never scoped either.
     */
    public function allowedProgramIds(): ?array
    {
        if ($this->hasRole('super_admin')) {
            return null;
        }

        if (! \App\Models\Setting::getBool(\App\Models\Setting::PROGRAM_SCOPING_ENABLED, false)) {
            return null;
        }

        if (! $this->hasRole(self::PROGRAM_SCOPED_ROLES)) {
            return null;
        }

        $scope = $this->program_scope ?: 'both';

        if ($scope === 'both') {
            return null;
        }

        $query = Program::query();

        $query = match ($scope) {
            'emonc' => $query->where('name', 'like', '%EmONC%'),
            'newborn' => $query->where('name', 'Newborn Care'),
            'infant_child' => $query->where('name', 'Infant and Child Care'),
            default => $query->whereRaw('1 = 0'),
        };

        return $query->pluck('id')->toArray();
    }

    public function supervisor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function supervisees(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class, 'supervisor_id');
    }

    public function sendPasswordResetNotification($token): void
    {
        $url = url('/admin/set-password/'.$token.'?email='.urlencode($this->email));

        $this->notify(new class($url) extends \Illuminate\Notifications\Notification
        {
            public function __construct(protected string $url) {}

            public function via($notifiable): array
            {
                return ['mail'];
            }

            public function toMail($notifiable): \Illuminate\Notifications\Messages\MailMessage
            {
                return (new \Illuminate\Notifications\Messages\MailMessage)
                    ->subject('Reset Your MNCH Password')
                    ->greeting('Hello!')
                    ->line('We received a request to reset your password.')
                    ->action('Reset Password', $this->url)
                    ->line('This link expires in 60 minutes.')
                    ->line('If you did not request this, ignore this email.');
            }
        });
    }

    public function sendPasswordResetNotification1($token)
    {
        $url = url('/admin/password-reset/'.$token.'?email='.urlencode($this->email));

        $this->notify(new class($url) extends Notification
        {
            protected string $url;

            public function __construct($url)
            {
                $this->url = $url;
            }

            public function via($notifiable)
            {
                return ['mail'];
            }

            public function toMail($notifiable)
            {
                return (new MailMessage)
                    ->subject('Reset Your Password')
                    ->line('Click the button below to reset your password.')
                    ->action('Reset Password', $this->url)
                    ->line('If you did not request a password reset, no further action is required.');
            }
        });
    }

    public function placementLogs()
    {
        return $this->hasMany(\App\Models\MenteePlacementLog::class, 'user_id');
    }

    // Computed Attributes

    public function getFullName1Attribute(): string
    {
        return $this->full_name;
    }

    public function getFullNameAttribute(): string
    {
        $firstName = trim($this->first_name ?? '');
        $lastName = trim($this->last_name ?? '');
        $name = trim($this->name ?? '');

        // Priority 1: If BOTH first_name AND last_name exist, use them
        if ($firstName !== '' && $lastName !== '') {
            return trim("{$firstName} {$lastName}");
        }

        // Priority 2: If only first_name exists, combine with last_name (even if null)
        if ($firstName !== '') {
            $fullName = trim("{$firstName} {$lastName}");

            return $fullName !== '' ? $fullName : $firstName;
        }

        // Priority 3: Fall back to name field
        if ($name !== '') {
            return $name;
        }

        // Priority 4: Use last_name if it's the only thing available
        if ($lastName !== '') {
            return $lastName;
        }

        // Fallback: No name available
        return 'No name provided';
    }

    // Relationships
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function cadre(): BelongsTo
    {
        return $this->belongsTo(Cadre::class);
    }

    public function organizedTrainings(): HasMany
    {
        return $this->hasMany(Training::class, 'organizer_id');
    }

    public function trainingParticipations(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    public function classParticipations(): HasMany
    {
        return $this->hasMany(ClassParticipant::class);
    }

    // Many-to-many relationships for user access scoping
    public function counties(): BelongsToMany
    {
        return $this->belongsToMany(County::class, 'county_user');
    }

    public function subcounties(): BelongsToMany
    {
        return $this->belongsToMany(Subcounty::class, 'subcounty_user');
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'facility_user');
    }

    // Resource relationships
    public function authoredResources(): HasMany
    {
        return $this->hasMany(Resource::class, 'author_id');
    }

    public function resourceComments(): HasMany
    {
        return $this->hasMany(ResourceComment::class);
    }

    public function resourceInteractions(): HasMany
    {
        return $this->hasMany(ResourceInteraction::class);
    }

    public function accessGroups(): BelongsToMany
    {
        return $this->belongsToMany(AccessGroup::class, 'access_group_users');
    }

    public function resourceViews(): HasMany
    {
        return $this->hasMany(ResourceView::class);
    }

    public function resourceDownloads(): HasMany
    {
        return $this->hasMany(ResourceDownload::class);
    }

    // Authorization Helper Methods
    public function isAboveSite(): bool
    {
        return $this->hasRole(['super_admin', 'admin', 'division', 'national', 'division_lead', 'national_mentor_lead']);
    }

    public function canCreateMentorships(): bool
    {
        return (bool) $this->can_create_mentorships;
    }

    public function scopedCountyIds()
    {
        return $this->isAboveSite() ? County::pluck('id') : $this->counties()->pluck('counties.id');
    }

    public function scopedSubcountyIds()
    {
        return $this->isAboveSite() ? Subcounty::pluck('id') : $this->subcounties()->pluck('id');
    }

    public function scopedFacilityIds()
    {
        // Enhanced for resource access
        if ($this->isAboveSite()) {
            return Facility::pluck('id')->toArray();
        }

        $facilityIds = [];

        // User's own facility
        if ($this->facility_id) {
            $facilityIds[] = $this->facility_id;
        }

        // Facilities from direct assignment
        $facilityIds = array_merge(
            $facilityIds,
            $this->facilities()->pluck('facilities.id')->toArray()
        );

        // Facilities from county assignment
        if ($this->hasRole(['county_admin', 'County Mentor Lead']) && $this->county_id) {
            $facilityIds = array_merge(
                $facilityIds,
                Facility::where('county_id', $this->county_id)->pluck('id')->toArray()
            );
        }

        return array_unique($facilityIds);
    }

    public function canAccessFacility(int $facilityId): bool
    {
        return $this->isAboveSite() || in_array($facilityId, $this->scopedFacilityIds(), true);
    }

    // Resource-specific methods (simplified with trait)
    public function hasLikedResource(Resource $resource): bool
    {
        return $this->hasInteractedWith($resource, 'like');
    }

    public function hasDislikedResource(Resource $resource): bool
    {
        return $this->hasInteractedWith($resource, 'dislike');
    }

    public function hasBookmarkedResource(Resource $resource): bool
    {
        return $this->hasInteractedWith($resource, 'bookmark');
    }

    public function hasDownloaded(Resource $resource): bool
    {
        return $this->resourceDownloads()
            ->where('resource_id', $resource->id)
            ->exists();
    }

    public function toggleResourceInteraction(Resource $resource, string $type): bool
    {
        $existing = $this->resourceInteractions()
            ->where('resource_id', $resource->id)
            ->where('type', $type)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        $this->resourceInteractions()->create([
            'resource_id' => $resource->id,
            'type' => $type,
            'ip_address' => request()->ip(),
        ]);

        return true;
    }

    public function canAccessResource(Resource $resource): bool
    {
        return $resource->canUserAccess($this);
    }

    // Query Scopes
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    // Mentee tracking relationships and methods
    public function statusLogs(): HasMany
    {
        return $this->hasMany(MenteeStatusLog::class)->orderBy('effective_date', 'desc');
    }

    public function assessmentResults(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            MenteeAssessmentResult::class,
            TrainingParticipant::class,
            'user_id',
            'participant_id',
            'id',
            'id'
        );
    }

    // Computed Attributes for Mentee Profile
    public function getCurrentStatusAttribute(): string
    {
        $latestLog = $this->statusLogs()->first();

        return $latestLog?->new_status ?? MenteeStatusLog::STATUS_ACTIVE;
    }

    public function getCurrentStatusLogAttribute(): ?MenteeStatusLog
    {
        return $this->statusLogs()->first();
    }

    public function getOverallTrainingScoreAttribute(): ?float
    {
        return $this->trainingParticipations()
            ->whereHas('objectiveResults')
            ->with('objectiveResults')
            ->get()
            ->map(function ($participation) {
                return $participation->objectiveResults->avg('score');
            })
            ->filter()
            ->avg();
    }

    public function getTrainingCompletionRateAttribute(): float
    {
        $totalTrainings = $this->trainingParticipations()->count();
        if ($totalTrainings === 0) {
            return 0;
        }

        $completedTrainings = $this->trainingParticipations()
            ->where('completion_status', 'completed')
            ->count();

        return round(($completedTrainings / $totalTrainings) * 100, 1);
    }

    public function getTrainingHistorySummaryAttribute(): array
    {
        $participations = $this->trainingParticipations()
            ->with(['training', 'objectiveResults'])
            ->get();

        return [
            'total_trainings' => $participations->count(),
            'completed' => $participations->where('completion_status', 'completed')->count(),
            'passed' => $participations->filter(function ($p) {
                $avgScore = $p->objectiveResults->avg('score');

                return $avgScore && $avgScore >= 70;
            })->count(),
            'average_score' => $this->overall_training_score,
            'latest_training' => $participations->sortByDesc('registration_date')->first()?->training?->title,
        ];
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->current_status, [
            MenteeStatusLog::STATUS_ACTIVE,
            MenteeStatusLog::STATUS_STUDY_LEAVE,
        ]);
    }

    public function getPerformanceTrendAttribute(): string
    {
        $recentScores = $this->trainingParticipations()
            ->with('objectiveResults')
            ->latest('completion_date')
            ->limit(3)
            ->get()
            ->map(fn ($p) => $p->objectiveResults->avg('score'))
            ->filter()
            ->values();

        if ($recentScores->count() < 2) {
            return 'Insufficient Data';
        }

        $firstScore = $recentScores->last();
        $latestScore = $recentScores->first();
        $improvement = $latestScore - $firstScore;

        if ($improvement > 5) {
            return 'Improving';
        }
        if ($improvement < -5) {
            return 'Declining';
        }

        return 'Stable';
    }

    // Methods for mentee management
    public function updateStatus(
        string $newStatus,
        ?string $reason = null,
        ?string $notes = null,
        $effectiveDate = null
    ): MenteeStatusLog {
        $currentStatus = $this->current_status;

        $statusLog = $this->statusLogs()->create([
            'previous_status' => $currentStatus,
            'new_status' => $newStatus,
            'effective_date' => $effectiveDate ?? now(),
            'reason' => $reason,
            'notes' => $notes,
            'changed_by' => auth()->id(),
            'facility_id' => $this->facility_id,
        ]);

        if ($this->hasAttribute('status')) {
            $this->update(['status' => $newStatus]);
        }

        return $statusLog;
    }

    public function getMentorshipTrainings()
    {
        return $this->trainingParticipations()
            ->whereHas('training', function ($query) {
                $query->where('type', 'facility_mentorship');
            })
            ->with(['training', 'objectiveResults.assessmentCategory']);
    }

    public function getAttritionRisk(): string
    {
        $riskFactors = 0;

        if ($this->performance_trend === 'Declining') {
            $riskFactors++;
        }

        if ($this->training_completion_rate < 70) {
            $riskFactors++;
        }

        if ($this->overall_training_score && $this->overall_training_score < 60) {
            $riskFactors++;
        }

        $lastTraining = $this->trainingParticipations()->latest('registration_date')->first();
        if (! $lastTraining || $lastTraining->registration_date < now()->subMonths(6)) {
            $riskFactors++;
        }

        return match ($riskFactors) {
            0, 1 => 'Low',
            2 => 'Medium',
            default => 'High',
        };
    }

    // Scopes for mentee queries
    public function scopeActiveMentees($query)
    {
        return $query->whereHas('statusLogs', function ($q) {
            $q->whereIn('new_status', [
                MenteeStatusLog::STATUS_ACTIVE,
                MenteeStatusLog::STATUS_STUDY_LEAVE,
            ])->latest('effective_date')->limit(1);
        })->orWhereDoesntHave('statusLogs');
    }

    public function scopeByCurrentStatus($query, string $status)
    {
        return $query->whereHas('statusLogs', function ($q) use ($status) {
            $q->where('new_status', $status)
                ->latest('effective_date')
                ->limit(1);
        });
    }

    public function scopeHighPerformers($query)
    {
        return $query->whereHas('trainingParticipations.objectiveResults', function ($q) {
            $q->havingRaw('AVG(score) >= 85');
        });
    }

    public function scopeAtRisk($query)
    {
        return $query->whereHas('trainingParticipations', function ($q) {
            $q->where('completion_status', '!=', 'completed')
                ->orWhereHas('objectiveResults', function ($qq) {
                    $qq->havingRaw('AVG(score) < 60');
                });
        });
    }

    public function getMentorshipPerformance(): array
    {
        $participations = $this->trainingParticipations()
            ->whereHas('training', function ($query) {
                $query->where('type', 'facility_mentorship');
            })
            ->with(['training', 'assessmentResults'])
            ->get();

        $totalTrainings = $participations->count();
        $completedTrainings = 0;
        $passedTrainings = 0;
        $totalScore = 0;
        $assessedTrainings = 0;

        foreach ($participations as $participation) {
            if ($participation->completion_status === 'completed') {
                $completedTrainings++;
            }

            $calculation = $participation->training->calculateOverallScore($participation);

            if ($calculation['all_assessed']) {
                $assessedTrainings++;
                $totalScore += $calculation['score'];

                if ($calculation['status'] === 'PASSED') {
                    $passedTrainings++;
                }
            }
        }

        return [
            'total_trainings' => $totalTrainings,
            'completed_trainings' => $completedTrainings,
            'passed_trainings' => $passedTrainings,
            'assessed_trainings' => $assessedTrainings,
            'completion_rate' => $totalTrainings > 0 ? round(($completedTrainings / $totalTrainings) * 100, 1) : 0,
            'pass_rate' => $assessedTrainings > 0 ? round(($passedTrainings / $assessedTrainings) * 100, 1) : 0,
            'average_score' => $assessedTrainings > 0 ? round($totalScore / $assessedTrainings, 1) : 0,
        ];
    }

    public function trainingParticipants(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    // Status Logs for Training Participants
    public function trainingStatusLogs(): HasManyThrough
    {
        return $this->hasManyThrough(
            ParticipantStatusLog::class,
            TrainingParticipant::class,
            'user_id',
            'training_participant_id',
            'id',
            'id'
        )->orderBy('month_number')->orderBy('created_at', 'desc');
    }

    // Status Logs for Mentorship Participants
    public function mentorshipStatusLogs(): HasManyThrough
    {
        return $this->hasManyThrough(
            ParticipantStatusLog::class,
            TrainingParticipant::class,
            'user_id',
            'mentorship_participant_id',
            'id',
            'id'
        )->whereNotNull('mentorship_participant_id')
            ->orderBy('month_number')->orderBy('created_at', 'desc');
    }

    // All Assessment Results for this user
    public function allAssessmentResults(): HasManyThrough
    {
        return $this->hasManyThrough(
            MenteeAssessmentResult::class,
            TrainingParticipant::class,
            'user_id',
            'participant_id',
            'id',
            'id'
        )->with('assessmentCategory');
    }

    // Mentorship-specific Assessment Results
    public function getMentorshipAssessmentResults(): HasManyThrough
    {
        return $this->hasManyThrough(
            MenteeAssessmentResult::class,
            TrainingParticipant::class,
            'user_id',
            'participant_id',
            'id',
            'id'
        )->whereHas('participant.training', function ($query) {
            $query->where('type', 'facility_mentorship');
        })->with('assessmentCategory');
    }

    // All Status Logs (Training + Mentorship) - fixed accessor
    public function getAllStatusLogsAttribute()
    {
        $trainingLogs = $this->trainingStatusLogs()->get();
        $mentorshipLogs = $this->mentorshipStatusLogs()->get();

        return $trainingLogs->concat($mentorshipLogs)
            ->sortByDesc('created_at')
            ->values();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status !== 'blocked';
    }
}

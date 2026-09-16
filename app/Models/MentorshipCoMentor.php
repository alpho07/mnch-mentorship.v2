<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorshipCoMentor extends Model {

    use HasFactory;

    protected $fillable = [
        'training_id',
        'user_id',
        'invited_by',
        'invited_at',
        'accepted_at',
        'status',
        'permissions',
        'invitation_token',
    ];
    protected $casts = [
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'permissions' => 'array',
    ];

    // Relationships
    public function training(): BelongsTo {
        return $this->belongsTo(Training::class, 'training_id');
    }

    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inviter(): BelongsTo {
        return $this->belongsTo(User::class, 'invited_by');
    }

    // Scopes
    public function scopePending($query) {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query) {
        return $query->where('status', 'accepted');
    }

    public function scopeActive($query) {
        return $query->where('status', 'accepted');
    }

    public function scopeRevoked($query) {
        return $query->where('status', 'revoked');
    }

    // Computed Attributes
    public function getIsAcceptedAttribute(): bool {
        return $this->status === 'accepted';
    }

    public function getIsPendingAttribute(): bool {
        return $this->status === 'pending';
    }

    public function getInvitationLinkAttribute(): ?string {
        if (!$this->invitation_token) {
            return null;
        }

        return url("/co-mentor/accept/{$this->invitation_token}");
    }

    public function hasAccess(): bool {
        return $this->status === 'accepted';
    }

    // Methods
    public function accept(): void {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    public function decline(): void {
        $this->update(['status' => 'declined']);
    }

    public function revoke(): void {
        $this->update(['status' => 'revoked']);
    }

    public function remove(): void {
        $this->update(['status' => 'removed']);
    }

    public function canFacilitate(): bool {
        return $this->is_accepted &&
                ($this->permissions['can_facilitate'] ?? true);
    }
}

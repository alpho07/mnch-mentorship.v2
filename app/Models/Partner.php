<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Partner extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'contact_person',
        'email',
        'phone',
        'address',
        'website',
        'registration_number',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    // Relationships
    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class, 'lead_partner_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'partner_id');
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class, 'partner_id');
    }

    // Query Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('contact_person', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('registration_number', 'like', "%{$search}%");
        });
    }

    public function scopeWithTrainings($query)
    {
        return $query->has('trainings');
    }

    // Accessors
    public function getTrainingCountAttribute(): int
    {
        return $this->trainings()->count();
    }

    public function getActiveTrainingCountAttribute(): int
    {
        return $this->trainings()->whereIn('status', ['ongoing', 'registration_open'])->count();
    }

    public function getCompletedTrainingCountAttribute(): int
    {
        return $this->trainings()->where('status', 'completed')->count();
    }

    public function getTotalParticipantsAttribute(): int
    {
        return $this->trainings()->withCount('participants')->get()->sum('participants_count');
    }

    public function getFormattedPhoneAttribute(): ?string
    {
        if (!$this->phone) return null;
        
        $phone = $this->phone;
        
        // Format Kenyan phone numbers
        if (str_starts_with($phone, '+254')) {
            return $phone;
        } elseif (str_starts_with($phone, '254')) {
            return '+' . $phone;
        } elseif (str_starts_with($phone, '0')) {
            return '+254' . substr($phone, 1);
        }
        
        return $phone;
    }

    public function getWebsiteUrlAttribute(): ?string
    {
        if (!$this->website) return null;
        
        if (str_starts_with($this->website, 'http')) {
            return $this->website;
        }
        
        return 'https://' . $this->website;
    }

    // Helper Methods
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function hasActiveTrainings(): bool
    {
        return $this->trainings()->whereIn('status', ['ongoing', 'registration_open'])->exists();
    }

    public function getTrainingStats(): array
    {
        $trainings = $this->trainings();
        
        return [
            'total' => $trainings->count(),
            'active' => $trainings->whereIn('status', ['ongoing', 'registration_open'])->count(),
            'completed' => $trainings->where('status', 'completed')->count(),
            'draft' => $trainings->where('status', 'draft')->count(),
            'cancelled' => $trainings->where('status', 'cancelled')->count(),
            'participants' => $trainings->withCount('participants')->get()->sum('participants_count'),
        ];
    }

    public function getRecentTrainings(int $limit = 5): Collection
    {
        return $this->trainings()
            ->with(['programs', 'participants'])
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }

    public function canBeDeleted(): bool
    {
        return !$this->hasActiveTrainings();
    }

    // Static Methods
    public static function getTypeOptions(): array
    {
        return [
            'ngo' => 'NGO',
            'private' => 'Private Organization',
            'international' => 'International Organization',
            'faith_based' => 'Faith-Based Organization',
            'academic' => 'Academic Institution',
            'development' => 'Development Partner',
            'other' => 'Other',
        ];
    }

    public static function getActivePartners(): Collection
    {
        return static::active()->orderBy('name')->get();
    }

    public static function getPartnersByType(string $type): Collection
    {
        return static::byType($type)->active()->orderBy('name')->get();
    }

    public static function getPartnerStats(): array
    {
        return [
            'total' => static::count(),
            'active' => static::active()->count(),
            'inactive' => static::inactive()->count(),
            'with_trainings' => static::withTrainings()->count(),
            'by_type' => static::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
        ];
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($partner) {
            if ($partner->hasActiveTrainings()) {
                throw new \Exception('Cannot delete partner with active trainings. Please complete or cancel all active trainings first.');
            }
        });
    }
}
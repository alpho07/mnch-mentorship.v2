<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingHotel extends Model {

    use HasFactory;

    protected $fillable = [
        'training_id',
        'hotel_name',
        'hotel_address',
        'hotel_contact',
    ];

    // Relationships
    public function training(): BelongsTo {
        return $this->belongsTo(Training::class);
    }

    // Computed Attributes
    public function getFormattedContactAttribute(): ?string {
        if (!$this->hotel_contact) {
            return null;
        }

        $contact = $this->hotel_contact;

        // Format Kenyan phone numbers
        if (str_starts_with($contact, '+254')) {
            return $contact;
        } elseif (str_starts_with($contact, '254')) {
            return '+' . $contact;
        } elseif (str_starts_with($contact, '0')) {
            return '+254' . substr($contact, 1);
        }

        return $contact;
    }

    public function getFullAddressAttribute(): string {
        $parts = array_filter([
            $this->hotel_name,
            $this->hotel_address,
        ]);

        return implode(', ', $parts);
    }

    // Query Scopes
    public function scopeByTraining($query, int $trainingId) {
        return $query->where('training_id', $trainingId);
    }

    public function scopeSearch($query, string $search) {
        return $query->where(function ($q) use ($search) {
                    $q->where('hotel_name', 'like', "%{$search}%")
                            ->orWhere('hotel_address', 'like', "%{$search}%")
                            ->orWhere('hotel_contact', 'like', "%{$search}%");
                });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'resource_id',
        'user_id',
        'type', // 'like', 'dislike', 'bookmark', 'share'
        'ip_address',
    ];

    // Relationships
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Query Scopes
    public function scopeType($query, string $type)
    {

        return $query->where('type', $type);
    }

    
}

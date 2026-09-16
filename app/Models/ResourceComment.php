<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResourceComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'resource_id',
        'user_id',
        'parent_id',
        'content',
        'author_name',
        'author_email',
        'is_approved',
        'ip_address',
    ];

    protected $casts = ['is_approved' => 'boolean'];

    // Relationships
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ResourceComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ResourceComment::class, 'parent_id');
    }

    // Query Scopes
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeParent($query)
    {
        return $query->whereNull('parent_id');
    }


}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionMaterial extends Model 
{
    use HasFactory;

    protected $fillable = [
        'module_session_id',
        'material_name',
        'quantity',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'quantity' => 'integer',
    ];

    // Relationships
    public function moduleSession(): BelongsTo
    {
        return $this->belongsTo(ModuleSession::class);
    }

    // Scopes
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOptional($query)
    {
        return $query->where('is_required', false);
    }
}
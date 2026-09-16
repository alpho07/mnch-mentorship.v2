<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramModule extends Model
{
    use HasFactory,
        SoftDeletes;

    protected $fillable = [
        'program_id',
        'parent_id',
        'name',
        'description',
        'order_sequence',
        'total_time_minutes',
        'start_date',
        'end_date',
        'duration_weeks',
        'objectives',
        'content',
        'is_active',
    ];

    protected $casts = [
        'order_sequence' => 'integer',
        'total_time_minutes' => 'integer',
        'duration_weeks' => 'integer',
        'objectives' => 'array',
        'content' => 'array',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // Relationships
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProgramModule::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProgramModule::class, 'parent_id')->orderBy('order_sequence');
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'program_module_activities')
            ->withTimestamps();
    }

    public function contents(): HasMany
    {
        return $this->hasMany(ProgramModuleContent::class, 'program_module_id')
            ->orderBy('order_sequence');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(ProgramModuleQuiz::class, 'program_module_id')
            ->orderBy('order_sequence');
    }

    public function classModules(): HasMany
    {
        return $this->hasMany(ClassModule::class, 'program_module_id');
    }

    public function moduleSessions(): HasMany
    {
        return $this->hasMany(\App\Models\ModuleSession::class, 'program_module_id');
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'resource_program_modules');
    }

    // Query Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByProgram($query, int $programId)
    {
        return $query->where('program_id', $programId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_sequence');
    }

    // Computed Attributes
    public function getUsageCountAttribute(): int
    {
        return $this->classModules()->count();
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

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function canBeDeleted(): bool
    {
        // Can't delete if being used in any class modules
        return $this->classModules()->doesntExist();
    }

    public function isTrack(): bool
    {
        return $this->parent_id !== null;
    }
}

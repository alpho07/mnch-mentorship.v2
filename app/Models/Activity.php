<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function programModules(): BelongsToMany
    {
        return $this->belongsToMany(ProgramModule::class, 'program_module_activities');
    }

    public function classModuleEnrollments(): HasMany
    {
        return $this->hasMany(ClassModuleActivityParticipant::class, 'activity_id');
    }
}

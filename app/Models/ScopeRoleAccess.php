<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScopeRoleAccess extends Model
{
    protected $table = 'scope_role_access';

    protected $fillable = ['scope_id', 'role_name'];

    public function scope(): BelongsTo
    {
        return $this->belongsTo(Scope::class);
    }
}

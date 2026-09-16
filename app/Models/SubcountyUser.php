<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubcountyUser extends Model
{
    use HasFactory;

    protected $table = 'subcounty_user';

    protected $fillable = [
        'user_id',
        'subcounty_id',
    ];

    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subcounty(): BelongsTo
    {
        return $this->belongsTo(Subcounty::class);
    }
}

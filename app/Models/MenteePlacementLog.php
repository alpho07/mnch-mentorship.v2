<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenteePlacementLog extends Model {

    use HasFactory;

    protected $fillable = [
        'user_id',
        'change_type',
        'old_department_id',
        'new_department_id',
        'old_cadre_id',
        'new_cadre_id',
        'effective_date',
        'reason',
        'notes',
    ];
    protected $casts = [
        'effective_date' => 'date',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function oldDepartment() {
        return $this->belongsTo(Department::class, 'old_department_id');
    }

    public function newDepartment() {
        return $this->belongsTo(Department::class, 'new_department_id');
    }

    public function oldCadre() {
        return $this->belongsTo(Cadre::class, 'old_cadre_id');
    }

    public function newCadre() {
        return $this->belongsTo(Cadre::class, 'new_cadre_id');
    }
}

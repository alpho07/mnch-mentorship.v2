<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProgramModuleActivity extends Pivot
{
    protected $table = 'program_module_activities';

    public $timestamps = true;
}

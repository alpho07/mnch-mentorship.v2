<?php

namespace App\Models\Concerns;

use App\Support\ProgramArea;

/** Adds the `program_area` tag: fillable, auto-set from the creating area admin. */
trait HasProgramArea
{
    public function initializeHasProgramArea(): void
    {
        $this->fillable[] = 'program_area';
    }

    protected static function bootHasProgramArea(): void
    {
        static::saving(function ($model) {
            // Area admins can only write into their own area; others choose freely.
            if ($area = ProgramArea::forUser(auth()->user())) {
                $model->program_area = $area;
            }
        });
    }
}

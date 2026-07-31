<?php

namespace App\Filament\Forms\Components;

use App\Models\Program;
use App\Models\ProgramModule;
use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;

class ProgramPicker extends Field
{
    protected string $view = 'filament.forms.components.program-picker';

    public function getPrograms(): Collection
    {
        $programs = Program::availableTo(auth()->user())->orderBy('name')->get();

        // Move Maternal Health (EmONC) to the 3rd position so the card order is:
        // Infant/Child, Newborn, EmONC, then any remaining programmes.
        $emoncIndex = $programs->search(
            fn (Program $p) => str_contains(strtolower($p->name), 'maternal')
                && str_contains(strtolower($p->name), 'emonc')
        );

        if ($emoncIndex !== false) {
            $emonc = $programs->pull($emoncIndex);
            $insertAt = min(2, $programs->count());
            $programs->splice($insertAt, 0, [$emonc]);
        }

        // Load active top-level ProgramModules in one query, then group by program.
        // Excludes track rows (parent_id set, e.g. PPH's 11 tracks) — those aren't
        // separate modules, just sub-procedures under their parent module.
        $modulesByProgram = ProgramModule::whereIn('program_id', $programs->pluck('id'))
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('order_sequence')
            ->get(['id', 'name', 'program_id', 'order_sequence'])
            ->groupBy('program_id');

        return $programs->each(
            fn ($p) => $p->setRelation('programModules', $modulesByProgram->get($p->id, collect()))
        );
    }
}

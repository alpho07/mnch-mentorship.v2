<?php

namespace App\Filament\Forms\Components;

use App\Models\MentorshipClass;
use App\Models\ProgramModule;
use App\Models\Training;
use Filament\Forms\Components\Field;
use Illuminate\Support\Collection;

class EmoncModulePicker extends Field
{
    protected string $view = 'filament.forms.components.emonc-module-picker';

    protected ?Training $training = null;

    protected ?MentorshipClass $class = null;

    protected bool $includeAssigned = false;

    public function training(Training $training): static
    {
        $this->training = $training;

        return $this;
    }

    public function class(MentorshipClass $class): static
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Include already-assigned tracks/modules in the list instead of
     * excluding them — used by the guided wizard, where module_ids is
     * pre-filled with the already-assigned ids so they render checked and
     * can be unchecked to remove them. The legacy one-shot "Add Modules"
     * modal leaves this off (its default), since it has no removal flow.
     */
    public function includeAssigned(bool $include = true): static
    {
        $this->includeAssigned = $include;

        return $this;
    }

    public function getTraining(): ?Training
    {
        return $this->training;
    }

    public function getClass(): ?MentorshipClass
    {
        return $this->class;
    }

    /**
     * Return parent modules with their children attached. A parent is
     * included if it is a leaf or has at least one visible track.
     * Already-assigned tracks are excluded unless includeAssigned() is set.
     */
    public function getModules(): Collection
    {
        $assignedIds = $this->includeAssigned
            ? []
            : $this->class->classModules()->pluck('program_module_id')->toArray();

        $parents = ProgramModule::where('program_id', $this->training->program_id)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->where('is_active', true)->orderBy('order_sequence')])
            ->orderBy('order_sequence')
            ->get();

        return $parents->map(function (ProgramModule $parent) use ($assignedIds) {
            $availableChildren = $parent->children->reject(
                fn (ProgramModule $track) => in_array($track->id, $assignedIds)
            )->values();

            // For leaf modules, keep them if not assigned.
            if ($parent->children->isEmpty()) {
                return in_array($parent->id, $assignedIds) ? null : $parent;
            }

            // For parents with tracks, only show if at least one track is available.
            if ($availableChildren->isEmpty()) {
                return null;
            }

            $parent->setRelation('availableChildren', $availableChildren);

            return $parent;
        })->filter();
    }
}

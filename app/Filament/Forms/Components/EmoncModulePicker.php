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

    public function getTraining(): ?Training
    {
        return $this->training;
    }

    public function getClass(): ?MentorshipClass
    {
        return $this->class;
    }

    /**
     * Return parent modules with their available children attached.
     * A parent is included if it is a leaf (no tracks) or has at least one
     * available track. Already-assigned tracks are excluded.
     */
    public function getModules(): Collection
    {
        $assignedIds = $this->class->classModules()->pluck('program_module_id')->toArray();

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

    /**
     * Parent modules whose tracks (or, for leaf modules, itself) are
     * already assigned to the class — for a read-only "Already added"
     * display, so real assignments stay visible instead of just
     * disappearing from getModules() once no longer pickable.
     */
    public function getAssignedModules(): Collection
    {
        $assignedIds = $this->class->classModules()->pluck('program_module_id')->toArray();

        if (empty($assignedIds)) {
            return collect();
        }

        $parents = ProgramModule::where('program_id', $this->training->program_id)
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->orderBy('order_sequence')])
            ->orderBy('order_sequence')
            ->get();

        return $parents->map(function (ProgramModule $parent) use ($assignedIds) {
            $assignedChildren = $parent->children->filter(
                fn (ProgramModule $track) => in_array($track->id, $assignedIds)
            )->values();

            if ($parent->children->isEmpty()) {
                return in_array($parent->id, $assignedIds) ? $parent : null;
            }

            if ($assignedChildren->isEmpty()) {
                return null;
            }

            $parent->setRelation('assignedChildren', $assignedChildren);

            return $parent;
        })->filter();
    }
}

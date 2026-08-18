<?php

namespace App\Services;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\ModuleRubric;
use App\Models\Training;
use App\Models\User;

class ProgramCertificationService
{
    /**
     * Per-program progress for a mentee: every program they have at least
     * one enrollment in, with module completion aggregated across ALL of
     * their classes in that program — regardless of facility or mentor —
     * mirroring ClassParticipant::hasCompletedAllProgramModules().
     */
    public function menteeProgress(User $mentee): array
    {
        $participants = ClassParticipant::where('user_id', $mentee->id)
            ->whereHas('mentorshipClass.training', fn ($q) => $q->where('type', 'facility_mentorship'))
            ->with(['mentorshipClass.training.program', 'mentorshipClass.training.facility'])
            ->get()
            ->filter(fn (ClassParticipant $p) => $p->mentorshipClass?->training?->program !== null);

        if ($participants->isEmpty()) {
            return [];
        }

        $byProgram = $participants->groupBy(fn (ClassParticipant $p) => $p->mentorshipClass->training->program_id);

        $result = [];

        foreach ($byProgram as $group) {
            $program = $group->first()->mentorshipClass->training->program;
            $participantIds = $group->pluck('id');

            $progressByModule = MenteeModuleProgress::whereIn('class_participant_id', $participantIds)
                ->with('classModule')
                ->get()
                ->groupBy(fn (MenteeModuleProgress $p) => $p->classModule?->program_module_id);

            $isSatisfied = function (int $pmId) use ($progressByModule): bool {
                $hasRubric = ModuleRubric::where('program_module_id', $pmId)->where('is_active', true)->exists();

                return $progressByModule->get($pmId, collect())->contains(
                    fn (MenteeModuleProgress $p) => in_array($p->status, ['completed', 'exempted'])
                        && (! $hasRubric || $p->isVideoPassed())
                );
            };

            // EmONC tracked separately: standalone modules vs tracks, since
            // "12 of 12 modules" and "8 of 11 tracks" is what's actually
            // meaningful — a combined "20 of 23 modules" muddies the two.
            $standaloneIds = $program->standaloneModules()->pluck('id');
            $trackIds = $program->trackModules()->pluck('id');

            $modulesDone = $standaloneIds->filter($isSatisfied)->count();
            $tracksDone = $trackIds->filter($isSatisfied)->count();
            $doneCount = $modulesDone + $tracksDone;
            $total = $standaloneIds->count() + $trackIds->count();

            $certifiedParticipant = $group->first(fn (ClassParticipant $p) => $p->head_drmh_approved_at !== null);

            $result[] = [
                'program_id' => $program->id,
                'program_name' => $program->name,
                'is_emonc' => $program->isEmonc(),
                'modules_done' => $modulesDone,
                'modules_total' => $standaloneIds->count(),
                'tracks_done' => $tracksDone,
                'tracks_total' => $trackIds->count(),
                'percent' => $total > 0 ? round(($doneCount / $total) * 100) : 0,
                'is_certified' => $certifiedParticipant !== null,
                'certified_at' => $certifiedParticipant?->head_drmh_approved_at,
                'cert_url' => $certifiedParticipant
                    ? route('reports.class.certificate', ['class' => $certifiedParticipant->mentorship_class_id, 'participant' => $certifiedParticipant->id])
                    : null,
                'classes' => $group->map(fn (ClassParticipant $p) => [
                    'name' => $p->mentorshipClass?->name ?? '—',
                    'facility' => $p->mentorshipClass?->training?->facility?->name ?? '—',
                    'status' => $p->status,
                ])->values()->toArray(),
            ];
        }

        return collect($result)->sortByDesc('percent')->values()->toArray();
    }

    /**
     * Per-program progress for a mentor: every program they lead at least
     * one training in, with completion measured by whether each of the
     * program's modules has been taught to completion
     * (ClassModule.status = 'completed') in ANY of the mentor's classes for
     * that program — regardless of which specific class did it.
     */
    public function mentorProgress(User $mentor): array
    {
        $trainings = Training::where('mentor_id', $mentor->id)
            ->where('type', 'facility_mentorship')
            ->with(['program', 'facility'])
            ->get()
            ->filter(fn (Training $t) => $t->program !== null);

        if ($trainings->isEmpty()) {
            return [];
        }

        $byProgram = $trainings->groupBy('program_id');

        $result = [];

        foreach ($byProgram as $group) {
            $program = $group->first()->program;
            $trainingIds = $group->pluck('id');
            $standaloneIds = $program->standaloneModules()->pluck('id');
            $trackIds = $program->trackModules()->pluck('id');

            $completedModuleIds = ClassModule::whereHas('mentorshipClass', fn ($q) => $q->whereIn('training_id', $trainingIds))
                ->where('status', 'completed')
                ->pluck('program_module_id')
                ->unique();

            $modulesDone = $standaloneIds->intersect($completedModuleIds)->count();
            $tracksDone = $trackIds->intersect($completedModuleIds)->count();
            $doneCount = $modulesDone + $tracksDone;
            $total = $standaloneIds->count() + $trackIds->count();
            $isCertified = $total > 0 && $doneCount === $total;

            $result[] = [
                'program_id' => $program->id,
                'program_name' => $program->name,
                'is_emonc' => $program->isEmonc(),
                'modules_done' => $modulesDone,
                'modules_total' => $standaloneIds->count(),
                'tracks_done' => $tracksDone,
                'tracks_total' => $trackIds->count(),
                'percent' => $total > 0 ? round(($doneCount / $total) * 100) : 0,
                'is_certified' => $isCertified,
                'cert_url' => $isCertified ? route('reports.mentor.program-certificate', ['program' => $program->id]) : null,
                'preview_url' => $isCertified ? route('reports.mentor.program-certificate.preview', ['program' => $program->id]) : null,
                'classes' => $group->map(fn (Training $t) => [
                    'title' => $t->title,
                    'facility' => $t->facility?->name ?? '—',
                    'status' => $t->status,
                ])->values()->toArray(),
            ];
        }

        return collect($result)->sortByDesc('percent')->values()->toArray();
    }
}

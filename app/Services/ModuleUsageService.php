<?php

namespace App\Services;

use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\MenteeModuleProgress;
use App\Models\MentorshipClass;
use App\Models\MentorshipModuleUsage;
use App\Models\ProgramModule;
use App\Models\Training;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ModuleUsageService
 *
 * Enforces the domain invariant: a module can only be assigned once
 * per class. The same module may be assigned to different classes.
 *
 * Also owns the auto-roster cascade: whenever a module is added to a class,
 * every currently enrolled mentee automatically gets a MenteeModuleProgress
 * row for it — zero manual work for the mentor.
 */
class ModuleUsageService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Read — available modules
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Modules from the program that are not already in the current class.
     * The same module may still be used in another class.
     *
     * Track handling:
     *   - Only parent modules (parent_id IS NULL) are shown in the picker.
     *   - If any child track of a parent is already assigned to the class,
     *     the parent is hidden so it cannot be selected again.
     *   - When a parent module is assigned, its children are expanded into
     *     individual ClassModule records automatically.
     */
    public function getAvailableModules(Training $training, MentorshipClass $class): Collection
    {
        $assignedModuleIds = $class->classModules()->pluck('program_module_id')->toArray();

        // If a track (child) is already assigned, its parent should also be
        // considered unavailable because tracks are managed through the parent.
        $assignedParentIds = ProgramModule::whereIn('id', $assignedModuleIds)
            ->whereNotNull('parent_id')
            ->pluck('parent_id')
            ->toArray();

        $excludeIds = array_unique(array_merge($assignedModuleIds, $assignedParentIds));

        return ProgramModule::where('program_id', $training->program_id)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->whereNotIn('id', $excludeIds)
            ->orderBy('order_sequence')
            ->get();
    }

    /**
     * Usage summary for the subheading / info strips.
     */
    public function getUsageSummary(Training $training): array
    {
        $totalModules = ProgramModule::where('program_id', $training->program_id)
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->count();

        $usedCount = ClassModule::whereHas('mentorshipClass',
            fn ($query) => $query->where('training_id', $training->id)
        )
            ->distinct('program_module_id')
            ->count('program_module_id');

        return [
            'total_modules' => $totalModules,
            'used_modules' => $usedCount,
            'remaining_modules' => max(0, $totalModules - $usedCount),
            'completion_percentage' => $totalModules > 0 ? round(($usedCount / $totalModules) * 100) : 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Write — assign modules
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Assign program modules to a class.
     *
     * For each module:
     *  1. Skips modules already assigned to this class
     *  2. Creates ClassModule record
     *  3. Records usage for reporting when possible
     *  4. *** AUTO-ROSTER CASCADE ***
     *     If the class is already active, creates MenteeModuleProgress rows for
     *     every currently enrolled mentee — so the mentor never has to add mentees
     *     to modules manually.
     *
     * Returns the count of modules successfully created.
     */
    public function assignModulesToClass(
        Training $training,
        MentorshipClass $class,
        array $programModuleIds,
        ?int $recordedBy = null,
        ?callable $afterCreate = null
    ): int {
        $added = 0;
        $recordedBy = $recordedBy ?? auth()->id();

        DB::transaction(function () use ($training, $class, $programModuleIds, $afterCreate, &$added) {
            $maxSequence = $class->classModules()->max('order_sequence') ?? 0;

            // Pre-fetch enrolled mentees once — used for cascade below
            $enrolledParticipants = $class->status === 'active' ? ClassParticipant::where('mentorship_class_id', $class->id)
                ->whereIn('status', ['enrolled', 'active'])
                ->get() : collect();

            // Pre-fetch previously completed module IDs per participant (for exemption)
            $completedByUser = [];
            foreach ($enrolledParticipants as $participant) {
                $completedByUser[$participant->id] = $this->getCompletedModuleIds($participant->user_id);
            }

            $programModules = ProgramModule::with('children')
                ->whereIn('id', $programModuleIds)
                ->get();

            foreach ($programModules as $programModule) {
                // Expand modules with tracks into one ClassModule per track.
                // Modules without tracks continue to create a single ClassModule.
                $modulesToCreate = $programModule->children->isNotEmpty()
                                ? $programModule->children
                                : collect([$programModule]);

                foreach ($modulesToCreate as $moduleToCreate) {
                    // Skip if already assigned to this class.
                    $alreadyInClass = $class->classModules()
                        ->where('program_module_id', $moduleToCreate->id)
                        ->exists();

                    if ($alreadyInClass) {
                        Log::warning("ModuleUsageService: module {$moduleToCreate->id} already assigned to class {$class->id}, skipping.");

                        continue;
                    }

                    // 2. Create ClassModule
                    $maxSequence++;
                    $classModule = ClassModule::create([
                        'mentorship_class_id' => $class->id,
                        'program_module_id' => $moduleToCreate->id,
                        'status' => 'not_started',
                        'order_sequence' => $maxSequence,
                        'min_attendance_percentage' => 75,
                        'requires_assessment' => false,
                        'attendance_link_active' => false,
                    ]);

                    // 3. Keep legacy usage tracking populated without enforcing availability.
                    MentorshipModuleUsage::firstOrCreate([
                        'mentorship_id' => $training->id,
                        'module_id' => $moduleToCreate->id,
                    ], [
                        'first_class_id' => $class->id,
                    ]);

                    // 3. AUTO-ROSTER CASCADE
                    // If the class is already active, ensure every enrolled mentee
                    // gets a progress row for this new module immediately.
                    if ($enrolledParticipants->isNotEmpty()) {
                        $this->cascadeRosterToModule($classModule, $enrolledParticipants, $completedByUser);
                    }

                    if ($afterCreate) {
                        $afterCreate($classModule);
                    }

                    $added++;
                }
            }
        });

        return $added;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Write — remove modules
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Remove a module from a class.
     *
     * Removes the module from this class. Blocked if the module has started
     * or has sessions.
     */
    public function removeModuleFromClass(
        Training $training,
        MentorshipClass $class,
        ClassModule $classModule
    ): bool {
        if (! $classModule->canBeRemoved()) {
            return false;
        }

        DB::transaction(function () use ($classModule) {
            // Delete progress rows for this module
            MenteeModuleProgress::where('class_module_id', $classModule->id)->delete();

            // Delete the class module (cascades to sessions via FK)
            $classModule->delete();
        });

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Auto-roster helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create MenteeModuleProgress rows for all given participants on a module.
     *
     * Called:
     *   A) When a module is added to an already-active class (new module)
     *   B) From MenteeEnrollmentController when a mentee joins (all existing modules)
     *   C) From ClassModule::start() as a sync guard (ensureMenteeProgressRecords)
     *
     * Uses firstOrCreate so it is safe to call multiple times (idempotent).
     */
    public function cascadeRosterToModule(
        ClassModule $classModule,
        Collection $participants,
        array $completedByUser = []
    ): int {
        $created = 0;

        // Determine what status a progress row should start at for this module
        $moduleStatus = match ($classModule->status) {
            'in_progress' => 'in_progress',
            'completed' => 'completed',
            default => 'not_started',
        };

        foreach ($participants as $participant) {
            $completedModuleIds = $completedByUser[$participant->id] ?? $this->getCompletedModuleIds($participant->user_id);

            $isExempted = in_array($classModule->program_module_id, $completedModuleIds);

            [$record, $wasCreated] = [
                MenteeModuleProgress::firstOrCreate(
                    [
                        'class_participant_id' => $participant->id,
                        'class_module_id' => $classModule->id,
                    ],
                    [
                        'status' => $isExempted ? 'exempted' : $moduleStatus,
                        'started_at' => ($isExempted || $moduleStatus === 'in_progress') ? now() : null,
                        'completed_in_previous_class' => $isExempted,
                        'exempted_at' => $isExempted ? now() : null,
                    ]
                ),
                null,
            ];

            // firstOrCreate doesn't return wasCreated directly in all versions
            if (! $record->wasRecentlyCreated) {
                continue;
            }

            $created++;
        }

        return $created;
    }

    /**
     * Cascade ALL existing modules in a class to a single new participant.
     *
     * Called from MenteeEnrollmentController when a mentee enrolls (including
     * late joiners after class has started).
     */
    public function cascadeAllModulesToParticipant(
        MentorshipClass $class,
        ClassParticipant $participant
    ): int {
        $class->loadMissing('classModules');

        $completedModuleIds = $this->getCompletedModuleIds($participant->user_id);
        $created = 0;

        foreach ($class->classModules as $classModule) {
            $isExempted = in_array($classModule->program_module_id, $completedModuleIds);

            $moduleStatus = match ($classModule->status) {
                'in_progress' => 'in_progress',
                'completed' => 'completed',
                default => 'not_started',
            };

            $record = MenteeModuleProgress::firstOrCreate(
                [
                    'class_participant_id' => $participant->id,
                    'class_module_id' => $classModule->id,
                ],
                [
                    'status' => $isExempted ? 'exempted' : $moduleStatus,
                    'started_at' => ($isExempted || $moduleStatus === 'in_progress') ? now() : null,
                    'completed_in_previous_class' => $isExempted,
                    'exempted_at' => $isExempted ? now() : null,
                ]
            );

            if ($record->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get program_module_ids a user has already completed in any previous class.
     * Used for exemption detection on enrollment.
     *
     * Uses two independent sources and merges them — whichever confirms presence
     * first wins. This makes exemption robust even if one source is incomplete.
     *
     * Source 1 — MenteeModuleProgress (status = completed|exempted)
     *   Set by ClassModule::complete() for mentees who confirmed attendance.
     *   Covers normal flow and prior exemptions.
     *
     * Source 2 — ClassAttendance (direct attendance record)
     *   Stronger proof: the mentee physically clicked the attendance link.
     *   Acts as fallback if progress records were not finalized for any reason
     *   (e.g. mentor ended class without completing all modules).
     *
     * Both sources match on program_module_id — stable identity across all
     * mentorships and classes on the platform.
     */
    public function getCompletedModuleIds(int $userId): array
    {
        // Source 1: progress records marked completed or exempted
        $fromProgress = DB::table('class_participants')
            ->join('mentee_module_progress', 'class_participants.id', '=', 'mentee_module_progress.class_participant_id')
            ->join('class_modules', 'mentee_module_progress.class_module_id', '=', 'class_modules.id')
            ->where('class_participants.user_id', $userId)
            ->whereIn('mentee_module_progress.status', ['completed', 'exempted'])
            ->pluck('class_modules.program_module_id');

        // Source 2: direct ClassAttendance records (mentee confirmed presence via link)
        // Requires class_module_id column — added by migration:
        //   add_class_module_id_to_class_attendances
        $fromAttendance = DB::table('class_attendances')
            ->join('class_modules', 'class_attendances.class_module_id', '=', 'class_modules.id')
            ->where('class_attendances.user_id', $userId)
            ->whereNotNull('class_attendances.class_module_id')
            ->pluck('class_modules.program_module_id');

        return $fromProgress
            ->merge($fromAttendance)
            ->unique()
            ->values()
            ->toArray();
    }
}

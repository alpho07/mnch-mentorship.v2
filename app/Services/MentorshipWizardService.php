<?php

namespace App\Services;

use App\Mail\MenteeEnrollmentInvitationMail;
use App\Models\ClassModule;
use App\Models\ClassParticipant;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Persistence logic shared by every mentorship-creation UI (the guided
 * Wizard and the chat assistant). Extracted verbatim from
 * GuidedMentorshipSetup so both callers get identical, single-source
 * business rules — see docs/GUIDED-MENTORSHIP-SETUP-REFERENCE.md for the
 * full behavioral spec each method below must uphold.
 */
class MentorshipWizardService
{
    public function createTraining(array $data, ?Training $existing = null): Training
    {
        $data['type'] = 'facility_mentorship';
        $data['mentor_id'] = auth()->id();

        $program = isset($data['program_id']) ? Program::find($data['program_id']) : null;
        $facility = isset($data['facility_id']) ? Facility::find($data['facility_id']) : null;
        $date = ! empty($data['start_date']) ? \Carbon\Carbon::parse($data['start_date'])->format('M Y') : now()->format('M Y');

        $data['title'] = trim(implode(' - ', array_filter([
            $program?->name ?? 'MNCH Mentorship',
            $facility?->name,
            $date,
        ])));

        if ($existing) {
            $existing->update($data);

            return $existing;
        }

        $data['identifier'] = 'MT-'.strtoupper(Str::random(6));

        return Training::create($data);
    }

    public function createFirstClass(array $data, Training $training, ?MentorshipClass $existing = null): MentorshipClass
    {
        $payload = [
            'training_id' => $training->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing;
        }

        return MentorshipClass::create($payload + [
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);
    }

    public function assignModules(array $data, Training $training, MentorshipClass $class): int
    {
        $desiredIds = collect($data['module_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $currentIds = $class->classModules()->pluck('program_module_id')->toArray();

        $toAdd = array_values(array_diff($desiredIds, $currentIds));
        $toRemove = array_values(array_diff($currentIds, $desiredIds));

        $blockedRemovals = [];
        foreach ($toRemove as $moduleId) {
            $classModule = $class->classModules()->where('program_module_id', $moduleId)->first();

            if ($classModule && ! $this->removeWizardModule($classModule)) {
                $blockedRemovals[] = $classModule->programModule?->name ?? "Module {$moduleId}";
            }
        }

        $created = 0;

        if (! empty($toAdd)) {
            $service = app(ModuleUsageService::class);
            $createdModules = [];

            $created = $service->assignModulesToClass(
                $training,
                $class,
                $toAdd,
                null,
                function (ClassModule $classModule) use (&$createdModules) {
                    $createdModules[$classModule->program_module_id] = $classModule;
                }
            );

            foreach ($data['module_dates'] ?? [] as $programModuleId => $dates) {
                $classModule = $createdModules[(int) $programModuleId] ?? null;

                if ($classModule && (! empty($dates['start']) || ! empty($dates['end']))) {
                    $classModule->update([
                        'start_date' => $dates['start'] ?? null,
                        'end_date' => $dates['end'] ?? null,
                    ]);
                }
            }

            if (($data['auto_create_sessions'] ?? true) && $created > 0) {
                $class->load('classModules');
                foreach ($class->classModules as $classModule) {
                    if (method_exists($classModule, 'autoCreateSessions')) {
                        $classModule->autoCreateSessions();
                    }
                }
            }
        }

        if (! empty($blockedRemovals)) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('Some modules could not be removed')
                ->body(implode(', ', $blockedRemovals).' already has mentee progress recorded.')
                ->send();
        }

        $this->clearWizardDraft($training, 'module_ids');
        $this->clearWizardDraft($training, 'moduleDates');

        return $created;
    }

    private function removeWizardModule(ClassModule $classModule): bool
    {
        if ($classModule->status !== 'not_started' || $classModule->menteeProgress()->count() > 0) {
            return false;
        }

        $classModule->delete();

        return true;
    }

    public function enrollMentees(array $data, MentorshipClass $class): int
    {
        $service = app(EnrollmentService::class);
        $count = 0;

        $desiredIds = collect($data['selected_users'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $currentIds = $class->participants()->pluck('user_id')->toArray();

        $toRemove = array_values(array_diff($currentIds, $desiredIds));
        foreach ($toRemove as $userId) {
            $participant = $class->participants()->where('user_id', $userId)->first();
            if ($participant) {
                $service->removeFromClass($participant);
            }
        }

        foreach (array_values(array_diff($desiredIds, $currentIds)) as $userId) {
            $user = User::find($userId);
            if ($user && ! $service->isEnrolled($user->id, $class->id)) {
                $service->enrollInClass($user, $class, 'manual');
                $count++;
            }
        }

        $newMentee = $data['new_mentee'] ?? null;
        if (! empty($newMentee['email'])) {
            $existing = User::where('email', $newMentee['email'])->first();

            if ($existing) {
                if (! $service->isEnrolled($existing->id, $class->id)) {
                    $service->enrollInClass($existing, $class, 'manual');
                    $count++;
                }
            } else {
                $displayName = trim(implode(' ', array_filter([
                    $newMentee['first_name'] ?? null,
                    $newMentee['last_name'] ?? null,
                ])));

                $user = User::create([
                    'first_name' => $newMentee['first_name'] ?? null,
                    'last_name' => $newMentee['last_name'] ?? null,
                    'name' => $displayName,
                    'email' => $newMentee['email'],
                    'phone' => $newMentee['phone'] ?? null,
                    'cadre_id' => $newMentee['cadre_id'] ?? null,
                    'department_id' => $newMentee['department_id'] ?? null,
                    'facility_id' => $newMentee['facility_id'] ?? null,
                    'password' => Hash::make('123456'),
                    'status' => 'active',
                    'role' => 'mentee',
                ]);

                if (method_exists($user, 'assignRole')) {
                    try {
                        $user->assignRole('mentee');
                    } catch (\Exception) {
                    }
                }

                $service->enrollInClass($user, $class, 'manual');
                $count++;
            }
        }

        $this->clearWizardDraft($class->training, 'selected_users');

        return $count;
    }

    public function sendInvitations(array $data, Training $training, MentorshipClass $class): array
    {
        if (! $class->enrollment_token) {
            $class->update([
                'enrollment_token' => Str::random(32),
                'enrollment_link_active' => true,
            ]);
        } else {
            $class->update(['enrollment_link_active' => true]);
        }
        $class->refresh();

        $query = ClassParticipant::where('mentorship_class_id', $class->id)
            ->whereHas('user', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
            ->with('user');

        if (($data['recipients'] ?? 'all') === 'not_sent') {
            $query->whereNull('invitation_sent_at');
        }

        $participants = $query->get();
        $sent = 0;
        $resent = 0;

        foreach ($participants as $record) {
            $isResend = (bool) $record->invitation_sent_at;

            Mail::to($record->user->email)->send(new MenteeEnrollmentInvitationMail(
                $record->user,
                $class,
                $record,
                $isResend
            ));

            $record->update(['invitation_sent_at' => now()]);
            $isResend ? $resent++ : $sent++;
        }

        $training->update([
            'guided_setup_completed_at' => now(),
            'guided_setup_draft' => null,
        ]);

        if ($class->canStart()) {
            $class->start();
        }

        $this->discardSupersededDrafts($training);

        return ['sent' => $sent, 'resent' => $resent];
    }

    public function discardSupersededDrafts(Training $training): void
    {
        Training::pendingGuidedSetup()
            ->where('mentor_id', $training->mentor_id)
            ->where('id', '!=', $training->id)
            ->get()
            ->each(fn (Training $stale) => $stale->forceDelete());
    }

    public function validateModuleDates(array $moduleIds, array $moduleDates): ?string
    {
        foreach (collect($moduleIds)->unique() as $id) {
            $dates = $moduleDates[$id] ?? null;
            $start = $dates['start'] ?? null;
            $end = $dates['end'] ?? null;

            if (empty($start) || empty($end)) {
                return 'Set a start and end date for every selected module/track before continuing — use "Set dates" on the row.';
            }

            if (\Illuminate\Support\Carbon::parse($end)->lt(\Illuminate\Support\Carbon::parse($start))) {
                return 'End date must be on or after the start date for every selected module/track.';
            }
        }

        return null;
    }

    public function isEmoncProgram(?int $programId): bool
    {
        if (! $programId) {
            return false;
        }

        $program = Program::find($programId);

        return $program
            && str_contains(strtolower($program->name), 'maternal')
            && str_contains(strtolower($program->name), 'emonc');
    }

    public function searchMenteeUsers(?string $search, int $page, int $perPage = 25): \Illuminate\Support\Collection
    {
        $query = User::query()
            ->where('status', 'active')
            ->with(['facility'])
            ->orderBy('first_name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('facility', function ($facilityQuery) use ($search) {
                        $facilityQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('mfl_code', 'like', "%{$search}%");
                    });
            });
        }

        return $query->skip(($page - 1) * $perPage)->take($perPage)->get();
    }

    public function menteeOptions(?string $search, int $page, array $selectedIds): array
    {
        $selected = empty($selectedIds)
            ? collect()
            : User::whereIn('id', $selectedIds)->with('facility')->orderBy('first_name')->get();

        $results = $this->searchMenteeUsers($search, $page)
            ->reject(fn ($u) => in_array($u->id, $selectedIds));

        return $selected->concat($results)
            ->mapWithKeys(fn ($u) => [$u->id => $this->formatMenteeLabel($u)])
            ->toArray();
    }

    public function formatMenteeLabel(User $u): string
    {
        return implode(' · ', array_filter([
            $u->name,
            $u->phone,
            $u->email,
            $u->facility ? "{$u->facility->name}".
                ($u->facility->mfl_code ? " (MFL {$u->facility->mfl_code})" : '') : null,
        ]));
    }

    public function saveWizardDraft(Training $training, string $key, mixed $state): void
    {
        $state = $state ?? [];
        $draft = $training->guided_setup_draft ?? [];
        $draft[$key] = array_is_list($state) ? array_values($state) : $state;

        $training->update(['guided_setup_draft' => $draft]);
    }

    public function clearWizardDraft(Training $training, string $key): void
    {
        $draft = $training->guided_setup_draft ?? [];

        if (! array_key_exists($key, $draft)) {
            return;
        }

        unset($draft[$key]);

        $training->update(['guided_setup_draft' => $draft ?: null]);
    }
}

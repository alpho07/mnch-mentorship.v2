<?php

namespace App\Services\Chat\Tools;

use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\User;
use App\Services\Chat\ChatTool;
use App\Services\Chat\SimpleChatTool;
use App\Services\MentorshipWizardService;

/**
 * Lets the model answer "what modules does program X have?" from real data,
 * anytime — not just once a class has actually reached the modules stage
 * (see MentorshipModulesToolProvider, which only fills module_ids for a
 * class already in progress). Without this, a plain question like "what
 * modules are available for Newborn Care?" had no tool to answer it, and
 * the model was observed inventing a plausible-sounding but entirely fake
 * module list. Reuses the exact same queries the legacy guided wizard uses
 * (GuidedMentorshipSetup's module step / EmoncModulePicker::getModules()) —
 * standard programs are a flat, active, parent-only list; EmONC programs
 * additionally carry per-parent "tracks" (only Postpartum Hemorrhage
 * actually has any seeded, but the shape supports any parent having them).
 */
class ProgramModulesQueryToolProvider
{
    public static function tools(): array
    {
        return [self::listModulesTool()];
    }

    private static function listModulesTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'list_program_modules',
            description: 'Look up the real training modules available for a named mentorship program. '.
                'Always use this instead of guessing or inventing module names.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'program_name' => [
                        'type' => 'string',
                        'description' => 'The program to list training modules for.',
                    ],
                ],
                'required' => ['program_name'],
            ],
            authorize: fn (User $user) => true,
            execute: function (array $args, User $user) {
                $name = trim((string) ($args['program_name'] ?? ''));

                if ($name === '') {
                    return ['error' => 'No program name given.'];
                }

                $resolution = self::resolveProgram($name, $user);

                if ($resolution['status'] !== 'resolved') {
                    return $resolution['status'] === 'ambiguous'
                        ? ['error' => "More than one program matches \"{$name}\" — please be more specific.", 'candidates' => $resolution['names']]
                        : ['error' => "No program found matching \"{$name}\"."];
                }

                $program = $resolution['program'];
                $isEmonc = app(MentorshipWizardService::class)->isEmoncProgram($program->id);

                $parents = ProgramModule::where('program_id', $program->id)
                    ->where('is_active', true)
                    ->whereNull('parent_id')
                    ->when($isEmonc, fn ($query) => $query->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('order_sequence')]))
                    ->orderBy('order_sequence')
                    ->get();

                $modules = $parents->map(fn (ProgramModule $parent) => [
                    'name' => $parent->name,
                    'tracks' => $isEmonc ? $parent->children->pluck('name')->all() : [],
                ])->all();

                return ['program' => $program->name, 'modules' => $modules];
            },
        );
    }

    /**
     * @return array{status: 'resolved', program: Program}|array{status: 'ambiguous', names: array<int, string>}|array{status: 'unresolved'}
     */
    private static function resolveProgram(string $name, User $user): array
    {
        $programs = Program::query()->get()->filter(fn (Program $p) => $p->isSelectableBy($user));

        foreach ($programs as $program) {
            if (strcasecmp($program->name, $name) === 0) {
                return ['status' => 'resolved', 'program' => $program];
            }
        }

        $partial = $programs->filter(
            fn (Program $p) => stripos($p->name, $name) !== false || stripos($name, $p->name) !== false
        );

        if ($partial->count() === 1) {
            return ['status' => 'resolved', 'program' => $partial->first()];
        }

        if ($partial->count() > 1) {
            return ['status' => 'ambiguous', 'names' => $partial->pluck('name')->values()->all()];
        }

        return ['status' => 'unresolved'];
    }
}

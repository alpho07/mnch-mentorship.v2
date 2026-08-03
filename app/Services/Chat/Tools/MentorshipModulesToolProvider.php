<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\Chat\ChatTool;
use App\Services\Chat\SimpleChatTool;

/**
 * Lets the model pick training modules by name once the modules stage is
 * reached — module_ids isn't a generic Slot (see
 * HasMentorshipChatSlots::answer()'s comment on this), so it needs its own
 * tool rather than riding on MentorshipSetupToolProvider. Mirrors that
 * provider's label-based resolution (see its resolveValue()) so the model
 * works from real module names instead of opaque database ids, and never
 * silently guesses or drops one it can't find. Only offered for standard
 * (non-EmONC) programs — EmONC modules need per-track date assignment via
 * chat-emonc-modules-turn.blade.php's date modal, which free text doesn't
 * drive (see MnchGptSetup::buildToolRegistry()).
 */
class MentorshipModulesToolProvider
{
    public static function tool($page): ChatTool
    {
        $options = $page->getModuleFieldOptions();

        return new SimpleChatTool(
            name: 'fill_mentorship_modules',
            description: 'Select the training modules for this mentorship class, or skip module selection for now.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'module_names' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string',
                            'enum' => array_values(array_map('strval', $options)),
                        ],
                        'description' => 'The modules to include, using the option text exactly as listed.',
                    ],
                    'skip' => [
                        'type' => 'boolean',
                        'description' => 'True if the user wants to skip module selection for now.',
                    ],
                ],
            ],
            authorize: fn (User $user) => true,
            execute: function (array $args, User $user) use ($page, $options) {
                if ($args['skip'] ?? false) {
                    $page->submitModules([]);

                    return ['submitted' => []];
                }

                $names = $args['module_names'] ?? [];

                if (empty($names)) {
                    return ['error' => 'No module names given.'];
                }

                $resolvedIds = [];
                $unresolved = [];

                foreach ($names as $name) {
                    $id = self::resolveModuleId($options, $name);

                    if ($id === null) {
                        $unresolved[] = $name;
                    } else {
                        $resolvedIds[] = $id;
                    }
                }

                // submitModules() is a one-shot "Continue" that finalizes
                // the whole module list for this class — submitting just
                // the names that resolved would silently truncate what the
                // user actually asked for, so nothing is assigned unless
                // every name resolved.
                if (! empty($unresolved)) {
                    return ['unresolved' => $unresolved];
                }

                $page->submitModules($resolvedIds);

                return ['submitted' => $resolvedIds];
            },
        );
    }

    private static function resolveModuleId(array $options, string $name): ?int
    {
        $needle = trim($name);

        foreach ($options as $id => $label) {
            if (strcasecmp((string) $label, $needle) === 0) {
                return $id;
            }
        }

        $partial = collect($options)->filter(
            fn ($label) => stripos((string) $label, $needle) !== false
        );

        return $partial->count() === 1 ? $partial->keys()->first() : null;
    }
}

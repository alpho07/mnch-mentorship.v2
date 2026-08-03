<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\Chat\ChatTool;
use App\Services\Chat\SimpleChatTool;
use App\Services\MentorshipWizardService;

/**
 * Lets the model search for and enroll mentees by name/email/phone once the
 * enroll_mentees stage is reached — selected_users isn't a generic Slot (see
 * HasMentorshipChatSlots::answer()'s comment on this), so it needs its own
 * tool rather than riding on MentorshipSetupToolProvider. Reuses the exact
 * same MentorshipWizardService::searchMenteeUsers()/checkAndSubmitMentees()
 * path the click-driven chat-mentees-turn.blade.php card UI already uses —
 * same search fields (name/email/phone/facility), same max_participants cap,
 * same missing-email pause. The mentee table is unbounded (thousands of
 * rows), unlike facility/county — there's no enum to expose, so each query
 * is resolved server-side against real matches, never guessed or invented.
 */
class MentorshipMenteesToolProvider
{
    public static function tool($page): ChatTool
    {
        return new SimpleChatTool(
            name: 'fill_mentorship_mentees',
            description: 'Search for and enroll mentees using whatever the user gave you — call this for ANY '.
                'mentee they mention, even a bare first name or a name copied from a list you already showed '.
                'them, and even if you are not sure that person exists yet. The system searches real records '.
                'and tells you what matched. Do NOT ask the user for role, title, department, or any other '.
                'registration detail — this app never collects those during enrollment, only a name/email/phone '.
                'to search with, or (for someone genuinely new) just an email plus first/last name.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'existing_mentee_queries' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Every name, email, or phone number the user gave for enrollment — pass '.
                            'each exactly as given. Use this first, always, before ever considering new_mentee.',
                    ],
                    'new_mentee' => [
                        'type' => 'object',
                        'description' => 'Only for someone confirmed NOT already in the system (an '.
                            'existing_mentee_queries search came back unresolved) whom the user explicitly wants '.
                            'added as brand new. Needs only email, first_name, last_name — nothing else. One '.
                            'new mentee per call.',
                        'properties' => [
                            'email' => ['type' => 'string'],
                            'first_name' => ['type' => 'string'],
                            'last_name' => ['type' => 'string'],
                        ],
                    ],
                    'skip' => [
                        'type' => 'boolean',
                        'description' => 'True if the user wants to skip enrolling mentees for now.',
                    ],
                ],
            ],
            authorize: fn (User $user) => true,
            execute: function (array $args, User $user) use ($page) {
                if ($page->activeStage() !== 'enroll_mentees') {
                    return ['error' => 'Mentee enrollment is not available yet.'];
                }

                if ($args['skip'] ?? false) {
                    $page->checkAndSubmitMentees([]);

                    return ['enrolled' => []];
                }

                $queries = $args['existing_mentee_queries'] ?? [];
                $newMentee = $args['new_mentee'] ?? null;

                if (empty($queries) && empty($newMentee['email'] ?? null)) {
                    return ['error' => 'No mentee names, contact details, or new mentee details were given.'];
                }

                $service = app(MentorshipWizardService::class);
                $resolvedIds = [];
                $unresolved = [];
                $candidates = [];

                foreach ($queries as $query) {
                    $matches = $service->searchMenteeUsers($query, 1, 8);

                    if ($matches->count() === 1) {
                        $resolvedIds[] = $matches->first()->id;
                    } elseif ($matches->count() > 1) {
                        $candidates[$query] = $matches->map(fn (User $u) => [
                            'id' => $u->id,
                            'label' => $service->formatMenteeLabel($u),
                        ])->all();
                    } else {
                        $unresolved[] = $query;
                    }
                }

                // checkAndSubmitMentees() is a one-shot "Continue" that
                // finalizes the whole mentee list for this class —
                // enrolling only the names that resolved would silently
                // drop whoever didn't match, so nothing is enrolled unless
                // every query resolved to exactly one person.
                if (! empty($unresolved) || ! empty($candidates)) {
                    return array_filter([
                        'unresolved' => $unresolved,
                        'candidates' => $candidates,
                    ]);
                }

                $resolvedIds = array_values(array_unique($resolvedIds));

                $max = $page->training->max_participants;
                if ($max && count($resolvedIds) > $max) {
                    return ['error' => "You can select at most {$max} mentees."];
                }

                $page->checkAndSubmitMentees($resolvedIds, $newMentee);

                if (! empty($page->menteesNeedingEmail)) {
                    return [
                        'awaiting_email' => true,
                        'mentees_needing_email' => collect($page->menteesNeedingEmail)->pluck('name')->all(),
                        'message' => 'These matched mentees have no email on file. A form has appeared on the '.
                            'page for the user to enter one for each — tell them to use it; there is no way to '.
                            'supply an email through chat text for this. Do not ask for any other detail.',
                    ];
                }

                return ['enrolled' => $resolvedIds];
            },
        );
    }
}

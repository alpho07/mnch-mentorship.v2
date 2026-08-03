<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\Chat\ChatTool;
use App\Services\Chat\Render;
use App\Services\Chat\SimpleChatTool;

/**
 * Batches every currently-eligible unfilled mentorship-setup Slot into a
 * single tool the LLM can call with as many of them as it could extract
 * from one message. Every proposed value is routed through the page's own
 * answer() — the exact same Slot::validate() a click-driven answer uses —
 * so the LLM never bypasses validation. See
 * docs/superpowers/specs/2026-08-03-mnchgpt-llm-assistant-design.md.
 */
class MentorshipSetupToolProvider
{
    /**
     * Sentinel distinguishing "no match" from every legitimate resolved
     * value, including falsy ones like 0 (is_pilot's "Live Mentorship").
     */
    private const UNRESOLVED = '__mnchgpt_unresolved__';

    public static function tool($page): ChatTool
    {
        return new SimpleChatTool(
            name: 'fill_mentorship_setup_slots',
            description: 'Fill in one or more mentorship setup fields extracted from the user\'s message.',
            schema: self::schemaFor($page),
            authorize: fn (User $user) => true,
            execute: function (array $args, User $user) use ($page) {
                $filled = [];
                $rejected = [];

                // module_ids/selected_users aren't generic Slot objects, so
                // nextUnfilledSlot() skips straight past the modules/
                // enroll_mentees stages to 'recipients' — answering it early
                // fires sendInvitations() immediately, completing the class
                // with nothing in it. This mirrors the same guard schemaFor()
                // applies, checked again here in case a value for a
                // not-currently-offered slot arrives anyway.
                if ($page->activeStage() !== 'slot') {
                    return ['filled' => [], 'rejected' => array_keys($args)];
                }

                foreach ($args as $slotId => $value) {
                    $slot = collect($page->slots())->firstWhere('id', $slotId);

                    if (! $slot) {
                        continue;
                    }

                    if (array_key_exists($slotId, $page->answers)) {
                        continue;
                    }

                    $resolved = self::resolveValue($slot, $value, $page->answers);

                    if ($resolved === self::UNRESOLVED) {
                        $rejected[] = $slotId;

                        continue;
                    }

                    $before = $page->answers;
                    $page->answer($slotId, $resolved);

                    if (array_key_exists($slotId, $page->answers) && $page->answers !== $before) {
                        $filled[] = $slotId;
                    } else {
                        $rejected[] = $slotId;
                    }
                }

                return ['filled' => $filled, 'rejected' => $rejected];
            },
        );
    }

    /**
     * Only slots in the *current* stage (the stage nextUnfilledSlot() is
     * in) are exposed — a later stage's slots (e.g. class_name in
     * first_class) aren't reachable yet even if visibleWhen() alone
     * wouldn't hide them, since MentorshipChatScript declares slots in
     * stage order and the click-driven flow never shows them early either.
     */
    private static function schemaFor($page): array
    {
        $next = $page->nextUnfilledSlot();

        // module_ids/selected_users aren't generic Slot objects (see
        // HasMentorshipChatSlots::answer()'s comment on this same point) —
        // nextUnfilledSlot() would otherwise skip straight past the
        // modules/enroll_mentees stages to 'recipients' (send_invitations),
        // offering it as fillable before the class has any modules or
        // mentees.
        if (! $next || $page->activeStage() !== 'slot') {
            return ['type' => 'object', 'properties' => []];
        }

        $properties = [];

        foreach ($page->slots() as $slot) {
            if ($slot->stage !== $next->stage) {
                continue;
            }

            if (array_key_exists($slot->id, $page->answers) || ! $slot->isVisible($page->answers)) {
                continue;
            }

            // A dependent slot's options are computed *from* the answers it
            // depends on (e.g. facility_id's options are scoped to the
            // chosen county). Before that dependency is answered there's no
            // sane subset to offer — showing it anyway means either an
            // empty enum (the model then wrongly claims a real facility
            // "isn't available") or every facility system-wide, unscoped.
            if (collect($slot->dependencies())->contains(fn ($dep) => ! array_key_exists($dep, $page->answers))) {
                continue;
            }

            $properties[$slot->id] = self::propertyFor($slot, $page->answers);
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * CARDS slots' real ids are opaque database surrogate keys (e.g. a
     * county id like 56427) with no relationship to what a user would ever
     * say — a model given those as the enum has no way to resolve "Tharaka
     * Nithi" to the right one and can only guess, which for a slot like
     * facility_id (10,000+ rows) is indistinguishable from never matching.
     * Exposing the option *labels* instead gives the model something it can
     * actually match against the user's words; resolveValue() below
     * translates the chosen label back to the real id server-side.
     */
    private static function propertyFor($slot, array $answers): array
    {
        if ($slot->renderKind() === Render::CARDS) {
            $options = $slot->getOptions($answers);

            return [
                'type' => 'string',
                'description' => $slot->getQuestion($answers).' Respond with the option text exactly as listed.',
                'enum' => array_values(array_map('strval', $options)),
            ];
        }

        return [
            'type' => 'string',
            'description' => $slot->getQuestion($answers),
        ];
    }

    /**
     * Resolves a CARDS slot's model-supplied value (expected to be one of
     * the label strings from propertyFor()'s enum) back to the option's
     * real id. Also accepts a raw id directly, for backward compatibility
     * with click-driven re-submissions and models that echo the id anyway.
     * Non-CARDS (free-text) slots pass through unchanged. Anything matching
     * neither a label nor a real id is rejected outright rather than
     * guessed at — a hallucinated value must never silently attach the
     * wrong county/facility/program to a mentorship.
     */
    private static function resolveValue($slot, mixed $value, array $answers): mixed
    {
        if ($slot->renderKind() !== Render::CARDS) {
            return $value;
        }

        $options = $slot->getOptions($answers);
        $needle = trim((string) $value);

        foreach ($options as $id => $label) {
            if ((string) $id === (string) $value || strcasecmp((string) $label, $needle) === 0) {
                return $id;
            }
        }

        // Labels like facility_id's "MFL012 — Chuka County Referral
        // Hospital" carry a code prefix a user would never actually say —
        // if the model relayed just the name, match it as long as it
        // identifies exactly one option; multiple matches are as
        // unresolvable as none, since guessing between them risks
        // attaching the wrong record.
        if ($needle !== '') {
            $partial = collect($options)->filter(
                fn ($label) => stripos((string) $label, $needle) !== false
            );

            if ($partial->count() === 1) {
                return $partial->keys()->first();
            }
        }

        return self::UNRESOLVED;
    }
}

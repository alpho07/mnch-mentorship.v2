<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\Chat\ChatTool;
use App\Services\Chat\FuzzyOptionMatcher;
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
                $candidates = [];

                // module_ids/selected_users aren't generic Slot objects, so
                // nextUnfilledSlot() skips straight past the modules/
                // enroll_mentees stages to 'recipients' — answering it early
                // fires sendInvitations() immediately, completing the class
                // with nothing in it. This mirrors the same guard schemaFor()
                // applies, checked again here in case a value for a
                // not-currently-offered slot arrives anyway.
                if ($page->activeStage() !== 'slot') {
                    return ['filled' => [], 'rejected' => array_keys($args), 'candidates' => []];
                }

                // A single dense message can legitimately name both the
                // rest of the current stage's slots AND the next stage's
                // (e.g. the last training_details field plus the whole
                // first_class stage) — the per-iteration stage check below
                // is designed to let that resolve within one call, once the
                // earlier stage completes partway through. But json_decode
                // preserves whatever key order the model wrote, not slot
                // declaration order — a later-stage key landing earlier in
                // the object would hit that check before its prerequisite
                // stage had actually completed *yet*, and get permanently
                // rejected even though the rest of the same call would have
                // unlocked it moments later. Sorting into script order
                // first makes the outcome independent of how the model
                // happened to order its JSON.
                $slotOrder = array_column($page->slots(), 'id');
                $args = collect($args)
                    ->sortBy(fn ($value, $slotId) => array_search($slotId, $slotOrder, true) ?? PHP_INT_MAX)
                    ->all();

                foreach ($args as $slotId => $value) {
                    $slot = collect($page->slots())->firstWhere('id', $slotId);

                    if (! $slot) {
                        continue;
                    }

                    if (array_key_exists($slotId, $page->answers)) {
                        continue;
                    }

                    // schemaFor() only ever offers slots from the *current*
                    // stage, but a tool schema is guidance, not an enforced
                    // contract — nothing stops the model from naming a
                    // later-stage slot anyway (e.g. class_name while
                    // facility_id is still unresolved). Accepting it would
                    // leave an earlier required stage permanently
                    // incomplete (training/class never get created) while
                    // the checklist shows later items as done, so anything
                    // outside the live next-unfilled-slot's stage is
                    // rejected outright — recomputed every iteration since
                    // answering one slot can complete a stage and advance
                    // this mid-loop.
                    $currentStage = $page->nextUnfilledSlot()?->stage;

                    if ($slot->stage !== $currentStage) {
                        $rejected[] = $slotId;

                        continue;
                    }

                    $resolution = self::resolveValue($slot, $value, $page->answers);

                    if ($resolution['status'] === 'ambiguous') {
                        $candidates[$slotId] = $resolution['candidates'];

                        continue;
                    }

                    if ($resolution['status'] === 'unresolved') {
                        $rejected[] = $slotId;

                        continue;
                    }

                    // announceNext: false — this loop can resolve several
                    // slots from one free-text message, and each of
                    // answer()'s own per-call "next question" messages would
                    // reflect only what was still unfilled *at that point*
                    // mid-loop, not the final state — producing several
                    // stale, mismatched questions per batch. The final reply
                    // built from this tool's overall result (in
                    // MnchGptSetup::sendMessage()) is the single source of
                    // "what's next" for a batched turn.
                    $before = $page->answers;
                    $page->answer($slotId, $resolution['value'], false);

                    if (array_key_exists($slotId, $page->answers) && $page->answers !== $before) {
                        $filled[] = $slotId;
                    } else {
                        $rejected[] = $slotId;
                    }
                }

                return ['filled' => $filled, 'rejected' => $rejected, 'candidates' => $candidates];
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
        //
        // properties is cast to (object) in both returns below — PHP has no
        // way to distinguish an empty associative array from an empty
        // indexed one, so json_encode(['properties' => []]) always produces
        // the JSON array `[]`, not `{}`. That's invalid per JSON Schema
        // (properties must be an object) and was confirmed live to make
        // DeepSeek reject the *entire* request with a 400 — since every
        // registered tool's schema ships in the same request, this single
        // malformed one (hit constantly, since it's exactly what happens
        // whenever nothing is left to fill via this tool, e.g. during the
        // modules/enroll_mentees stages) broke every other tool call
        // alongside it, surfacing as "Sorry, I couldn't process that."
        if (! $next || $page->activeStage() !== 'slot') {
            return ['type' => 'object', 'properties' => (object) []];
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

        // Only cast when genuinely empty (see the comment above on the
        // other return path) — a non-empty associative array with string
        // keys already serializes to a correct JSON object on its own,
        // and staying a plain PHP array here keeps schema()['properties']
        // usable with array_keys()/etc. everywhere else it's inspected.
        return [
            'type' => 'object',
            'properties' => empty($properties) ? (object) [] : $properties,
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
     * Resolves a CARDS slot's model-supplied value against its real
     * options, in three tiers — exact match, unique substring match, then
     * a fuzzy shortlist (FuzzyOptionMatcher) — returning one of:
     * - ['status' => 'resolved', 'value' => $id]
     * - ['status' => 'ambiguous', 'candidates' => [['id' => ..., 'label' => ...], ...]]
     * - ['status' => 'unresolved']
     * Non-CARDS (free-text) slots always resolve immediately. Fuzzy never
     * auto-picks a winner, even a clearly-best one — ambiguous always means
     * "show the shortlist", never "guess".
     */
    private static function resolveValue($slot, mixed $value, array $answers): array
    {
        if ($slot->renderKind() !== Render::CARDS) {
            return ['status' => 'resolved', 'value' => $value];
        }

        $options = $slot->getOptions($answers);
        $needle = trim((string) $value);

        foreach ($options as $id => $label) {
            if ((string) $id === (string) $value || strcasecmp((string) $label, $needle) === 0) {
                return ['status' => 'resolved', 'value' => $id];
            }
        }

        // Labels like facility_id's "MFL012 — Chuka County Referral
        // Hospital" carry a code prefix a user would never actually say —
        // if the model relayed just the name, match it as long as it
        // identifies exactly one option; multiple matches fall through to
        // the fuzzy tier below rather than being guessed between.
        if ($needle !== '') {
            $partial = collect($options)->filter(
                fn ($label) => stripos((string) $label, $needle) !== false
            );

            if ($partial->count() === 1) {
                return ['status' => 'resolved', 'value' => $partial->keys()->first()];
            }
        }

        if ($needle !== '') {
            $candidates = FuzzyOptionMatcher::search($options, $needle);

            if (! empty($candidates)) {
                return ['status' => 'ambiguous', 'candidates' => $candidates];
            }
        }

        return ['status' => 'unresolved'];
    }
}

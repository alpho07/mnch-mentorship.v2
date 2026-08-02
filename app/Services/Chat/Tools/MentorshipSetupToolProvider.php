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

                foreach ($args as $slotId => $value) {
                    if (! collect($page->slots())->contains('id', $slotId)) {
                        continue;
                    }

                    if (array_key_exists($slotId, $page->answers)) {
                        continue;
                    }

                    $before = $page->answers;
                    $page->answer($slotId, $value);

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

        if (! $next) {
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

            $properties[$slot->id] = self::propertyFor($slot, $page->answers);
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    private static function propertyFor($slot, array $answers): array
    {
        if ($slot->renderKind() === Render::CARDS) {
            $options = $slot->getOptions($answers);

            return [
                'type' => 'string',
                'description' => $slot->getQuestion($answers),
                'enum' => array_map('strval', array_keys($options)),
            ];
        }

        return [
            'type' => 'string',
            'description' => $slot->getQuestion($answers),
        ];
    }
}

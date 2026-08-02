<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\Chat\ChatTool;
use App\Services\Chat\SimpleChatTool;
use App\Services\MentorshipStatsService;

/**
 * Mentorship/mentee count tools, backed by MentorshipStatsService — the
 * exact same scoped source the dashboard widget uses, so a user can never
 * learn a number here they couldn't already see on the mentorships page.
 */
class MentorshipStatsToolProvider
{
    public static function tools(): array
    {
        return [self::countsTool()];
    }

    public static function countsTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_mentorship_counts',
            description: 'Get the number of live mentorships and mentees, overall or for one named program.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'program_name' => [
                        'type' => 'string',
                        'description' => 'Optional program name to narrow the counts to.',
                    ],
                ],
            ],
            authorize: fn (User $user) => true,
            execute: fn (array $args, User $user) => app(MentorshipStatsService::class)
                ->countsFor($user, $args['program_name'] ?? null),
        );
    }
}

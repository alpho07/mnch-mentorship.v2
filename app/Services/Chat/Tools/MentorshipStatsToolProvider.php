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
        return [self::countsTool(), self::trendsTool()];
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

    public static function trendsTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_mentorship_trends',
            description: 'Get mentorship and mentee counts per period over time, to answer growth/trend questions.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'period' => ['type' => 'string', 'enum' => ['monthly', 'quarterly']],
                    'periods_back' => ['type' => 'integer', 'description' => 'How many periods back to include, default 6.'],
                ],
                'required' => ['period'],
            ],
            authorize: fn (User $user) => true,
            execute: fn (array $args, User $user) => [
                'trends' => app(MentorshipStatsService::class)->trends(
                    $user,
                    $args['period'] ?? 'monthly',
                    $args['periods_back'] ?? 6,
                ),
            ],
        );
    }
}

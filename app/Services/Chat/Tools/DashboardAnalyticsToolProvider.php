<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\Chat\ChatTool;
use App\Services\Chat\SimpleChatTool;
use App\Services\DashboardAnalyticsQueryService;

class DashboardAnalyticsToolProvider
{
    public static function tools(): array
    {
        return [self::countyCoverageTool(), self::programSummaryTool(), self::trainingCompletionTool()];
    }

    public static function countyCoverageTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_county_coverage_summary',
            description: 'Get facility, mentorship, and mentee counts for a named county.',
            schema: [
                'type' => 'object',
                'properties' => ['county_name' => ['type' => 'string']],
                'required' => ['county_name'],
            ],
            authorize: fn (User $user) => true,
            execute: function (array $args, User $user) {
                $result = app(DashboardAnalyticsQueryService::class)->countyCoverageSummary($user, $args['county_name']);

                return $result ?? ['error' => 'That county was not found or is not accessible to you.'];
            },
        );
    }

    public static function programSummaryTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_program_summary',
            description: 'Get mentorship totals and a per-county breakdown for a named program.',
            schema: [
                'type' => 'object',
                'properties' => ['program_name' => ['type' => 'string']],
                'required' => ['program_name'],
            ],
            authorize: fn (User $user) => true,
            execute: function (array $args, User $user) {
                $result = app(DashboardAnalyticsQueryService::class)->programSummary($user, $args['program_name']);

                return $result ?? ['error' => 'That program was not found.'];
            },
        );
    }

    public static function trainingCompletionTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_training_completion_stats',
            description: 'Get training completion rate and participant counts, optionally for one named program.',
            schema: [
                'type' => 'object',
                'properties' => ['program_name' => ['type' => 'string']],
            ],
            authorize: fn (User $user) => true,
            execute: fn (array $args, User $user) => app(DashboardAnalyticsQueryService::class)
                ->trainingCompletionStats($user, $args['program_name'] ?? null),
        );
    }
}

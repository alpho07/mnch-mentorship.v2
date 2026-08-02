<?php

namespace App\Services\Chat\Tools;

use App\Models\User;
use App\Services\AssessmentSummaryQueryService;
use App\Services\Chat\ChatTool;
use App\Services\Chat\SimpleChatTool;

class AssessmentSummaryToolProvider
{
    public static function tools(): array
    {
        return [self::statusCountsTool(), self::readinessScoresTool(), self::executiveSummaryTool()];
    }

    public static function statusCountsTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_assessment_status_counts',
            description: 'Get facility assessment counts by status (draft, in_progress, completed), or a specific status count.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => ['draft', 'in_progress', 'completed']],
                ],
            ],
            // Mirrors AssessmentResource::canAccess() — a user without this
            // permission can't see the Assessments resource at all, so this
            // tool must not even be offered to the model for them.
            authorize: fn (User $user) => $user->can('view_any_assessment'),
            execute: fn (array $args, User $user) => app(AssessmentSummaryQueryService::class)
                ->statusCounts($user, $args['status'] ?? null),
        );
    }

    public static function readinessScoresTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_facility_readiness_scores',
            description: 'Get facility readiness (assessment) scores, optionally filtered to one facility or a percentage threshold.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'facility_name' => ['type' => 'string'],
                    'below_percentage' => ['type' => 'number'],
                ],
            ],
            authorize: fn (User $user) => $user->can('view_any_assessment'),
            execute: fn (array $args, User $user) => [
                'scores' => app(AssessmentSummaryQueryService::class)->readinessScores(
                    $user,
                    $args['facility_name'] ?? null,
                    $args['below_percentage'] ?? null,
                ),
            ],
        );
    }

    public static function executiveSummaryTool(): ChatTool
    {
        return new SimpleChatTool(
            name: 'get_facility_executive_summary',
            description: 'Get the executive summary insights for a named facility\'s latest completed assessment.',
            schema: [
                'type' => 'object',
                'properties' => ['facility_name' => ['type' => 'string']],
                'required' => ['facility_name'],
            ],
            authorize: fn (User $user) => $user->can('view_any_assessment'),
            execute: function (array $args, User $user) {
                $result = app(AssessmentSummaryQueryService::class)
                    ->facilityExecutiveSummary($user, $args['facility_name']);

                return $result ?? ['error' => 'No completed assessment found for that facility.'];
            },
        );
    }
}

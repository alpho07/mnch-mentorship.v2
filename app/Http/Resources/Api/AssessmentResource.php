<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResource extends JsonResource {

    public function toArray($request): array {
        return [
            'id' => $this->id,
            'facility_id' => $this->facility_id,
            'facility_name' => $this->facility?->name,
            'mfl_code' => $this->facility?->mfl_code,
            'county' => $this->facility?->subcounty?->county?->name,
            'subcounty' => $this->facility?->subcounty?->name,
            'assessment_type' => $this->assessment_type,
            'assessment_date' => $this->assessment_date instanceof \Carbon\Carbon ? $this->assessment_date->toDateString() : $this->assessment_date,
            'assessor_name' => $this->assessor_name,
            'assessor_contact' => $this->assessor_contact,
            'status' => $this->status,
            'section_progress' => $this->section_progress ?? [],
            'overall_score' => $this->overall_score,
            'overall_percentage' => $this->overall_percentage,
            'overall_grade' => $this->overall_grade,
            'completed_at' => $this->completed_at instanceof \Carbon\Carbon ? $this->completed_at->toDateString() : $this->completed_at,
            'created_at'  => $this->created_at?->toIso8601String(),
            'is_trashed'  => $this->deleted_at !== null,
            'section_scores' => $this->whenLoaded('sectionScores', fn() =>
                    $this->sectionScores->mapWithKeys(fn($s) => [
                        $s->section->code => [
                            'percentage' => $s->percentage,
                            'grade' => $s->grade,
                            'answered_questions' => $s->answered_questions,
                            'total_questions' => $s->total_questions,
                            'skipped_questions' => $s->skipped_questions,
                        ],
                            ])
            ),
            'team' => $this->whenLoaded('teamMembers', fn () => $this->teamMembers->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
            ])->values()),
            'lead_assessor' => $this->when($this->relationLoaded('teamMembers'), function () {
                $lead = $this->teamMembers->first(fn ($member) => $member->pivot->role === 'team_lead');

                return [
                    'id' => $lead?->id ?? $this->assessor_id,
                    'name' => $lead?->name ?? $this->assessor_name,
                    'email' => $lead?->email ?? $this->assessor_contact,
                    'role' => 'team_lead',
                ];
            }),
            'team_members' => $this->whenLoaded('teamMembers', fn () => $this->teamMembers
                ->filter(fn ($member) => $member->pivot->role === 'member')
                ->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => 'member',
                ])->values()),
            'can_manage_team' => $this->when($request->user(), fn () => $this->canManageTeam($request->user()->id)),
        ];
    }
}

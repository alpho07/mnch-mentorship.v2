<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource {

    public function toArray($request): array { 
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->getRoleNames()->first() ?? 'user',
            'roles' => $this->getRoleNames()->values(),
            'status' => $this->status ?? 'active',
            'avatar' => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'initials' => $this->getInitials(),
            'facility_id'  => $this->facility_id,
            'subcounty_id' => $this->whenLoaded('facility', fn() => $this->facility->subcounty_id ?? null),
            'county_id'    => $this->whenLoaded('facility', fn() => $this->facility->subcounty->county_id ?? null),
            'facility' => $this->whenLoaded('facility', fn() => [
                'id'           => $this->facility->id,
                'name'         => $this->facility->name,
                'mfl_code'     => $this->facility->mfl_code,
                'subcounty_id' => $this->facility->subcounty_id ?? null,
                'county'       => $this->facility->subcounty->county->name ?? null,
                'county_id'    => $this->facility->subcounty->county_id ?? null,
                'subcounty'    => $this->facility->subcounty->name ?? null,
            ]),
            'cadre' => $this->whenLoaded('cadre', fn() => $this->cadre?->name),
            'department' => $this->whenLoaded('department', fn() => $this->department?->name),
            'created_at' => $this->created_at?->toDateString(),
            // Scope config — included so mobile app avoids a second API call on login
            'scopes' => \App\Models\Scope::forUser($this->resource)
                ->map(fn ($scope) => [
                    'id'       => $scope->slug,
                    'label'    => $scope->label,
                    'icon'     => $scope->icon,
                    'color'    => $scope->color,
                    'gradient' => $scope->gradient,
                    'tabs'     => $scope->tabs,
                    'summary'  => [],   // summaries fetched lazily via /scope-config
                ])
                ->values(),
        ];
    }

    private function getInitials(): string {
        $parts = explode(' ', $this->name ?? '');
        return strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
    }
}

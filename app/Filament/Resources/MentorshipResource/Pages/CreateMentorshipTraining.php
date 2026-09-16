<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Facility;
use App\Models\FacilityAssessment;
use App\Models\Program;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMentorshipTraining extends CreateRecord
{
    protected static string $resource = MentorshipTrainingResource::class;

    /**
     * Backs the "New Mentorship" button's own ->disabled() state on the list
     * page (cosmetic) — this is the actual enforcement, so the /create route
     * can't be reached directly while the method is turned off in
     * Mentorship Settings.
     */
    public static function canAccess(array $parameters = []): bool
    {
        return parent::canAccess($parameters) && Setting::getBool(Setting::NEW_MENTORSHIP_BUTTON_ENABLED);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('classes', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'facility_mentorship';

        // Auto-generate unique identifier
        $data['identifier'] = 'MT-'.strtoupper(\Illuminate\Support\Str::random(6));

        // This form is a single-step create — unlike the guided wizard,
        // there's no multi-step abandonment risk, so it's "complete" the
        // moment it's created. Prevents it from showing as a pending
        // guided-setup draft on the list page.
        $data['guided_setup_completed_at'] = now();

        if (empty($data['title'])) {
            $program = isset($data['program_id']) ? Program::find($data['program_id']) : null;
            $facility = isset($data['facility_id']) ? Facility::find($data['facility_id']) : null;
            $date = ! empty($data['start_date']) ? \Carbon\Carbon::parse($data['start_date'])->format('M Y') : now()->format('M Y');

            $data['title'] = trim(implode(' - ', array_filter([
                $program?->name ?? 'MNCH Mentorship',
                $facility?->name,
                $date,
            ])));
        }

        // Check facility assessment
        if (isset($data['facility_id'])) {
            $assessment = FacilityAssessment::where('facility_id', $data['facility_id'])
                ->where('status', 'approved')
                ->where('next_assessment_due', '>', now())
                ->latest()
                ->first();

            if (! $assessment) {
                // throw new \Exception('Facility assessment must be completed and approved before creating mentorship.');
            }
        }

        // Validate assessment weights
        if (isset($data['assessment_category_settings']) && ! empty($data['assessment_category_settings'])) {
            $totalWeight = collect($data['assessment_category_settings'])->sum('weight_percentage');
            if (abs($totalWeight - 100.0) >= 0.1) {
                throw new \Exception("Assessment category weights must total 100%. Current total: {$totalWeight}%");
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Only handle assessment categories - let Filament handle the other relationships automatically
        if (isset($this->data['assessment_category_settings'])) {
            $this->attachAssessmentCategories();
        }
    }

    private function attachAssessmentCategories(): void
    {
        $settings = $this->data['assessment_category_settings'] ?? [];
        $attachData = [];

        foreach ($settings as $setting) {
            if (! ($setting['is_active'] ?? true)) {
                continue;
            }

            $attachData[$setting['assessment_category_id']] = [
                'weight_percentage' => $setting['weight_percentage'],
                'pass_threshold' => $setting['pass_threshold'] ?? 70.00,
                'is_required' => $setting['is_required'] ?? true,
                'order_sequence' => $setting['order_sequence'] ?? 1,
                'is_active' => true,
            ];
        }

        if (! empty($attachData)) {
            $this->record->assessmentCategories()->sync($attachData);
        }
    }

    // ── Rename the submit button to "Submit" ──────────────────────────────
    public static function canCreateAnother(): bool
    {
        return false;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Mentorship Created')
            ->body('Mentorship created successfully. You can now add classes.');
    }

    public function getTitle(): string
    {
        return 'New Mentorship';
    }
}

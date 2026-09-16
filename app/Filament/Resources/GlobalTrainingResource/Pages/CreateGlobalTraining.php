<?php

namespace App\Filament\Resources\GlobalTrainingResource\Pages;

use App\Filament\Resources\GlobalTrainingResource;
use App\Models\AssessmentCategory;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateGlobalTraining extends CreateRecord {

    protected static string $resource = GlobalTrainingResource::class;

    protected function getRedirectUrl(): string {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array {
        $data['type'] = 'global_training';
        $data['mentor_id'] = auth()->id();

        // Only validate assessment weights if assessments are enabled
        if (($data['assess_participants'] ?? false) && isset($data['assessment_category_settings']) && !empty($data['assessment_category_settings'])) {
            $totalWeight = collect($data['assessment_category_settings'])->sum('weight_percentage');
            if (abs($totalWeight - 100.0) >= 0.1) {
                throw new \Exception("Assessment category weights must total 100%. Current total: {$totalWeight}%");
            }
        }

        // Handle additional locations from tags input
        if (!empty($data['additional_locations'])) {
            $locationIds = [];
            foreach ($data['additional_locations'] as $locationName) {
                $location = \App\Models\Location::firstOrCreate(
                        ['name' => $locationName],
                        ['type' => 'other']
                );
                $locationIds[] = $location->id;
            }

            // Merge with existing selected locations
            $existingLocationIds = $data['locations'] ?? [];
            $data['locations'] = array_unique(array_merge($existingLocationIds, $locationIds));
        }

        // Store assessment and materials settings for later processing
        $this->assessmentSettings = $data['assess_participants'] ?? false ? ($data['assessment_category_settings'] ?? []) : [];
        $this->materialsEnabled = $data['provide_materials'] ?? false;

        // Clean up data - remove fields that shouldn't be saved to database
        unset($data['additional_locations']);
        unset($data['selected_assessment_categories']);
        unset($data['assessment_category_settings']);
        unset($data['total_weight_check']);

        return $data;
    }

    protected function afterCreate(): void {
        // Only handle assessment categories if assessments are enabled
        if ($this->record->assess_participants && !empty($this->assessmentSettings)) {
            $this->attachAssessmentCategories();
        }
    }

    private function attachAssessmentCategories(): void {
        $settings = $this->assessmentSettings ?? [];
        $attachData = [];

        foreach ($settings as $setting) {
            if (isset($setting['assessment_category_id']) && !empty($setting['assessment_category_id'])) {
                // Only attach if category is active
                if (!($setting['is_active'] ?? true)) continue;

                $attachData[$setting['assessment_category_id']] = [
                    'weight_percentage' => $setting['weight_percentage'] ?? 25.0,
                    'pass_threshold' => $setting['pass_threshold'] ?? 70.00,
                    'is_required' => $setting['is_required'] ?? true,
                    'order_sequence' => $setting['order_sequence'] ?? 1,
                    'is_active' => true,
                ];
            }
        }

        if (!empty($attachData)) {
            $this->record->assessmentCategories()->sync($attachData);
        }
    }

    protected function getCreatedNotification(): ?Notification {
        $categoriesCount = $this->record->assessmentCategories()->count();
        $programsCount = $this->record->programs()->count();
        $materialsCount = $this->record->trainingMaterials()->count();

        $features = [];
        if ($categoriesCount > 0) {
            $features[] = "{$categoriesCount} assessment categories";
        }
        if ($programsCount > 0) {
            $features[] = "{$programsCount} programs";
        }
        if ($materialsCount > 0) {
            $features[] = "{$materialsCount} materials";
        }

        $featuresText = !empty($features) ? ' with ' . implode(', ', $features) : '';

        $actions = [];
        
        // Add participants action
        $actions[] = \Filament\Notifications\Actions\Action::make('add_participants')
                ->button()
                ->url($this->getResource()::getUrl('participants', ['record' => $this->record]))
                ->label('Add Participants');

        // Add assessments action only if assessments are enabled
        if ($this->record->assess_participants) {
            $actions[] = \Filament\Notifications\Actions\Action::make('view_assessments')
                    ->button()
                    ->url($this->getResource()::getUrl('assessments', ['record' => $this->record]))
                    ->label('Manage Assessments');
        }

        return Notification::make()
                        ->success()
                        ->title('MOH Training Created')
                        ->body("Training created successfully{$featuresText}. You can now add participants and begin the training program.")
                        ->actions($actions);
    }

    public function getTitle(): string {
        return 'Create New MOH Training';
    }

    // Store assessment settings temporarily
    private array $assessmentSettings = [];
    private bool $materialsEnabled = false;
}
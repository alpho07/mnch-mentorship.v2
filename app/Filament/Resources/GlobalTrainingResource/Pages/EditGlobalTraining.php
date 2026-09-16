<?php

namespace App\Filament\Resources\GlobalTrainingResource\Pages;

use App\Filament\Resources\GlobalTrainingResource;
use App\Models\AssessmentCategory;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class EditGlobalTraining extends EditRecord {

    protected static string $resource = GlobalTrainingResource::class;
    protected static ?string $title = 'Edit MOH Training';

    protected function getHeaderActions(): array {
        return [
                    Actions\ViewAction::make()
                    ->color('info'),
                    Actions\Action::make('manage_participants')
                    ->label('Manage Participants')
                    ->icon('heroicon-o-users')
                    ->color('success')
                    ->url(fn() => static::getResource()::getUrl('participants', ['record' => $this->record])),
                    Actions\Action::make('manage_assessments')
                    ->label('Manage Assessments')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->url(fn() => static::getResource()::getUrl('assessments', ['record' => $this->record]))
                    ->visible(fn() => $this->record->assess_participants === true),
                    Actions\DeleteAction::make()
                    ->requiresConfirmation(),
        ];
    }

    protected function getRedirectUrl(): string {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeFill(array $data): array {
        // Load existing assessment categories when editing
        if ($this->record && $this->record->exists) {
            $assessmentCategories = $this->record->assessmentCategories()->get();

            if ($assessmentCategories->isNotEmpty()) {
                // Set assessment toggle to true
                $data['assess_participants'] = true;

                // Set selected categories
                $data['selected_assessment_categories'] = $assessmentCategories->pluck('id')->toArray();

                // Set category settings
                $data['assessment_category_settings'] = $assessmentCategories->map(function ($category) {
                            return [
                                'assessment_category_id' => $category->id,
                                'weight_percentage' => $category->pivot->weight_percentage,
                                'pass_threshold' => $category->pivot->pass_threshold,
                                'is_required' => $category->pivot->is_required,
                                'is_active' => $category->pivot->is_active,
                                'order_sequence' => $category->pivot->order_sequence,
                            ];
                        })->toArray();

                // Set total weight check
                $data['total_weight_check'] = $assessmentCategories->sum('pivot.weight_percentage');
            }

            // Check if materials are configured
            if ($this->record->trainingMaterials()->exists()) {
                $data['provide_materials'] = true;
            }

            // Load program/module relationships
            $data['programs'] = $this->record->programs()->pluck('programs.id')->toArray();
            $data['modules'] = $this->record->modules()->pluck('modules.id')->toArray();
            $data['methodologies'] = $this->record->methodologies()->pluck('methodologies.id')->toArray();
            $data['locations'] = $this->record->locations()->pluck('locations.id')->toArray();
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array {
        $data['type'] = 'global_training';

        // Only set mentor_id if it's not already set (preserve existing mentor on edit)
        if (empty($data['mentor_id'])) {
            $data['mentor_id'] = auth()->id();
        }

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

        // Store assessment settings for later processing
        $this->assessmentSettings = ($data['assess_participants'] ?? false) ? ($data['assessment_category_settings'] ?? []) : [];
        $this->assessmentEnabled = $data['assess_participants'] ?? false;

        // Clean up data - remove fields that shouldn't be saved to database
        unset($data['additional_locations']);
        unset($data['selected_assessment_categories']);
        unset($data['assessment_category_settings']);
        unset($data['total_weight_check']);

        return $data;
    }

    protected function afterSave(): void {
        // Handle assessment categories based on toggle state
        if ($this->assessmentEnabled && !empty($this->assessmentSettings)) {
            $this->attachAssessmentCategories();
        } elseif (!$this->assessmentEnabled) {
            // If assessments are disabled, remove any existing assessment categories
            $this->record->assessmentCategories()->detach();
        }

        // Handle program/module relationships - let Filament handle these automatically through the form
        // No need to manually sync as the relationship fields will handle this
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
        } else {
            // If no valid settings, detach all
            $this->record->assessmentCategories()->detach();
        }
    }

    protected function getSavedNotification(): ?Notification {
        $categoriesCount = $this->record->assessmentCategories()->count();
        $programsCount = $this->record->programs()->count();
        $materialsCount = $this->record->trainingMaterials()->count();
        $participantsCount = $this->record->participants()->count();

        $features = [];
        if ($categoriesCount > 0) {
            $totalWeight = $this->record->assessmentCategories()
                    ->sum('training_assessment_categories.weight_percentage');
            $features[] = "{$categoriesCount} assessment categories ({$totalWeight}%)";
        }
        if ($programsCount > 0) {
            $features[] = "{$programsCount} programs";
        }
        if ($materialsCount > 0) {
            $features[] = "{$materialsCount} materials";
        }
        if ($participantsCount > 0) {
            $features[] = "{$participantsCount} participants";
        }

        $featuresText = !empty($features) ? ' with ' . implode(', ', $features) : '';

        $actions = [];
        
        // Add view action
        $actions[] = \Filament\Notifications\Actions\Action::make('view_training')
                ->button()
                ->url($this->getResource()::getUrl('view', ['record' => $this->record]))
                ->label('View Training');

        // Add participants action if no participants yet
        if ($participantsCount === 0) {
            $actions[] = \Filament\Notifications\Actions\Action::make('add_participants')
                    ->button()
                    ->url($this->getResource()::getUrl('participants', ['record' => $this->record]))
                    ->label('Add Participants');
        }

        return Notification::make()
                        ->success()
                        ->title('Training Updated Successfully')
                        ->body("Training updated{$featuresText}.")
                        ->actions($actions);
    }

    // Store assessment settings temporarily
    private array $assessmentSettings = [];
    private bool $assessmentEnabled = false;
}
<?php
namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\AssessmentCategory;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class EditMentorshipTraining extends EditRecord
{
    protected static string $resource = MentorshipTrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()->color('info'),

            Actions\ActionGroup::make([
                Actions\Action::make('manage_mentees')
                    ->label('Manage Mentees')
                    ->icon('heroicon-o-users')
                    ->url(fn () => static::getResource()::getUrl('mentees', ['record' => $this->record])),
                
                Actions\Action::make('manage_classes')
                    ->label('Manage Classes')
                    ->icon('heroicon-o-rectangle-stack')
                    ->url(fn () => static::getResource()::getUrl('classes', ['record' => $this->record])),

                Actions\Action::make('co_mentors')
                    ->label('Co-Mentors')
                    ->icon('heroicon-o-user-group')
                    ->url(fn () => static::getResource()::getUrl('co-mentors', ['record' => $this->record])),
                
                Actions\Action::make('duplicate_training')
                    ->label('Duplicate Mentorship')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function () {
                        $newTraining = $this->record->replicate();
                        $newTraining->title = $this->record->title . ' (Copy)';
                        $newTraining->identifier = 'MT-' . strtoupper(Str::random(6));
                        $newTraining->status = 'draft';
                        $newTraining->save();

                        // Copy assessment categories with pivot data
                        $categoryData = [];
                        foreach ($this->record->assessmentCategories as $category) {
                            $categoryData[$category->id] = [
                                'weight_percentage' => $category->pivot->weight_percentage,
                                'pass_threshold' => $category->pivot->pass_threshold,
                                'is_required' => $category->pivot->is_required,
                                'order_sequence' => $category->pivot->order_sequence,
                                'is_active' => $category->pivot->is_active,
                            ];
                        }
                        $newTraining->assessmentCategories()->sync($categoryData);

                        // Copy program/module relationships
                        $newTraining->programs()->attach($this->record->programs->pluck('id'));
                        $newTraining->modules()->attach($this->record->modules->pluck('id'));
                        $newTraining->methodologies()->attach($this->record->methodologies->pluck('id'));

                        // Copy training materials
                        foreach ($this->record->trainingMaterials as $material) {
                            $newMaterial = $material->replicate();
                            $newMaterial->training_id = $newTraining->id;
                            $newMaterial->quantity_used = 0;
                            $newMaterial->save();
                        }

                        Notification::make()
                            ->title('Mentorship Duplicated')
                            ->body('Copy created with all content and assessment categories.')
                            ->success()
                            ->send();

                        return redirect()->to(static::getResource()::getUrl('edit', ['record' => $newTraining]));
                    }),
            ])
            ->label('Quick Actions')
            ->color('success')
            ->button(),

            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->before(function () {
                    // Clean up assessment results
                    \App\Models\MenteeAssessmentResult::whereHas('participant', function ($query) {
                        $query->where('training_id', $this->record->id);
                    })->delete();
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load existing assessment categories when editing
        if ($this->record && $this->record->exists) {
            $assessmentCategories = $this->record->assessmentCategories()->get();
            
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

            // Load program/module relationships
            $data['programs'] = $this->record->programs()->pluck('programs.id')->toArray();
            $data['modules'] = $this->record->modules()->pluck('modules.id')->toArray();
            $data['methodologies'] = $this->record->methodologies()->pluck('methodologies.id')->toArray();
        }
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = 'facility_mentorship';
        
        // Validate weights if present
        if (isset($data['assessment_category_settings']) && !empty($data['assessment_category_settings'])) {
            $totalWeight = collect($data['assessment_category_settings'])->sum('weight_percentage');
            if (abs($totalWeight - 100.0) >= 0.1) {
                throw new \Exception("Assessment category weights must total 100%. Current total: {$totalWeight}%");
            }
        }
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Handle assessment categories for existing records
        if (isset($this->data['assessment_category_settings'])) {
            $this->attachAssessmentCategories();
        }

        // Handle program/module relationships
        if (isset($this->data['programs'])) {
            $this->record->programs()->sync($this->data['programs']);
        }

        if (isset($this->data['modules'])) {
            $this->record->modules()->sync($this->data['modules']);
        }

        if (isset($this->data['methodologies'])) {
            $this->record->methodologies()->sync($this->data['methodologies']);
        }
    }

    private function attachAssessmentCategories(): void
    {
        $settings = $this->data['assessment_category_settings'] ?? [];
        $attachData = [];
        
        foreach ($settings as $setting) {
            if (!($setting['is_active'] ?? true)) continue;
            
            $attachData[$setting['assessment_category_id']] = [
                'weight_percentage' => $setting['weight_percentage'],
                'pass_threshold' => $setting['pass_threshold'] ?? 70.00,
                'is_required' => $setting['is_required'] ?? true,
                'order_sequence' => $setting['order_sequence'] ?? 1,
                'is_active' => true,
            ];
        }
        
        if (!empty($attachData)) {
            $this->record->assessmentCategories()->sync($attachData);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
    
    public function getTitle(): string{
        return 'Edit Mentorship';
    }

    protected function getSavedNotification(): ?Notification
    {
        $categoriesCount = $this->record->assessmentCategories()->count();
        $programsCount = $this->record->programs()->count();
        $totalWeight = $this->record->assessmentCategories()
            ->sum('training_assessment_categories.weight_percentage');
        
        return Notification::make()
            ->success()
            ->title('Mentorship Updated')
            ->body("Updated with {$categoriesCount} categories, {$programsCount} programs (Total: {$totalWeight}%)")
            ->actions([
                \Filament\Notifications\Actions\Action::make('view_training')
                    ->button()
                    ->url($this->getResource()::getUrl('view', ['record' => $this->record])),
            ]);
    }
}

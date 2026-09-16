<?php

namespace App\Filament\Resources\ResourceResource\Pages;

use App\Filament\Resources\ResourceResource;
use App\Models\ProgramModule;
use App\Models\Tag;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class EditResource extends EditRecord
{
    protected static string $resource = ResourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_frontend')
                ->label('View Live')
                ->icon('heroicon-o-globe-alt')
                ->url(fn () => route('resources.show', $this->getRecord()->slug))
                ->openUrlInNewTab()
                ->visible(fn () => $this->getRecord()->status === 'published'),

            Actions\Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->url(fn () => route('admin.resources.preview', $this->getRecord()))
                ->openUrlInNewTab()
                ->visible(fn () => $this->getRecord()->status === 'draft'),

            Actions\Action::make('download_primary')
                ->label('Download Primary File')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $primaryFile = $this->getRecord()->primaryFile;
                    if ($primaryFile && $primaryFile->exists()) {
                        return response()->download(
                            Storage::disk('resources')->path($primaryFile->file_path),
                            $primaryFile->original_name
                        );
                    }
                })
                ->visible(fn () => $this->getRecord()->hasPrimaryFile()),

            Actions\Action::make('view_activities')
                ->label('View Activity Log')
                ->icon('heroicon-o-clock')
                ->modalContent(fn () => view('filament.modals.activity-log', [
                    'activities' => $this->getRecord()->activities()->with('causer')->latest()->take(20)->get()
                ]))
                ->modalWidth('2xl')
                ->visible(fn () => method_exists($this->getRecord(), 'activities')),

            Actions\ReplicateAction::make()
                ->excludeAttributes(['slug', 'view_count', 'download_count', 'like_count'])
                ->beforeReplicaSaved(function ($replica, array $data): void {
                    $replica->title = $data['title'] . ' (Copy)';
                    $replica->slug = Str::slug($replica->title);
                    $replica->status = 'draft';
                    $replica->published_at = null;
                }),

            Actions\DeleteAction::make()
                ->before(function () {
                    // Log activity before deletion if function exists
                    if (function_exists('activity')) {
                        activity()
                            ->performedOn($this->getRecord())
                            ->causedBy(auth()->user())
                            ->log("Deleted resource: {$this->getRecord()->title}");
                    }
                }),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Resource updated successfully';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load existing tag names for display
        if ($this->getRecord()->tags) {
            $data['tag_names'] = $this->getRecord()->tags->pluck('name')->toArray();
        }

        // Load program module selections
        $moduleIds = $this->getRecord()->programModules()->pluck('program_modules.id')->toArray();
        $data['program_module_ids'] = $moduleIds;
        $programIds = ProgramModule::whereIn('id', $moduleIds)->pluck('program_id')->unique()->values()->toArray();
        $data['program_ids'] = $programIds;

        // Load existing files data for the repeater
        $data['resource_files'] = $this->getRecord()->files->map(function ($file) {
            return [
                'id' => $file->id,
                'file_path' => $file->file_path,
                'original_name' => $file->original_name,
                'description' => $file->description,
                'is_primary' => $file->is_primary,
                'file_name' => $file->file_name,
                'file_size' => $file->file_size,
                'file_type' => $file->file_type,
                'sort_order' => $file->sort_order,
            ];
        })->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Strip virtual fields before model update
        unset($data['program_ids'], $data['program_module_ids']);

        // Handle tag names conversion
        if (isset($data['tag_names']) && is_array($data['tag_names'])) {
            $tagIds = [];
            foreach ($data['tag_names'] as $tagName) {
                if (!empty($tagName)) {
                    $tag = Tag::firstOrCreate(
                        ['name' => $tagName],
                        ['slug' => Str::slug($tagName)]
                    );
                    $tagIds[] = $tag->id;
                }
            }
            $data['tag_ids'] = $tagIds;
            unset($data['tag_names']);
        }

        // Auto-generate slug if changed
        if (isset($data['title']) && $data['title'] !== $this->getRecord()->title) {
            if (empty($data['slug']) || $data['slug'] === Str::slug($this->getRecord()->title)) {
                $data['slug'] = Str::slug($data['title']);
            }
        }

        // Handle resource files - we'll process this separately
        if (isset($data['resource_files'])) {
            $this->pendingFiles = $data['resource_files'];
            unset($data['resource_files']); // Remove from main data to avoid mass assignment issues
        }

        return $data;
    }

    protected $pendingFiles = [];

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Update the main resource
        $record->update($data);

        // Handle file relationships
        if (!empty($this->pendingFiles)) {
            $this->syncResourceFiles($record, $this->pendingFiles);
        }

        return $record;
    }

    protected function syncResourceFiles($record, array $filesData): void
    {
        $existingFileIds = [];

        foreach ($filesData as $index => $fileData) {
            if (isset($fileData['id']) && !empty($fileData['id'])) {
                // Update existing file
                $file = $record->files()->find($fileData['id']);
                if ($file) {
                    $updateData = [
                        'sort_order' => $index,
                    ];

                    // Only update fields that are present and not empty
                    if (isset($fileData['original_name']) && !empty($fileData['original_name'])) {
                        $updateData['original_name'] = $fileData['original_name'];
                    }
                    
                    if (isset($fileData['description'])) {
                        $updateData['description'] = $fileData['description'];
                    }
                    
                    if (isset($fileData['is_primary'])) {
                        $updateData['is_primary'] = $fileData['is_primary'];
                    }

                    $file->update($updateData);
                    $existingFileIds[] = $file->id;
                }
            } else {
                // Create new file (only if file_path is provided)
                if (isset($fileData['file_path']) && !empty($fileData['file_path'])) {
                    $newFile = $record->files()->create([
                        'original_name' => $fileData['original_name'] ?? $fileData['file_name'] ?? 'Uploaded File',
                        'file_name' => $fileData['file_name'] ?? $fileData['original_name'] ?? 'uploaded_file',
                        'file_path' => $fileData['file_path'],
                        'file_size' => $fileData['file_size'] ?? 0,
                        'file_type' => $fileData['file_type'] ?? 'application/octet-stream',
                        'description' => $fileData['description'] ?? null,
                        'is_primary' => $fileData['is_primary'] ?? false,
                        'sort_order' => $index,
                    ]);
                    $existingFileIds[] = $newFile->id;

                    // Log file upload activity
                    if (function_exists('activity')) {
                        activity()
                            ->performedOn($record)
                            ->causedBy(auth()->user())
                            ->withProperties(['file_name' => $newFile->original_name])
                            ->log("Uploaded new file: {$newFile->original_name}");
                    }
                }
            }
        }

        // Remove files that are no longer in the form
        $filesToDelete = $record->files()->whereNotIn('id', $existingFileIds)->get();
        foreach ($filesToDelete as $file) {
            // Log file deletion activity
            if (function_exists('activity')) {
                activity()
                    ->performedOn($record)
                    ->causedBy(auth()->user())
                    ->withProperties(['file_name' => $file->original_name])
                    ->log("Deleted file: {$file->original_name}");
            }

            $file->deleteFile(); // Delete physical file
            $file->delete(); // Delete record
        }
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        // Sync tags after save
        if (isset($this->data['tag_ids'])) {
            $record->tags()->sync($this->data['tag_ids']);
        }

        // Sync access groups
        if (isset($this->data['access_groups'])) {
            $record->accessGroups()->sync($this->data['access_groups']);
        }

        // Sync scoped facilities
        if (isset($this->data['scoped_facilities'])) {
            $record->scopedFacilities()->sync($this->data['scoped_facilities']);
        }

        // Sync scoped departments
        if (isset($this->data['scoped_departments'])) {
            $record->scopedDepartments()->sync($this->data['scoped_departments']);
        }

        // Sync program modules
        $moduleIds = $this->data['program_module_ids'] ?? [];
        $record->programModules()->sync($moduleIds);

        // Ensure only one primary file exists
        $this->ensureSinglePrimaryFile($record);

        // Log general update activity
        if (function_exists('activity')) {
            $changedFields = array_keys($record->getChanges());
            if (!empty($changedFields)) {
                activity()
                    ->performedOn($record)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'changed_fields' => $changedFields,
                        'status' => $record->status,
                        'visibility' => $record->visibility,
                    ])
                    ->log("Updated resource: {$record->title}");
            }
        }

        // Log specific status changes
        if ($record->wasChanged('status')) {
            $oldStatus = $record->getOriginal('status');
            $newStatus = $record->status;
            
            if (function_exists('activity')) {
                activity()
                    ->performedOn($record)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                    ])
                    ->log("Status changed from {$oldStatus} to {$newStatus}");
            }
        }

        // Log featured image updates
        if ($record->wasChanged('featured_image')) {
            if (function_exists('activity')) {
                activity()
                    ->performedOn($record)
                    ->causedBy(auth()->user())
                    ->log('Featured image updated');
            }
        }
    }

    protected function ensureSinglePrimaryFile($record): void
    {
        $primaryFiles = $record->files()->where('is_primary', true)->get();
        
        if ($primaryFiles->count() > 1) {
            // Keep the first one, remove primary flag from others
            $primaryFiles->skip(1)->each(function ($file) {
                $file->update(['is_primary' => false]);
            });

            if (function_exists('activity')) {
                activity()
                    ->performedOn($record)
                    ->causedBy(auth()->user())
                    ->log('Multiple primary files detected - kept only the first one as primary');
            }
        } elseif ($primaryFiles->count() === 0 && $record->files()->exists()) {
            // If no primary file and files exist, make the first one primary
            $firstFile = $record->files()->orderBy('sort_order')->first();
            if ($firstFile) {
                $firstFile->update(['is_primary' => true]);

                if (function_exists('activity')) {
                    activity()
                        ->performedOn($record)
                        ->causedBy(auth()->user())
                        ->withProperties(['file_name' => $firstFile->original_name])
                        ->log("Set {$firstFile->original_name} as primary file");
                }
            }
        }
    }

    // Helper method to get redirect URL after save
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    // Custom validation messages
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Update Resource'),
            $this->getCancelFormAction(),
        ];
    }

    // Handle form validation errors specifically for files
    protected function onValidationError(\Illuminate\Validation\ValidationException $exception): void
    {
        // Check if there are file upload errors
        $errors = $exception->validator->errors()->toArray();
        $fileErrors = array_filter($errors, function($key) {
            return str_contains($key, 'resource_files') || str_contains($key, 'file_path');
        }, ARRAY_FILTER_USE_KEY);

        if (!empty($fileErrors)) {
            $this->dispatch('file-upload-error', [
                'message' => 'There were errors with your file uploads. Please check the file sizes and formats.'
            ]);
        }

        parent::onValidationError($exception);
    }
}
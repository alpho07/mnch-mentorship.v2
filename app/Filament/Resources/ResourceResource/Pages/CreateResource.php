<?php

namespace App\Filament\Resources\ResourceResource\Pages;

use App\Filament\Resources\ResourceResource;
use App\Models\Tag;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateResource extends CreateRecord {

    protected static string $resource = ResourceResource::class;

    protected function getRedirectUrl(): string {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string {
        return 'Resource created successfully';
    }

    protected function handleRecordCreation(array $data): Model {
        $record = static::getModel()::create($data);

        // Log the creation with custom properties
        activity()
                ->performedOn($record)
                ->causedBy(auth()->user())
                ->withProperties([
                    'category' => $record->category?->name,
                    'type' => $record->resourceType?->name,
                    'visibility' => $record->visibility,
                ])
                ->log("Created resource: {$record->title}");

        return $record;
    }

    protected function mutateFormDataBeforeCreate(array $data): array {
        // Strip virtual fields before model creation
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

        // Auto-generate slug if not provided
        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        // Set author if not provided
        if (empty($data['author_id'])) {
            $data['author_id'] = auth()->id();
        }

        return $data;
    }

    protected function afterCreate(): void {
        $record = $this->record;
        $data = $this->data;

        // Sync tags
        if (isset($data['tag_ids'])) {
            $record->tags()->sync($data['tag_ids']);
        }

        // Sync access groups
        if (isset($data['access_groups'])) {
            $record->accessGroups()->sync($data['access_groups']);
        }

        // Sync program modules
        $moduleIds = $data['program_module_ids'] ?? [];
        $record->programModules()->sync($moduleIds);
    }
}

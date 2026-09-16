<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\ClassModule;
use App\Models\MentorshipClass;
use App\Models\ProgramModule;
use App\Models\Resource;
use App\Models\Training;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ManageModuleResources extends Page
{
    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.manage-module-resources';

    protected static bool $shouldRegisterNavigation = false;

    public Training $training;

    public MentorshipClass $class;

    public ClassModule $module;

    public ProgramModule $programModule;

    public bool $isEmonc = false;

    public function mount(Training $training, MentorshipClass $class, ClassModule $module): void
    {
        $this->training = $training->load('program');
        $this->class = $class;
        $this->module = $module->load([
            'programModule' => fn ($q) => $q->with([
                'contents',
                'quizzes.questions.options',
                'resources' => fn ($q) => $q->with(['primaryFile', 'category', 'resourceType']),
            ]),
        ]);
        $this->programModule = $module->programModule;
        $this->isEmonc = $this->detectEmonc();
    }

    private function detectEmonc(): bool
    {
        $name = strtolower($this->training->program?->name ?? '');

        return str_contains($name, 'maternal') && str_contains($name, 'emonc');
    }

    public function getTitle(): string
    {
        return "Resources — {$this->programModule->name}";
    }

    public function getSubheading(): ?string
    {
        if (! $this->isEmonc) {
            return $this->programModule->resources->count().' resource(s) attached';
        }

        $sectionCount = count($this->getResourceSections());

        return "{$sectionCount} section(s) in this module";
    }

    public function isAdmin(): bool
    {
        return auth()->user()?->can('update_program::module') ?? false;
    }

    public function detachResource(int $resourceId): void
    {
        if (! $this->isAdmin()) {
            Notification::make()->danger()->title('Not authorized')->send();

            return;
        }

        $resource = $this->programModule->resources->firstWhere('id', $resourceId);

        $this->programModule->resources()->detach($resourceId);
        $this->programModule->load([
            'resources' => fn ($q) => $q->with(['primaryFile', 'category', 'resourceType']),
        ]);

        Notification::make()->success()->title(($resource?->title ?? 'Resource').' removed from module')->send();
    }

    public function getResourceSections(): array
    {
        $introductions = $this->programModule->contents->where('type', 'introduction');
        $videos = $this->programModule->contents->where('type', 'video');
        $caseScenarios = $this->programModule->contents->where('type', 'case_scenario');
        $preTests = $this->programModule->quizzes->filter(fn ($q) => $q->isPreTest());
        $postTests = $this->programModule->quizzes->filter(fn ($q) => $q->isPostTest());
        $resources = $this->programModule->resources;

        return [
            'introduction' => [
                'title' => 'Introduction',
                'icon' => 'heroicon-o-book-open',
                'count' => $introductions->count() + ($this->programModule->description ? 1 : 0),
                'items' => $introductions,
                'has_description' => filled($this->programModule->description),
            ],
            'pre_test' => [
                'title' => 'Pre-Test',
                'icon' => 'heroicon-o-clipboard-document-check',
                'count' => $preTests->count(),
                'items' => $preTests,
            ],
            'video' => [
                'title' => 'Hands-on Videos',
                'icon' => 'heroicon-o-video-camera',
                'count' => $videos->count(),
                'items' => $videos,
            ],
            'case_scenario' => [
                'title' => 'Case Scenarios',
                'icon' => 'heroicon-o-document-text',
                'count' => $caseScenarios->count(),
                'items' => $caseScenarios,
            ],
            'post_test' => [
                'title' => 'Post-Test',
                'icon' => 'heroicon-o-check-badge',
                'count' => $postTests->count(),
                'items' => $postTests,
            ],
            'resources' => [
                'title' => 'Attached Resources',
                'icon' => 'heroicon-o-folder-open',
                'count' => $resources->count(),
                'items' => $resources,
            ],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_modules')
                ->label('Back to Modules')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('class-modules', [
                    'training' => $this->training->id,
                    'class' => $this->class->id,
                ])),

            Actions\Action::make('attach_resources')
                ->label('Attach Resources')
                ->icon('heroicon-o-link')
                ->color('primary')
                ->visible(fn () => $this->isAdmin())
                ->slideOver()
                ->modalWidth('2xl')
                ->modalHeading('Attach Resources to Module')
                ->modalDescription("Search and attach existing resources to '{$this->programModule->name}'.")
                ->form([
                    \Filament\Forms\Components\Select::make('resource_ids')
                        ->label('Resources')
                        ->multiple()
                        ->options(function () {
                            $alreadyLinked = $this->programModule->resources()->pluck('resources.id')->toArray();

                            return Resource::whereNotIn('id', $alreadyLinked)
                                ->orderBy('title')
                                ->pluck('title', 'id');
                        })
                        ->searchable()
                        ->required()
                        ->helperText('Resources already attached to this module are excluded.'),
                ])
                ->action(function (array $data) {
                    $ids = $data['resource_ids'] ?? [];
                    if (! empty($ids)) {
                        $this->programModule->resources()->syncWithoutDetaching($ids);
                        Notification::make()->success()->title(count($ids).' resource(s) attached')->send();
                    }
                }),
        ];
    }
}

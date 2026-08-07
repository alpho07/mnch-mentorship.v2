<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Cadre;
use App\Models\County;
use App\Models\Department;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Setting;
use App\Models\Training;
use App\Services\MentorshipWizardService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Url;

class QuickMentorshipSetup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.quick-mentorship-setup';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Same enforcement pattern as GuidedMentorshipSetup::canAccess() — a
     * ?training= query string means someone is resuming a session already
     * started, always allowed; a fresh visit requires the Settings toggle.
     */
    public static function canAccess(array $parameters = []): bool
    {
        if (! parent::canAccess($parameters)) {
            return false;
        }

        if (request()->filled('training')) {
            return true;
        }

        return Setting::getBool(Setting::QUICK_SETUP_BUTTON_ENABLED);
    }

    public ?array $data = [];

    #[Url(as: 'training')]
    public ?int $trainingId = null;

    #[Url(as: 'class')]
    public ?int $classId = null;

    public ?Training $training = null;

    public ?MentorshipClass $class = null;

    public bool $completed = false;

    public function mount(): void
    {
        $this->form->fill([]);
    }

    public function getTitle(): string
    {
        return 'Quick Setup';
    }

    public function getSubheading(): ?string
    {
        return 'Everything in one place — fill each section as you go.';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Placeholder')
                    ->schema([]),
            ])
            ->statePath('data');
    }
}

<?php

namespace App\Filament\Pages;

use App\Services\CpdPointsService;
use App\Services\ProgramCertificationService;
use Filament\Pages\Page;

class MentorCertificates extends Page
{
    protected static string $view = 'filament.pages.mentor-certificates';

    protected static ?string $slug = 'mentor-certificates';

    protected static ?string $navigationGroup = 'Dashboards';

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Mentor Certificates';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('page_MentorCertificates');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('page_MentorCertificates');
    }

    public array $programs = [];

    public array $cpd = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->programs = app(ProgramCertificationService::class)->mentorProgress($user);
        $this->cpd = app(CpdPointsService::class)->forMentor($user);
    }
}

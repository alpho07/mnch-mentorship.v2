<?php

namespace App\Filament\Pages;

use App\Services\CpdPointsService;
use App\Services\ProgramCertificationService;
use Filament\Pages\Page;

class MyCertificates extends Page
{
    protected static string $view = 'filament.pages.my-certificates';

    protected static ?string $slug = 'my-certificates';

    protected static ?string $navigationGroup = 'Dashboards';

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'My Certificates';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('page_MyCertificates');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('page_MyCertificates');
    }

    public array $programs = [];

    public array $cpd = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->programs = app(ProgramCertificationService::class)->menteeProgress($user);
        $this->cpd = app(CpdPointsService::class)->forMentee($user);
    }
}

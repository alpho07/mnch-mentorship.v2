<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\County;

class OrgUnitExplorer extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static string $view = 'filament.pages.org-unit-explorer';
    protected static ?string $title = 'Organization Unit Explorer';

    public $counties;
     public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->counties = County::with('subcounties.facilities.spokes')->orderBy('name')->get();
    }

    protected function getViewData(): array
    {
        return [
            'counties' => $this->counties,
        ];
    }
}

<?php

namespace App\Filament\Pages\Indicators;

use App\Models\Indicators\FacilityIndicatorAssignment;
use App\Models\Indicators\Indicator;
use App\Models\Indicators\IndicatorGroup;
use App\Models\Indicators\IndicatorReportType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class IndicatorsReference extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Indicators Reference';

    protected static ?string $navigationGroup = 'Indicator Catalog';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'indicators/reference';

    protected static string $view = 'filament.pages.indicators.reference';

    public static function shouldRegisterNavigation(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $user = auth()->user();

        if (! $user->can('page_IndicatorsReference')) {
            return false;
        }

        // Admin-level can view regardless of facility assignment
        if ($user->can('view_any_indicator')) {
            return true;
        }

        $facilityId = $user->facility_id;

        if (! $facilityId) {
            return false;
        }

        return FacilityIndicatorAssignment::where('facility_id', $facilityId)
            ->where('is_locked', true)
            ->exists();
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────────────────────────

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'search' => '',
            'report_type_id' => '',
            'group_id' => '',
            'category' => '',
            'indicator_type' => '',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Filter form
    // ──────────────────────────────────────────────────────────────────────────

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('search')
                    ->label('')
                    ->placeholder('Search indicators by name, code, or definition…')
                    ->prefixIcon('heroicon-o-magnifying-glass')
                    ->live(debounce: 300)
                    ->columnSpan(2),
                Select::make('report_type_id')
                    ->label('Report Type')
                    ->options(IndicatorReportType::active()->pluck('name', 'id')->prepend('All Types', ''))
                    ->live()
                    ->afterStateUpdated(fn () => $this->data['group_id'] = ''),
                Select::make('group_id')
                    ->label('Module')
                    ->options(function () {
                        $q = IndicatorGroup::where('is_active', true)->orderBy('sort_order');
                        if ($this->data['report_type_id'] ?? null) {
                            $q->where('report_type_id', $this->data['report_type_id']);
                        }

                        return $q->pluck('name', 'id')->prepend('All Modules', '');
                    })
                    ->live(),
                Select::make('category')
                    ->label('Category')
                    ->options([
                        '' => 'All Categories',
                        'process' => 'Process',
                        'output' => 'Output',
                        'outcome' => 'Outcome',
                        'satisfaction' => 'Satisfaction',
                    ])
                    ->live(),
                Select::make('indicator_type')
                    ->label('Indicator Type')
                    ->options([
                        '' => 'All Types',
                        'proportion' => 'Proportion',
                        'count' => 'Count',
                        'yes_no' => 'Yes / No',
                        'rate' => 'Rate',
                    ])
                    ->live(),
            ])
            ->columns(4)
            ->statePath('data');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Filtered indicators for the view
    // ──────────────────────────────────────────────────────────────────────────

    public function getFilteredGroups(): \Illuminate\Support\Collection
    {
        $d = $this->data;

        $query = Indicator::query()
            ->where('is_active', true)
            ->with(['group.reportType', 'children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->whereNull('parent_indicator_id') // top-level only
            ->orderBy('sort_order');

        if ($search = trim($d['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('short_name', 'like', "%{$search}%")
                ->orWhere('definition', 'like', "%{$search}%")
            );
        }

        if ($d['report_type_id'] ?? null) {
            $query->whereHas('group', fn ($q) => $q->where('report_type_id', $d['report_type_id']));
        }

        if ($d['group_id'] ?? null) {
            $query->where('group_id', $d['group_id']);
        }

        if ($d['category'] ?? null) {
            $query->where('category', $d['category']);
        }

        if ($d['indicator_type'] ?? null) {
            $query->where('indicator_type', $d['indicator_type']);
        }

        $indicators = $query->get();

        // Group by module
        return $indicators->groupBy(fn ($ind) => $ind->group_id)
            ->map(fn ($group) => [
                'group' => $group->first()->group,
                'indicators' => $group,
            ])
            ->values();
    }

    public function getViewData(): array
    {
        return [
            'groups' => $this->getFilteredGroups(),
            'totalCount' => $this->getFilteredGroups()->sum(fn ($g) => count($g['indicators'])),
        ];
    }
}

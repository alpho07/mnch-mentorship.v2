<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssessmentResource\Pages;
use App\Models\Assessment;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssessmentResource extends Resource
{
    protected static ?string $model = Assessment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Assessments';

    protected static ?string $navigationGroup = 'Facility Assessment';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_assessment');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_assessment');
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit($record): bool
    {
        return static::canAccess();
    }

    public static function canDelete($record): bool
    {
        return static::canAccess();
    }

    /**
     * Row-level scoping: super_admin/admin/division see every assessment;
     * everyone else (assessor and any other role) only sees assessments
     * they personally conducted. This also naturally blocks direct-URL
     * access to another assessor's record, since edit/view/dashboard pages
     * resolve records through this same scoped query.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && ! $user->hasRole(['super_admin', 'admin', 'division'])) {
            $query->where(function (Builder $query) use ($user) {
                $query->where('assessor_id', $user->id)
                    ->orWhere('created_by', $user->id)
                    ->orWhereHas('teamMembers', fn (Builder $team) => $team->where('users.id', $user->id));
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form; // Empty — pages define their own forms
    }

    public static function table(Table $table): Table
    {
        return $table; // Empty — ListAssessments defines its own table
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessments::route('/'),
            'create' => Pages\CreateAssessment::route('/create'),
            'dashboard' => Pages\AssessmentDashboard::route('/{record}/dashboard'),
            'edit-section' => Pages\EditSection::route('/{record}/section/{sectionCode}'),
            'edit-human-resources' => Pages\EditHumanResources::route('/{record}/human-resources'),
            'edit-health-products' => Pages\EditHealthProducts::route('/{record}/health-products'),
            'summary' => Pages\ViewAssessmentSummary::route('/{record}/summary'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->whereIn('status', ['draft', 'in_progress'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}

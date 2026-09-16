<?php

// app/Filament/Resources/AssessmentQuestionResource/Pages/ListAssessmentQuestions.php

namespace App\Filament\Resources\AssessmentQuestionResource\Pages;

use App\Filament\Resources\AssessmentQuestionResource;
use App\Models\AssessmentQuestion;
use App\Models\AssessmentSection;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAssessmentQuestions extends ListRecords {

    protected static string $resource = AssessmentQuestionResource::class;

    protected function getHeaderActions(): array {
        return [
                    Actions\CreateAction::make()
                    ->label('Add Question')
                    ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * One tab per section for fast navigation
     */
    public function getTabs(): array {
        $tabs = [
            'all' => Tab::make('All Questions')
                    ->badge(AssessmentQuestion::count()),
        ];

        $sections = AssessmentSection::where('is_active', true)
                ->orderBy('order')
                ->withCount('questions')
                ->get();

        foreach ($sections as $section) {
            $tabs[$section->code] = Tab::make($section->name)
                    ->modifyQueryUsing(fn(Builder $query) => $query->where('assessment_section_id', $section->id))
                    ->badge($section->questions_count)
                    ->badgeColor('primary');
        }

        $tabs['inactive'] = Tab::make('Inactive')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('is_active', false))
                ->badge(AssessmentQuestion::where('is_active', false)->count())
                ->badgeColor('danger');

        $tabs['conditional'] = Tab::make('Conditional')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNotNull('display_conditions'))
                ->badge(AssessmentQuestion::whereNotNull('display_conditions')->count())
                ->badgeColor('warning');

        return $tabs;
    }
}

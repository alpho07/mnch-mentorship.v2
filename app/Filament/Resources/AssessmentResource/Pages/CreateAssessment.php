<?php

namespace App\Filament\Resources\AssessmentResource\Pages;

use App\Filament\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\AssessmentType;
use App\Models\Facility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateAssessment extends CreateRecord
{
    protected static string $resource = AssessmentResource::class;

    private array $pendingTeamMemberIds = [];

    /**
     * Step 1 only — UI form schema
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Facility Information')
                    ->schema([
                        Forms\Components\Select::make('county_filter')
                            ->label('County')
                            ->options(\App\Models\County::pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->dehydrated(false),
                        Forms\Components\Select::make('facility_id')
                            ->label('Facility')
                            ->required()
                            ->searchable()
                            ->live()
                            ->options(function (Forms\Get $get) {
                                $countyId = $get('county_filter');
                                $query = Facility::query();

                                if ($countyId) {
                                    $query->whereHas('subcounty', function ($q) use ($countyId) {
                                        $q->where('county_id', $countyId);
                                    });
                                }

                                return $query->get()->mapWithKeys(function ($facility) {
                                    $label = ($facility->mfl_code ? "{$facility->mfl_code} - " : '').$facility->name;

                                    return [$facility->id => $label];
                                });
                            }),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Assessment Details')
                    ->schema([
                        Forms\Components\Select::make('category_filter')
                            ->label('Category')
                            ->helperText('Optional — narrows the Assessment list below to templates in this category.')
                            ->options(fn () => \App\Models\AssessmentTypeCategory::active()->ordered()->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->dehydrated(false),
                        Forms\Components\Select::make('assessment_type_id')
                            ->label('Assessment')
                            ->helperText('Pick the assessment template to use — its sections and questions load automatically.')
                            ->relationship(
                                name: 'assessmentType',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Forms\Get $get, $query) => $query
                                    ->where('is_active', true)
                                    ->when($get('category_filter'), fn ($q, $categoryId) => $q->where('category_id', $categoryId))
                                    ->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('round')
                            ->label('Assessment Round')
                            ->helperText('Which round of this template is this?')
                            ->options([
                                'baseline' => 'Baseline',
                                'midline' => 'Midline',
                                'endline' => 'Endline',
                                'other' => 'Other',
                            ])
                            ->default('baseline')
                            ->live()
                            ->required(),
                        Forms\Components\TextInput::make('round_label')
                            ->label('Specify Round')
                            ->helperText('Required when round is "Other" — e.g. "Post-COVID Re-assessment".')
                            ->maxLength(100)
                            ->visible(fn (Forms\Get $get) => $get('round') === 'other')
                            ->required(fn (Forms\Get $get) => $get('round') === 'other'),
                        Forms\Components\DatePicker::make('assessment_date')
                            ->label('Assessment Date')
                            ->default(now())
                            ->required(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Team Members')
                    ->description('Invite other assessors to help with this assessment now — you\'ll automatically be the lead assessor. You can also invite people later from the assessments list.')
                    ->schema([
                        Forms\Components\Select::make('member_ids')
                            ->label('Invite team members')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => app(\App\Services\AssessmentTeamService::class)
                                ->searchEligibleUsers(null, $search)
                                ->mapWithKeys(fn ($user) => [$user->id => "{$user->name} — {$user->email}"])
                                ->all())
                            ->getOptionLabelsUsing(fn (array $values): array => \App\Models\User::whereIn('id', $values)
                                ->get()
                                ->mapWithKeys(fn ($user) => [$user->id => "{$user->name} — {$user->email}"])
                                ->all())
                            ->helperText('Optional — search by name or email. Anyone added who isn\'t already an assessor will be granted the Assessor role.'),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Assessor Information')
                    ->description('Auto-populated from logged-in user')
                    ->schema([
                        Forms\Components\TextInput::make('assessor_name')
                            ->label('Assessor Name')
                            ->default(auth()->user()->name ?? '')
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\TextInput::make('assessor_contact')
                            ->label('Assessor Contact')
                            ->default(auth()->user()->email ?? auth()->user()->phone ?? '')
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Block duplicate assessments: one per facility, per template, per
     * round — baseline/midline/endline each allowed once; "other" rounds
     * are distinguished by their free-text round_label, so any number of
     * distinctly-labeled "other" assessments are allowed.
     */
    protected function beforeCreate(): void
    {
        $facilityId = $this->data['facility_id'] ?? null;
        $assessmentTypeId = $this->data['assessment_type_id'] ?? null;
        $round = $this->data['round'] ?? null;
        $roundLabel = $round === 'other' ? ($this->data['round_label'] ?? null) : null;

        if ($facilityId && $assessmentTypeId && $round) {
            $query = Assessment::where('facility_id', $facilityId)
                ->where('assessment_type_id', $assessmentTypeId)
                ->where('round', $round)
                ->whereNull('deleted_at');

            if ($round === 'other') {
                $query->where('round_label', $roundLabel);
            }

            if ($query->exists()) {
                $typeName = AssessmentType::find($assessmentTypeId)?->name ?? 'This';
                $roundName = $round === 'other' ? $roundLabel : ucfirst($round);
                Notification::make()
                    ->danger()
                    ->title('Duplicate Assessment')
                    ->body("A \"{$roundName}\" \"{$typeName}\" assessment already exists for this facility.")
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }
    }

    /**
     * Hook: Mutate data before save (add assessor_id and progress array)
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingTeamMemberIds = $data['member_ids'] ?? [];
        unset($data['member_ids']);

        $data['assessor_id'] = auth()->id();
        $data['status'] = 'in_progress';

        // Section progress initialization — built from the selected
        // template's own sections rather than a hardcoded 7-key list, so a
        // different template's section set is tracked correctly.
        // "facility_assessor" isn't a real assessment_sections row — it's
        // the synthetic first dashboard entry for this very step, always
        // marked complete once the record exists (see AssessmentDashboard).
        $sectionCodes = isset($data['assessment_type_id'])
            ? AssessmentType::find($data['assessment_type_id'])?->sections()->active()->pluck('code')->toArray() ?? []
            : [];

        $data['section_progress'] = array_merge(
            ['facility_assessor' => true],
            array_fill_keys($sectionCodes, false)
        );

        return $data;
    }

    /**
     * The record (and its auto-attached lead, via Assessment's `created`
     * event) exists by this point, so invited members can be attached now.
     */
    protected function afterCreate(): void
    {
        if ($this->pendingTeamMemberIds === []) {
            return;
        }

        app(\App\Services\AssessmentTeamService::class)
            ->addMembers($this->record, $this->pendingTeamMemberIds, auth()->id());

        Notification::make()
            ->success()
            ->title(count($this->pendingTeamMemberIds).' team member(s) invited')
            ->send();
    }

    /**
     * After saving Step 1 → redirect back to list
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Assessment created')
            ->body('Continue from the dashboard to complete all sections.');
    }
}

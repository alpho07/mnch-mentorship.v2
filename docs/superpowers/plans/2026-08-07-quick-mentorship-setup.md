# Quick Mentorship Setup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a fifth, additive mentorship-creation flow — `QuickMentorshipSetup` — that captures the same full pipeline (Training → first Class → Modules → Mentees → Invitations) as the Guided Wizard, on one continuous page with progressively-revealed sections instead of separate wizard steps, per `docs/superpowers/specs/2026-08-07-quick-mentorship-setup-design.md`.

**Architecture:** A new Filament `Page` (not `CreateRecord`, for the same reason `GuidedMentorshipSetup` isn't one — it creates four different model types across the session). Every section's data-mutating action is a thin wrapper delegating straight to `MentorshipWizardService`'s existing public methods — no new business logic anywhere. Section reveal + per-section "become required" both gate on the same page-property boolean flag (`$basicsSaved`, `$firstClassSaved`, etc.), the identical idiom this codebase already uses for EmONC-conditional fields (`->required(fn (Get $get) => ! $this->isEmoncProgram(...))` paired with the same closure on `->visible()`) — this is what lets `$this->form->getState()` validate only the reached sections, since Filament's form-state resolution skips hidden fields.

**Tech Stack:** Laravel 12, Filament v3 Forms (`Section`, inline `Actions`), Livewire `#[Url]` attributes, PHPUnit/Laravel test conventions matching this codebase.

## Global Constraints

- Zero changes to `CreateMentorshipTraining.php`, `GuidedMentorshipSetup.php`, `ChatMentorshipSetup.php`, `MnchGptSetup.php`, or `MentorshipWizardService.php` — all four existing flows and the shared service are stable dependencies, read-only for this plan.
- `PendingGuidedSetupNotice.php` gets exactly one approved edit (Task 8) — extending its existing `chat`/default ternary to a `match` with a `quick` branch, per spec §5. No other change to that file.
- No new business rules, no new persistence mechanism — every create/update/validate call goes through an existing `MentorshipWizardService` public method.
- Draft/resume state reuses the existing `guided_setup_draft` column and `saveWizardDraft()`/`clearWizardDraft()` methods — no new column, no new draft mechanism.
- `Setting::QUICK_SETUP_BUTTON_ENABLED` follows the exact naming/toggle pattern of the other three (`GUIDED_SETUP_BUTTON_ENABLED`, `CHAT_SETUP_BUTTON_ENABLED`, `MNCHGPT_BUTTON_ENABLED`).

---

### Task 1: Entry point — Setting, button, route, minimal page

**Files:**
- Modify: `app/Models/Setting.php` (add one constant)
- Modify: `app/Filament/Pages/MentorshipSettings.php` (add one toggle)
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php` (add one button)
- Modify: `app/Filament/Resources/MentorshipTrainingResource.php` (register one route)
- Create: `app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php`
- Create: `resources/views/filament/pages/quick-mentorship-setup.blade.php`
- Test: `tests/Feature/QuickMentorshipSetupTest.php`

**Interfaces:**
- Produces: `QuickMentorshipSetup` page class (extended by every later task), `Setting::QUICK_SETUP_BUTTON_ENABLED`, route `quick-setup`.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\ListMentorshipTrainings;
use App\Filament\Resources\MentorshipResource\Pages\QuickMentorshipSetup;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class QuickMentorshipSetupTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsCoordinator(): User
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'create_mentorship::training', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_any_mentorship::training', 'guard_name' => 'web']);
        $user->givePermissionTo(['create_mentorship::training', 'view_any_mentorship::training']);
        $this->actingAs($user);

        return $user;
    }

    public function test_list_page_shows_quick_setup_button(): void
    {
        $this->actingAsCoordinator();

        Livewire::test(ListMentorshipTrainings::class)
            ->assertSeeHtml('Quick Setup');
    }

    public function test_quick_setup_page_loads(): void
    {
        $this->actingAsCoordinator();

        Livewire::test(QuickMentorshipSetup::class)
            ->assertSuccessful();
    }

    public function test_page_is_blocked_when_the_setting_is_off_for_a_fresh_visit(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::QUICK_SETUP_BUTTON_ENABLED, false);

        $this->assertFalse(QuickMentorshipSetup::canAccess());
    }

    public function test_page_stays_accessible_with_a_training_query_param_even_when_off(): void
    {
        $this->actingAsCoordinator();
        Setting::setBool(Setting::QUICK_SETUP_BUTTON_ENABLED, false);
        request()->merge(['training' => 1]);

        $this->assertTrue(QuickMentorshipSetup::canAccess());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php`
Expected: FAIL — `QuickMentorshipSetup` class doesn't exist yet.

- [ ] **Step 3: Add the Setting constant**

In `app/Models/Setting.php`, next to the other three:

```php
    public const QUICK_SETUP_BUTTON_ENABLED = 'quick_setup_button_enabled';
```

- [ ] **Step 4: Create the minimal page class**

```php
<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\County;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Setting;
use App\Models\Training;
use App\Services\MentorshipWizardService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
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
```

(The `form()` schema and `mount()` body above are placeholders replaced by later tasks — this step only needs the page to exist and load. Every model import listed is used by later tasks, so it's included now to avoid repeated edits to the `use` block.)

- [ ] **Step 5: Create the blade view**

```blade
<x-filament-panels::page>
    @if ($completed)
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-lg font-semibold text-gray-950 dark:text-white">
                Mentorship "{{ $training?->title }}" created.
            </p>
            @if ($class)
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Class "{{ $class->name }}" has {{ $invitedCount }} mentee(s) invited.
                    @if ($classStarted)
                        The class is now <span class="font-semibold text-success-600 dark:text-success-400">active</span> — modules are open and mentors can begin.
                    @else
                        It's still saved as a draft — add modules and enroll mentees before it can start.
                    @endif
                </p>
            @endif
            <div class="mt-4 flex gap-3">
                @if ($class)
                    <a href="{{ \App\Filament\Resources\MentorshipTrainingResource::getUrl('classes', ['record' => $training->id]) }}"
                       class="fi-btn fi-btn-color-primary fi-btn-size-md fi-color-primary rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white">
                        Go to Class
                    </a>
                @endif
                <a href="{{ \App\Filament\Resources\MentorshipTrainingResource::getUrl('index') }}"
                   class="fi-btn fi-btn-color-gray fi-btn-size-md rounded-lg px-4 py-2 text-sm font-semibold ring-1 ring-gray-300 dark:ring-gray-700">
                    Back to Mentorships
                </a>
            </div>
        </div>
    @else
        {{ $this->form }}
    @endif
</x-filament-panels::page>
```

(`$invitedCount`/`$classStarted` are added to the page class in Task 6 — referencing them here now is safe since Blade only evaluates them when `$completed` is true, which can't happen until Task 6 exists.)

- [ ] **Step 6: Register the route**

In `app/Filament/Resources/MentorshipTrainingResource.php`'s `getPages()`, next to `'mnchgpt-setup'`:

```php
            'quick-setup' => Pages\QuickMentorshipSetup::route('/quick-setup'),
```

- [ ] **Step 7: Add the Settings page toggle**

In `app/Filament/Pages/MentorshipSettings.php`'s `mount()`, add to the `fill()` array:

```php
            'quick_setup_button_enabled' => Setting::getBool(Setting::QUICK_SETUP_BUTTON_ENABLED),
```

In its `form()`, inside the `Mentorship Creation Methods` section's schema, add a fifth toggle matching the other four exactly:

```php
                        Forms\Components\Toggle::make('quick_setup_button_enabled')
                            ->label('"Quick Setup" button')
                            ->helperText('The single-page, all-in-one form.')
                            ->onColor('success')
                            ->offColor('danger')
                            ->live()
                            ->afterStateUpdated(function (bool $state): void {
                                Setting::setBool(Setting::QUICK_SETUP_BUTTON_ENABLED, $state);
                                Notification::make()
                                    ->title($state ? 'Quick Setup enabled' : 'Quick Setup disabled')
                                    ->success()
                                    ->send();
                            }),
```

Change that section's `->columns(4)` to `->columns(5)` (5 toggles now, not 4).

- [ ] **Step 8: Add the list-page button**

In `app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php`'s `getHeaderActions()`, add:

```php
        $quickSetupEnabled = Setting::getBool(Setting::QUICK_SETUP_BUTTON_ENABLED);
```

next to the other three `$...Enabled` lines, and add a fifth action to the returned array:

```php
            Actions\Action::make('quick_setup')
                ->label('Quick Setup')
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('quick-setup'))
                ->disabled(! $quickSetupEnabled)
                ->tooltip($quickSetupEnabled ? null : 'Turned off in Mentorship Settings'),
```

- [ ] **Step 9: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php`
Expected: PASS on all 4.

- [ ] **Step 10: Run the existing Settings and list-page regression tests**

Run: `php artisan test tests/Feature/MentorshipSettingsTest.php tests/Feature/GuidedMentorshipSetupTest.php`
Expected: PASS (adding a 5th toggle/button doesn't change the other four's behavior).

- [ ] **Step 11: Commit**

```bash
git add app/Models/Setting.php app/Filament/Pages/MentorshipSettings.php app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php app/Filament/Resources/MentorshipTrainingResource.php app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php resources/views/filament/pages/quick-mentorship-setup.blade.php tests/Feature/QuickMentorshipSetupTest.php
git commit -m "feat: add Quick Setup entry point (setting, button, route, empty page)"
```

---

### Task 2: Basics section

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php`
- Test: `tests/Feature/QuickMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `MentorshipWizardService::createTraining()`, `::isEmoncProgram()`.
- Produces: `$basicsSaved: bool`, `createTraining(array $data): Training` (page method, mirrors `GuidedMentorshipSetup::createTraining()` exactly) — consumed by Task 7 (mount/resume).

- [ ] **Step 1: Write the failing test**

```php
    public function test_basics_continue_action_creates_training_and_reveals_first_class_section(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care']);
        $facility = \App\Models\Facility::factory()->create(['name' => 'Test Facility']);

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->fillForm([
            'is_pilot' => 0,
            'county_id' => $facility->subcounty->county_id,
            'facility_id' => $facility->id,
            'program_id' => $program->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'max_participants' => 10,
        ]);
        $component->call('saveBasics');

        $this->assertTrue($component->instance()->basicsSaved);
        $this->assertDatabaseHas('trainings', [
            'program_id' => $program->id,
            'facility_id' => $facility->id,
        ]);
        $this->assertSame('quick', \App\Models\Training::where('program_id', $program->id)->first()->guided_setup_method);
    }

    public function test_basics_continue_action_fails_validation_without_required_fields(): void
    {
        $this->actingAsCoordinator();

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->call('saveBasics');

        $component->assertHasFormErrors(['program_id']);
        $this->assertFalse($component->instance()->basicsSaved);
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter=test_basics`
Expected: FAIL — `saveBasics` doesn't exist, `basicsSaved` doesn't exist.

- [ ] **Step 3: Replace the placeholder form schema and add the Basics section**

In `QuickMentorshipSetup.php`, add the property:

```php
    public bool $basicsSaved = false;
```

Replace the `form()` method's placeholder schema with:

```php
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basics')
                    ->description('Run type, location, program, and schedule.')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        Forms\Components\Radio::make('is_pilot')
                            ->label('')
                            ->options([0 => 'Live Mentorship', 1 => 'Pilot Run'])
                            ->descriptions([
                                0 => 'Counts in dashboards, KPI badges, and analytics reports.',
                                1 => 'Excluded from all counts, badges, and analytics. Use for testing.',
                            ])
                            ->default(0)
                            ->required()
                            ->inline(false),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('county_id')
                                ->label('County')
                                ->options(fn () => County::orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Forms\Set $set) => $set('facility_id', null))
                                ->prefixIcon('heroicon-o-map'),
                            Forms\Components\Select::make('facility_id')
                                ->label('Facility')
                                ->options(function (Get $get) {
                                    $countyId = $get('county_id');
                                    if (! $countyId) {
                                        return [];
                                    }

                                    return Facility::whereHas('subcounty', fn ($q) => $q->where('county_id', $countyId))
                                        ->get()
                                        ->mapWithKeys(fn ($f) => [$f->id => "{$f->mfl_code} — {$f->name}"]);
                                })
                                ->searchable()
                                ->required()
                                ->disabled(fn (Get $get) => ! $get('county_id'))
                                ->prefixIcon('heroicon-o-building-office-2'),
                        ]),
                        \App\Filament\Forms\Components\ProgramPicker::make('program_id')
                            ->label('Mentorship Program')
                            ->helperText('Tap a programme card to select it.')
                            ->required()
                            ->validationMessages([
                                'required' => 'Please pick a programme card.',
                            ])
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                $program = $value ? Program::find($value) : null;

                                if ($program && ! $program->isSelectableBy(auth()->user())) {
                                    $fail('That program is not active — pick a different one.');
                                }
                            })
                            ->columnSpanFull(),
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\DatePicker::make('start_date')
                                ->label('Start Date')
                                ->required(fn (Get $get) => ! $this->isEmoncProgram($get('program_id')))
                                ->visible(fn (Get $get) => ! $this->isEmoncProgram($get('program_id')))
                                ->native(false)
                                ->minDate(today())
                                ->displayFormat('M j, Y'),
                            Forms\Components\DatePicker::make('end_date')
                                ->label('End Date')
                                ->required(fn (Get $get) => ! $this->isEmoncProgram($get('program_id')))
                                ->visible(fn (Get $get) => ! $this->isEmoncProgram($get('program_id')))
                                ->native(false)
                                ->minDate(fn (Get $get) => $get('start_date') ?? now())
                                ->afterOrEqual('start_date')
                                ->displayFormat('M j, Y'),
                            Forms\Components\TextInput::make('max_participants')
                                ->label('Number of Mentees')
                                ->numeric()
                                ->default(10)
                                ->minValue(2)
                                ->maxValue(10)
                                ->suffix('mentees'),
                        ]),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('continue_basics')
                                ->label('Continue')
                                ->action('saveBasics'),
                        ])->visible(fn () => ! $this->basicsSaved),
                    ]),
            ])
            ->statePath('data');
    }

    public function saveBasics(): void
    {
        $state = $this->form->getState();

        $this->createTraining([
            'is_pilot' => $state['is_pilot'],
            'county_id' => $state['county_id'],
            'facility_id' => $state['facility_id'],
            'program_id' => $state['program_id'],
            'start_date' => $state['start_date'] ?? null,
            'end_date' => $state['end_date'] ?? null,
            'max_participants' => $state['max_participants'],
        ]);

        $this->training->update(['guided_setup_method' => 'quick']);
        $this->basicsSaved = true;
    }

    public function createTraining(array $data): Training
    {
        $this->training = app(MentorshipWizardService::class)->createTraining($data, $this->training);
        $this->trainingId = $this->training->id;

        return $this->training;
    }

    private function isEmoncProgram(?int $programId): bool
    {
        return app(MentorshipWizardService::class)->isEmoncProgram($programId);
    }
```

(`Forms\Components\Actions::make([...])->visible(fn () => ! $this->basicsSaved)` hides the Continue button once the section is done — same idea used for the Wizard's `reveal_new_mentee_form` action toggling on a boolean.)

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter=test_basics`
Expected: PASS on both.

- [ ] **Step 5: Run the full file plus Task 1's tests**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php`
Expected: PASS on all 6.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php tests/Feature/QuickMentorshipSetupTest.php
git commit -m "feat: add Basics section to Quick Setup"
```

---

### Task 3: First Class section

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php`
- Test: `tests/Feature/QuickMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `MentorshipWizardService::createFirstClass()`, `$this->basicsSaved`, `$this->training`.
- Produces: `$firstClassSaved: bool`, `createFirstClass(array $data): MentorshipClass` — consumed by Task 7.

- [ ] **Step 1: Write the failing test**

```php
    public function test_first_class_continue_action_creates_class_and_reveals_modules_section(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->trainingId = $training->id;
        $component->instance()->basicsSaved = true;
        $component->fillForm([
            'class_name' => 'January 2027 Cohort',
            'class_start_date' => now()->addDay()->toDateString(),
            'class_end_date' => now()->addMonth()->toDateString(),
        ]);
        $component->call('saveFirstClass');

        $this->assertTrue($component->instance()->firstClassSaved);
        $this->assertDatabaseHas('mentorship_classes', [
            'training_id' => $training->id,
            'name' => 'January 2027 Cohort',
        ]);
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter=test_first_class`
Expected: FAIL — `saveFirstClass` doesn't exist.

- [ ] **Step 3: Add the First Class section**

Add the property:

```php
    public bool $firstClassSaved = false;
```

Add a new `Section` to the `form()` schema array, immediately after the Basics section:

```php
                Forms\Components\Section::make('First Class')
                    ->description("Let's create your first class or cohort.")
                    ->icon('heroicon-o-user-group')
                    ->visible(fn () => $this->basicsSaved)
                    ->schema([
                        Forms\Components\TextInput::make('class_name')
                            ->label('Class/Cohort Name')
                            ->required(fn () => $this->basicsSaved)
                            ->placeholder('e.g., January 2027 Cohort')
                            ->maxLength(255),
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\DatePicker::make('class_start_date')
                                ->label('Start Date')
                                ->required(fn () => $this->basicsSaved && ! $this->isEmoncProgram($this->training?->program_id))
                                ->visible(fn () => ! $this->isEmoncProgram($this->training?->program_id))
                                ->native(false)
                                ->minDate(fn () => $this->training?->start_date)
                                ->maxDate(fn () => $this->training?->end_date),
                            Forms\Components\DatePicker::make('class_end_date')
                                ->label('End Date')
                                ->required(fn () => $this->basicsSaved && ! $this->isEmoncProgram($this->training?->program_id))
                                ->visible(fn () => ! $this->isEmoncProgram($this->training?->program_id))
                                ->native(false)
                                ->minDate(fn (Get $get) => $get('class_start_date') ?: $this->training?->start_date)
                                ->maxDate(fn () => $this->training?->end_date)
                                ->afterOrEqual('class_start_date'),
                        ]),
                        Forms\Components\Textarea::make('class_description')
                            ->label('Description')
                            ->rows(3)
                            ->placeholder('Describe the gap identified and how this class will be delivered.'),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('continue_first_class')
                                ->label('Continue')
                                ->action('saveFirstClass'),
                        ])->visible(fn () => ! $this->firstClassSaved),
                    ]),
```

Add the methods:

```php
    public function saveFirstClass(): void
    {
        $state = $this->form->getState();

        $this->createFirstClass([
            'name' => $state['class_name'],
            'start_date' => $state['class_start_date'] ?? null,
            'end_date' => $state['class_end_date'] ?? null,
            'description' => $state['class_description'] ?? null,
        ]);

        $this->firstClassSaved = true;
    }

    public function createFirstClass(array $data): MentorshipClass
    {
        $this->class = app(MentorshipWizardService::class)->createFirstClass($data, $this->training, $this->class);
        $this->classId = $this->class->id;

        return $this->class;
    }
```

(Note: `class_name`'s `->required(fn () => $this->basicsSaved)` — not unconditionally `->required()` — because the field must not block validation while the section is still hidden, matching the same "required only once revealed" idiom as the EmONC-conditional fields.)

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter=test_first_class`
Expected: PASS.

- [ ] **Step 5: Run the full file**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php`
Expected: PASS on all 7.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php tests/Feature/QuickMentorshipSetupTest.php
git commit -m "feat: add First Class section to Quick Setup"
```

---

### Task 4: Modules section

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php`
- Test: `tests/Feature/QuickMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `MentorshipWizardService::assignModules()`, `::validateModuleDates()`, `::saveWizardDraft()`, `::clearWizardDraft()`, `$this->firstClassSaved`.
- Produces: `$modulesSaved: bool`, `$moduleDates: array`, `assignModules(array $data): int`, `validateModuleDates(array $moduleIds): ?string`, `updatedModuleDates(): void` — consumed by Task 7.

- [ ] **Step 1: Write the failing tests**

```php
    public function test_modules_continue_action_assigns_modules_and_reveals_mentees_section(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care']);
        $training = \App\Models\Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $programModule = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->trainingId = $training->id;
        $component->instance()->class = $class;
        $component->instance()->classId = $class->id;
        $component->instance()->basicsSaved = true;
        $component->instance()->firstClassSaved = true;
        $component->fillForm(['module_ids' => [$programModule->id]]);
        $component->call('saveModules');

        $this->assertTrue($component->instance()->modulesSaved);
        $this->assertDatabaseHas('class_modules', [
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
        ]);
    }

    public function test_validate_module_dates_delegates_to_the_shared_service(): void
    {
        $this->actingAsCoordinator();

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->instance()->moduleDates = [];

        $error = $component->instance()->validateModuleDates([56]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('Set a start and end date', $error);
    }

    public function test_updated_module_dates_hook_persists_to_the_draft(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->moduleDates = [56 => ['start' => '2027-03-01', 'end' => '2027-03-10']];
        $component->instance()->updatedModuleDates();

        $this->assertSame(
            [56 => ['start' => '2027-03-01', 'end' => '2027-03-10']],
            $training->fresh()->guided_setup_draft['moduleDates']
        );
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter=test_modules_continue`
Expected: FAIL — `saveModules` doesn't exist.

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter="test_validate_module_dates|test_updated_module_dates"`
Expected: FAIL — `validateModuleDates`/`moduleDates`/`updatedModuleDates` don't exist.

- [ ] **Step 3: Add the Modules section**

Add properties:

```php
    public bool $modulesSaved = false;

    public array $moduleDates = [];

    public function updatedModuleDates(): void
    {
        $this->saveWizardDraft('moduleDates', $this->moduleDates);
    }
```

Add a new `Section` after First Class:

```php
                Forms\Components\Section::make('Modules')
                    ->description("Pick as many or as few modules as you like — you can add more later.")
                    ->icon('heroicon-o-book-open')
                    ->visible(fn () => $this->firstClassSaved)
                    ->schema(function () {
                        if (! $this->training || ! $this->class) {
                            return [];
                        }

                        if ($this->isEmoncProgram($this->training->program_id)) {
                            $picker = \App\Filament\Forms\Components\EmoncModulePicker::make('module_ids')
                                ->label('Available Program Modules')
                                ->training($this->training)
                                ->class($this->class)
                                ->includeAssigned()
                                ->live()
                                ->afterStateUpdated(fn ($state) => $this->saveWizardDraft('module_ids', $state))
                                ->helperText('Already-added modules/tracks are pre-checked — uncheck one to remove it.')
                                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                    if ($error = $this->validateModuleDates($value ?? [])) {
                                        $fail($error);
                                    }
                                });
                        } else {
                            $allModules = ProgramModule::where('program_id', $this->training->program_id)
                                ->where('is_active', true)
                                ->whereNull('parent_id')
                                ->orderBy('order_sequence')
                                ->pluck('name', 'id')
                                ->toArray();

                            $picker = \App\Filament\Forms\Components\CardCheckboxList::make('module_ids')
                                ->label('Available Program Modules')
                                ->options($allModules)
                                ->default([])
                                ->live()
                                ->afterStateUpdated(fn ($state) => $this->saveWizardDraft('module_ids', $state))
                                ->helperText('Already-added modules are pre-checked — uncheck one to remove it.');
                        }

                        return [
                            $picker,
                            Forms\Components\Toggle::make('auto_create_sessions')
                                ->label('Auto-populate sessions from program template')
                                ->default(true)
                                ->disabled()
                                ->dehydrated(true),
                            Forms\Components\Actions::make([
                                Forms\Components\Actions\Action::make('continue_modules')
                                    ->label('Continue')
                                    ->action('saveModules'),
                            ])->visible(fn () => ! $this->modulesSaved),
                        ];
                    }),
```

Add the methods:

```php
    public function saveModules(): void
    {
        $state = $this->form->getState();

        $this->assignModules([
            'module_ids' => $state['module_ids'] ?? [],
            'auto_create_sessions' => $state['auto_create_sessions'] ?? true,
            'module_dates' => $this->moduleDates,
        ]);

        $this->modulesSaved = true;
    }

    public function assignModules(array $data): int
    {
        $created = app(MentorshipWizardService::class)->assignModules($data, $this->training, $this->class);
        $this->moduleDates = [];

        return $created;
    }

    public function validateModuleDates(array $moduleIds): ?string
    {
        return app(MentorshipWizardService::class)->validateModuleDates($moduleIds, $this->moduleDates);
    }

    private function saveWizardDraft(string $key, mixed $state): void
    {
        if (! $this->training) {
            return;
        }

        app(MentorshipWizardService::class)->saveWizardDraft($this->training, $key, $state);
    }

    private function clearWizardDraft(string $key): void
    {
        if (! $this->training) {
            return;
        }

        app(MentorshipWizardService::class)->clearWizardDraft($this->training, $key);
    }
```

Add `use App\Models\ProgramModule;` to the top of the file.

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter="test_modules_continue|test_validate_module_dates|test_updated_module_dates"`
Expected: PASS on all 3.

- [ ] **Step 5: Run the full file**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php`
Expected: PASS on all 10.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php tests/Feature/QuickMentorshipSetupTest.php
git commit -m "feat: add Modules section to Quick Setup"
```

---

### Task 5: Mentees section

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php`
- Test: `tests/Feature/QuickMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `MentorshipWizardService::enrollMentees()`, `::menteeOptions()`, `$this->modulesSaved`.
- Produces: `$menteesSaved: bool`, `$enrolledCount: int`, `enrollMentees(array $data): int` — consumed by Task 7.

- [ ] **Step 1: Write the failing test**

```php
    public function test_mentees_continue_action_enrolls_selected_users_and_reveals_invite_section(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $mentee = User::factory()->create();

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->trainingId = $training->id;
        $component->instance()->class = $class;
        $component->instance()->classId = $class->id;
        $component->instance()->basicsSaved = true;
        $component->instance()->firstClassSaved = true;
        $component->instance()->modulesSaved = true;
        $component->fillForm(['selected_users' => [$mentee->id]]);
        $component->call('saveMentees');

        $this->assertTrue($component->instance()->menteesSaved);
        $this->assertSame(1, $component->instance()->enrolledCount);
        $this->assertDatabaseHas('class_participants', [
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
        ]);
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter=test_mentees_continue`
Expected: FAIL — `saveMentees` doesn't exist.

- [ ] **Step 3: Add the Mentees section**

Add properties:

```php
    public bool $menteesSaved = false;

    public int $enrolledCount = 0;
```

Add a new `Section` after Modules:

```php
                Forms\Components\Section::make('Mentees')
                    ->description('Who will be mentored in this class? You can skip this and enroll mentees later.')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn () => $this->modulesSaved)
                    ->schema([
                        Forms\Components\Hidden::make('mentee_page')->default(1),
                        Forms\Components\Hidden::make('show_new_mentee_form')->default(false),
                        Forms\Components\TextInput::make('mentee_search')
                            ->label('Search')
                            ->placeholder('Search by name, phone, email, or facility...')
                            ->live(debounce: 400)
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('mentee_page', 1))
                            ->prefixIcon('heroicon-o-magnifying-glass'),
                        \App\Filament\Forms\Components\CardCheckboxList::make('selected_users')
                            ->label('Existing Users')
                            ->options(fn (Get $get) => app(MentorshipWizardService::class)->menteeOptions(
                                $get('mentee_search'),
                                (int) ($get('mentee_page') ?? 1),
                                collect($get('selected_users') ?? [])->map(fn ($id) => (int) $id)->all()
                            ))
                            ->maxSelections(fn () => $this->training?->max_participants)
                            ->default([])
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->saveWizardDraft('selected_users', $state))
                            ->columnSpanFull()
                            ->helperText('Search and check existing users to enroll.'),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('mentee_previous')
                                ->label('Previous Page')
                                ->color('gray')
                                ->action(fn (Forms\Set $set, Get $get) => $set('mentee_page', max(1, (int) $get('mentee_page') - 1))),
                            Forms\Components\Actions\Action::make('mentee_next')
                                ->label('Next Page')
                                ->color('gray')
                                ->action(fn (Forms\Set $set, Get $get) => $set('mentee_page', (int) $get('mentee_page') + 1)),
                        ])->columnSpanFull(),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('reveal_new_mentee_form')
                                ->label(fn (Get $get) => trim((string) $get('mentee_search')) !== ''
                                    ? "Mentee \"{$get('mentee_search')}\" not found — click here to add"
                                    : '+ Add a new mentee')
                                ->icon('heroicon-o-user-plus')
                                ->color(fn (Get $get) => trim((string) $get('mentee_search')) !== '' ? 'warning' : 'gray')
                                ->action(function (Forms\Set $set, Get $get) {
                                    $set('show_new_mentee_form', true);

                                    $search = trim((string) $get('mentee_search'));
                                    if ($search === '') {
                                        return;
                                    }

                                    if (str_contains($search, '@')) {
                                        $set('new_mentee.email', $search);

                                        return;
                                    }

                                    $parts = preg_split('/\s+/', $search, 2);
                                    $set('new_mentee.first_name', $parts[0] ?? null);
                                    $set('new_mentee.last_name', $parts[1] ?? null);
                                }),
                        ])
                            ->visible(fn (Get $get) => ! $get('show_new_mentee_form'))
                            ->columnSpanFull(),
                        Forms\Components\Fieldset::make('Or Add a New Mentee')
                            ->visible(fn (Get $get) => (bool) $get('show_new_mentee_form'))
                            ->schema([
                                Forms\Components\TextInput::make('new_mentee.email')
                                    ->label('Email Address')
                                    ->email(),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('new_mentee.first_name')
                                        ->label('First Name')
                                        ->requiredWith('new_mentee.email'),
                                    Forms\Components\TextInput::make('new_mentee.last_name')
                                        ->label('Last Name')
                                        ->requiredWith('new_mentee.email'),
                                ]),
                                Forms\Components\TextInput::make('new_mentee.phone')
                                    ->label('Phone')
                                    ->tel(),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('new_mentee.cadre_id')
                                        ->label('Cadre')
                                        ->options(Cadre::orderBy('name')->pluck('name', 'id')),
                                    Forms\Components\Select::make('new_mentee.department_id')
                                        ->label('Department')
                                        ->options(Department::orderBy('name')->pluck('name', 'id')),
                                ]),
                                Forms\Components\Select::make('new_mentee.facility_id')
                                    ->label('Facility')
                                    ->options(fn () => Facility::orderBy('name')
                                        ->limit(200)
                                        ->get()
                                        ->mapWithKeys(fn ($f) => [$f->id => "{$f->mfl_code} - {$f->name}"])),
                            ]),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('continue_mentees')
                                ->label('Continue')
                                ->action('saveMentees'),
                        ])->visible(fn () => ! $this->menteesSaved),
                    ]),
```

Add the methods:

```php
    public function saveMentees(): void
    {
        $state = $this->form->getState();

        $this->enrollMentees([
            'selected_users' => $state['selected_users'] ?? [],
            'new_mentee' => ($state['new_mentee']['email'] ?? null) ? $state['new_mentee'] : null,
        ]);

        $this->menteesSaved = true;
    }

    public function enrollMentees(array $data): int
    {
        $count = app(MentorshipWizardService::class)->enrollMentees($data, $this->class);
        $this->enrolledCount = $count;

        return $count;
    }
```

Add `use App\Models\Cadre;` and `use App\Models\Department;` to the top of the file.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter=test_mentees_continue`
Expected: PASS.

- [ ] **Step 5: Run the full file**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php`
Expected: PASS on all 11.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php tests/Feature/QuickMentorshipSetupTest.php
git commit -m "feat: add Mentees section to Quick Setup"
```

---

### Task 6: Invite section + completion

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php`
- Test: `tests/Feature/QuickMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `MentorshipWizardService::sendInvitations()`, `$this->menteesSaved`.
- Produces: `$invitedCount: int`, `$classStarted: bool`, `sendInvitations(array $data): array`, `submit(): void` — consumed by Task 9's end-to-end test.

- [ ] **Step 1: Write the failing test**

```php
    public function test_submit_sends_invitations_and_marks_completed(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $mentee = User::factory()->create(['email' => 'mentee@example.com']);
        \App\Models\ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'enrolled',
        ]);

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;
        $component->instance()->basicsSaved = true;
        $component->instance()->firstClassSaved = true;
        $component->instance()->modulesSaved = true;
        $component->instance()->menteesSaved = true;
        $component->fillForm(['recipients' => 'all']);
        $component->call('submit');

        $this->assertTrue($component->instance()->completed);
        $this->assertSame(1, $component->instance()->invitedCount);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\MenteeEnrollmentInvitationMail::class, 1);
        $this->assertNotNull($training->fresh()->guided_setup_completed_at);
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter=test_submit_sends_invitations`
Expected: FAIL — `submit`/`invitedCount`/`completed` don't exist as expected (the placeholder `$completed`/`submit` from Task 1's minimal page don't wire to the real service yet).

- [ ] **Step 3: Add the Invite section and submit logic**

Add properties:

```php
    public int $invitedCount = 0;

    public bool $classStarted = false;
```

Add a final `Section` after Mentees:

```php
                Forms\Components\Section::make('Invite')
                    ->description('Time to invite your mentees!')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn () => $this->menteesSaved)
                    ->schema([
                        Forms\Components\Radio::make('recipients')
                            ->label('Who should receive the email?')
                            ->options([
                                'all' => 'All mentees with email addresses',
                                'not_sent' => 'Only those not yet invited',
                            ])
                            ->default('all')
                            ->required(fn () => $this->menteesSaved),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('submit')
                                ->label('Create Mentorship')
                                ->color('primary')
                                ->action('submit'),
                        ]),
                    ]),
```

Add the methods:

```php
    public function submit(): void
    {
        $state = $this->form->getState();

        try {
            $this->sendInvitations(['recipients' => $state['recipients'] ?? 'all']);
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Something Went Wrong')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function sendInvitations(array $data): array
    {
        $result = app(MentorshipWizardService::class)->sendInvitations($data, $this->training, $this->class);

        $this->invitedCount = $result['sent'] + $result['resent'];
        $this->completed = true;

        if ($this->class->fresh()->status === 'active') {
            $this->classStarted = true;
        }

        return $result;
    }
```

Add `use Filament\Notifications\Notification;` to the top of the file.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter=test_submit_sends_invitations`
Expected: PASS.

- [ ] **Step 5: Run the full file**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php`
Expected: PASS on all 12.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php tests/Feature/QuickMentorshipSetupTest.php
git commit -m "feat: add Invite section and submit to Quick Setup"
```

---

### Task 7: Resume/draft restoration in mount()

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php`
- Test: `tests/Feature/QuickMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `$this->trainingId`, `$this->classId`, `Training::guided_setup_draft`.
- Produces: fully resumable `mount()` — no new interface consumed elsewhere; this is the terminal integration point tying Tasks 2-6 together for the refresh/resume scenario.

- [ ] **Step 1: Write the failing tests**

```php
    public function test_mount_resumes_a_training_in_progress_and_reveals_the_right_sections(): void
    {
        $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care']);
        $training = \App\Models\Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->instance()->trainingId = $training->id;
        $component->instance()->classId = $class->id;
        $component->instance()->mount();

        $this->assertTrue($component->instance()->basicsSaved);
        $this->assertTrue($component->instance()->firstClassSaved);
        $component->assertFormSet([
            'program_id' => $program->id,
            'class_name' => $class->name,
        ]);
    }

    public function test_mount_restores_module_and_mentee_picks_from_the_training_draft(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create([
            'guided_setup_draft' => [
                'module_ids' => [39, 41],
                'selected_users' => [100],
            ],
        ]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);

        $component = Livewire::test(QuickMentorshipSetup::class);
        $component->instance()->trainingId = $training->id;
        $component->instance()->classId = $class->id;
        $component->instance()->mount();

        $component->assertFormSet([
            'module_ids' => [39, 41],
            'selected_users' => [100],
        ]);
    }

    public function test_mount_with_no_training_id_leaves_only_basics_visible(): void
    {
        $this->actingAsCoordinator();

        $component = Livewire::test(QuickMentorshipSetup::class);

        $this->assertFalse($component->instance()->basicsSaved);
        $this->assertFalse($component->instance()->firstClassSaved);
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter=test_mount`
Expected: FAIL — `mount()` (still the Task 1 placeholder) doesn't restore anything, so `basicsSaved`/`firstClassSaved` stay false and the form isn't seeded.

- [ ] **Step 3: Replace `mount()`**

```php
    public function mount(): void
    {
        $fill = [
            'module_ids' => [],
            'selected_users' => [],
            'auto_create_sessions' => true,
        ];

        if ($this->trainingId) {
            $this->training = Training::find($this->trainingId);

            if ($this->training) {
                $this->basicsSaved = true;
                $fill['is_pilot'] = (int) $this->training->is_pilot;
                $fill['county_id'] = $this->training->county_id;
                $fill['facility_id'] = $this->training->facility_id;
                $fill['program_id'] = $this->training->program_id;
                $fill['start_date'] = $this->training->start_date;
                $fill['end_date'] = $this->training->end_date;
                $fill['max_participants'] = $this->training->max_participants;

                $this->moduleDates = $this->training->guided_setup_draft['moduleDates'] ?? [];
            }
        }

        if ($this->classId) {
            $this->class = MentorshipClass::find($this->classId);

            if ($this->class) {
                $this->firstClassSaved = true;
                $fill['class_name'] = $this->class->name;
                $fill['class_start_date'] = $this->class->start_date;
                $fill['class_end_date'] = $this->class->end_date;
                $fill['class_description'] = $this->class->description;

                $draft = $this->training->guided_setup_draft ?? [];

                $fill['module_ids'] = array_key_exists('module_ids', $draft)
                    ? $draft['module_ids']
                    : $this->class->classModules()->pluck('program_module_id')->toArray();

                $fill['selected_users'] = array_key_exists('selected_users', $draft)
                    ? $draft['selected_users']
                    : $this->class->participants()->pluck('user_id')->toArray();

                $this->modulesSaved = $this->class->classModules()->exists()
                    || array_key_exists('module_ids', $draft);
                $this->menteesSaved = $this->class->participants()->exists()
                    || array_key_exists('selected_users', $draft);
            }
        }

        $this->form->fill($fill);
    }
```

(`$modulesSaved`/`$menteesSaved` on resume are inferred from whether real records or a draft key already exist — mirrors the same "draft is authoritative once it exists, otherwise fall back to real assignments" rule `GuidedMentorshipSetup::mount()` already uses for `module_ids`/`selected_users` themselves.)

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter=test_mount`
Expected: PASS on all 3.

- [ ] **Step 5: Run the full file**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php`
Expected: PASS on all 15.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/QuickMentorshipSetup.php tests/Feature/QuickMentorshipSetupTest.php
git commit -m "feat: resume Quick Setup sessions from training/class IDs and draft state"
```

---

### Task 8: Resume-banner integration

**Files:**
- Modify: `app/Filament/Widgets/PendingGuidedSetupNotice.php`
- Test: `tests/Feature/QuickMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `Training::guided_setup_method`.

- [ ] **Step 1: Write the failing test**

```php
    public function test_abandoned_quick_setup_draft_continue_link_points_to_quick_setup(): void
    {
        $mentor = $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create([
            'mentor_id' => $mentor->id,
            'guided_setup_completed_at' => null,
            'guided_setup_method' => 'quick',
        ]);

        $viewData = (new \ReflectionMethod(\App\Filament\Widgets\PendingGuidedSetupNotice::class, 'getViewData'))
            ->invoke(new \App\Filament\Widgets\PendingGuidedSetupNotice);

        $this->assertStringContainsString('quick-setup', $viewData['continueUrl']);
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter=test_abandoned_quick_setup`
Expected: FAIL — the route key still resolves to `guided-setup` for a `'quick'` method (the ternary only special-cases `'chat'`).

- [ ] **Step 3: Update the route-key logic**

In `app/Filament/Widgets/PendingGuidedSetupNotice.php`'s `getViewData()`, replace:

```php
        $routeKey = $training->guided_setup_method === 'chat' ? 'chat-setup' : 'guided-setup';
```

with:

```php
        $routeKey = match ($training->guided_setup_method) {
            'chat' => 'chat-setup',
            'quick' => 'quick-setup',
            default => 'guided-setup',
        };
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter=test_abandoned_quick_setup`
Expected: PASS.

- [ ] **Step 5: Run the full file plus the existing widget's own coverage**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php tests/Feature/GuidedMentorshipSetupTest.php`
Expected: PASS on everything, including `test_send_invitations_discards_the_same_mentors_other_abandoned_drafts` (unaffected — still defaults through the `'guided-setup'` branch for non-`'quick'`, non-`'chat'` methods).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Widgets/PendingGuidedSetupNotice.php tests/Feature/QuickMentorshipSetupTest.php
git commit -m "feat: point the pending-setup banner's Continue link at quick-setup for Quick Setup drafts"
```

---

### Task 9: End-to-end test and full regression

**Files:**
- Test: `tests/Feature/QuickMentorshipSetupTest.php`

**Interfaces:**
- Consumes: every method built in Tasks 1-8, exercised together in one real HTTP-driven run.

- [ ] **Step 1: Write the end-to-end test**

```php
    public function test_end_to_end_quick_setup_creates_a_fully_configured_mentorship(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $mentor = $this->actingAsCoordinator();
        $program = \App\Models\Program::factory()->create(['name' => 'Newborn Care']);
        $facility = \App\Models\Facility::factory()->create();
        $programModule = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);
        $mentee = User::factory()->create(['email' => 'endtoend@example.com']);

        $component = Livewire::test(QuickMentorshipSetup::class);

        $component->fillForm([
            'is_pilot' => 0,
            'county_id' => $facility->subcounty->county_id,
            'facility_id' => $facility->id,
            'program_id' => $program->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'max_participants' => 10,
        ]);
        $component->call('saveBasics');

        $component->fillForm(['class_name' => 'End to End Cohort']);
        $component->call('saveFirstClass');

        $component->fillForm(['module_ids' => [$programModule->id]]);
        $component->call('saveModules');

        $component->fillForm(['selected_users' => [$mentee->id]]);
        $component->call('saveMentees');

        $component->fillForm(['recipients' => 'all']);
        $component->call('submit');

        $training = \App\Models\Training::where('program_id', $program->id)->first();
        $this->assertNotNull($training);
        $this->assertSame('quick', $training->guided_setup_method);
        $this->assertNotNull($training->guided_setup_completed_at);
        $this->assertNull($training->guided_setup_draft);

        $class = \App\Models\MentorshipClass::where('training_id', $training->id)->first();
        $this->assertSame('End to End Cohort', $class->name);
        $this->assertSame('active', $class->status);

        $this->assertDatabaseHas('class_modules', [
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
        ]);
        $this->assertDatabaseHas('class_participants', [
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
        ]);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\MenteeEnrollmentInvitationMail::class, 1);
    }
```

- [ ] **Step 2: Run it to verify it passes**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php --filter=test_end_to_end`
Expected: PASS — every piece built in Tasks 1-8 already works individually, so this should pass on the first run; if it doesn't, the failure points at an integration gap between sections (e.g. a section's `visible()` condition not matching its actual save-flag name) — fix that gap, not the test.

- [ ] **Step 3: Run the complete Quick Setup test file**

Run: `php artisan test tests/Feature/QuickMentorshipSetupTest.php`
Expected: PASS on all 16 tests.

- [ ] **Step 4: Run the full project test suite**

Run: `php artisan test`
Expected: PASS (0 failures; pre-existing "risky" warnings are the documented cosmetic artifact from bootstrapping a second Laravel Application instance in static test data providers, not real failures).

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/QuickMentorshipSetupTest.php
git commit -m "test: add end-to-end coverage for the full Quick Setup pipeline"
```

---

## Deferred / not in this plan

- Retiring any of the four existing flows via their Settings toggles — your decision, made later, once Quick Setup has proven itself in real use.
- Any change to `MentorshipWizardService`, or to `CreateMentorshipTraining`/`GuidedMentorshipSetup`/`ChatMentorshipSetup`/`MnchGptSetup` themselves.
- Visual/UX polish beyond functional parity (e.g. a progress indicator showing which sections are done) — not requested, can be a follow-up once the flow is live.

## Self-Review

**Spec coverage:** §1 (new page + route) → Task 1. §2 (Setting + button) → Task 1. §3 (five sections) → Tasks 2-6, one section per task, each independently testable per the spec's own ordering. §4 (autosave/resume) → Task 7. §5 (resume banner) → Task 8. §6 (explicitly not built) → enforced by the Global Constraints section and untouched by every task.

**Placeholder scan:** No TBD/TODO. Task 1's placeholder `form()`/`mount()` bodies are explicitly labeled as intentionally temporary and are each fully replaced by name in Tasks 2 and 7 respectively — not unwritten logic, a deliberate incremental-build sequencing (page must load before it can be built up section by section, same reasoning as building any multi-part UI test-first).

**Type consistency:** `createTraining(array $data): Training`, `createFirstClass(array $data): MentorshipClass`, `assignModules(array $data): int`, `enrollMentees(array $data): int`, `sendInvitations(array $data): array` — identical signatures to their `GuidedMentorshipSetup` counterparts throughout, since both delegate to the same `MentorshipWizardService` methods with the same argument shapes. `$basicsSaved`/`$firstClassSaved`/`$modulesSaved`/`$menteesSaved` are all `bool`, set exactly once each (by their own section's save method) and read only by the next section's `visible()`/`required()` closures and Task 7's `mount()` — no task reads a flag before the task that defines it.

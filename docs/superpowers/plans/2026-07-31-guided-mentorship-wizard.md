# Guided Mentorship Setup Wizard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a new, parallel "Guided Setup" wizard that walks a coordinator from creating a mentorship through creating a class, assigning modules, enrolling mentees, and sending invitations — as one continuous multi-step journey — without changing the existing single-page create form or any of the existing class/module/mentee pages.

**Architecture:** One new Filament page (`GuidedMentorshipSetup`, a standalone page implementing `HasForms`, not a `CreateRecord` page) hosting a single `Forms\Components\Wizard` with 7 steps. Each step persists its own slice of data via `Wizard\Step::afterValidation()` the moment the coordinator clicks "Next," calling the exact same underlying model-creation calls and services (`EnrollmentService`, `ModuleUsageService`, `MenteeEnrollmentInvitationMail`) the existing multi-page flow already uses. A "Done" confirmation screen replaces the Wizard once the final step completes.

**Tech Stack:** Laravel 12, Filament v3.3, Livewire, PHPUnit + `RefreshDatabase`.

## Global Constraints

- Zero changes to `CreateMentorshipTraining.php`, `ManageMentorshipClasses.php`, `ManageClassModules.php`, `ManageClassMentees.php`, `EnrollmentService.php`, `ModuleUsageService.php`, or `MenteeEnrollmentInvitationMail.php` — everything is called, nothing is modified.
- The only touches to existing files are: adding one route entry to `MentorshipTrainingResource::getPages()`, and adding one header action button to `ListMentorshipTrainings::getHeaderActions()`.
- Single sitting, no draft-resume UX. If the coordinator abandons the wizard partway, whatever was already persisted (Training, MentorshipClass, etc.) is left exactly as a normal, unflagged record — no cleanup job, no "incomplete" marker.
- Every persistence method must mirror the exact business logic of its existing counterpart bit-for-bit (same fields, same defaults, same auto-generation rules) — this is a new orchestration layer, not new domain logic.
- No new permission gate — the guided setup page is reachable by anyone who can already reach `MentorshipTrainingResource`'s create page.
- Each Wizard step's persistence logic lives in its own small, directly-callable public method on the page class (e.g. `createTraining(array $data): Training`) rather than being buried inline in the `afterValidation()` closure. This keeps each step's side effect independently testable via `Livewire::test(...)->call('methodName', [...])`, sidestepping the fragility of driving Filament's Wizard "Next" button (which involves Alpine.js state) inside a headless PHPUnit/Livewire test — this project already tests behavior, not framework mechanics (see Phase 5b conventions).
- `$class` and `Program` `isEmonc()` detection uses the same duplicated-per-file pattern already used everywhere in this codebase (`str_contains(strtolower($program->name), 'maternal') && str_contains(..., 'emonc')`) — do not extract a shared helper; that would deviate from established convention.

---

### Task 1: Page scaffold, entry-point button, and Steps 1–3 (Run Type, Location, Program & Schedule)

**Files:**
- Create: `app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php`
- Create: `resources/views/filament/pages/guided-mentorship-setup.blade.php`
- Modify: `app/Filament/Resources/MentorshipTrainingResource.php:551-572` (add route)
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php:19-27` (add header button)
- Test: `tests/Feature/GuidedMentorshipSetupTest.php`

**Interfaces:**
- Produces: `GuidedMentorshipSetup::createTraining(array $data): Training` — called by later tasks' tests as the entry point that must have already run before class/module/mentee steps make sense.
- Produces: public properties `public ?Training $training = null;` and `public bool $completed = false;` — later tasks read/set `$this->training`.

- [ ] **Step 1: Write the failing test for the entry-point button**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\MentorshipResource\Pages\GuidedMentorshipSetup;
use App\Filament\Resources\MentorshipResource\Pages\ListMentorshipTrainings;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GuidedMentorshipSetupTest extends TestCase
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

    public function test_list_page_shows_guided_setup_button(): void
    {
        $this->actingAsCoordinator();

        Livewire::test(ListMentorshipTrainings::class)
            ->assertSeeHtml('Guided Setup');
    }

    public function test_guided_setup_page_loads(): void
    {
        $this->actingAsCoordinator();

        Livewire::test(GuidedMentorshipSetup::class)
            ->assertSuccessful();
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=GuidedMentorshipSetupTest`
Expected: FAIL — `GuidedMentorshipSetup` class not found, and "Guided Setup" text not found on the list page.

- [ ] **Step 3: Create the page class with Steps 1–3 and `createTraining()`**

```php
<?php

namespace App\Filament\Resources\MentorshipResource\Pages;

use App\Filament\Forms\Components\ProgramPicker;
use App\Filament\Resources\MentorshipTrainingResource;
use App\Models\Facility;
use App\Models\MentorshipClass;
use App\Models\Program;
use App\Models\Training;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Str;

class GuidedMentorshipSetup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = MentorshipTrainingResource::class;

    protected static string $view = 'filament.pages.guided-mentorship-setup';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public ?Training $training = null;

    public ?MentorshipClass $class = null;

    public bool $completed = false;

    public int $enrolledCount = 0;

    public int $invitedCount = 0;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function getTitle(): string
    {
        return 'Guided Mentorship Setup';
    }

    public function getSubheading(): ?string
    {
        return "We'll walk through this one step at a time — from creating the mentorship to inviting your mentees.";
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make('Run Type')
                        ->description('Is this a real live mentorship or a pilot/test run?')
                        ->icon('heroicon-o-beaker')
                        ->schema([
                            Forms\Components\Radio::make('is_pilot')
                                ->label('')
                                ->options([
                                    0 => 'Live Mentorship',
                                    1 => 'Pilot Run',
                                ])
                                ->descriptions([
                                    0 => 'Counts in dashboards, KPI badges, and analytics reports.',
                                    1 => 'Excluded from all counts, badges, and analytics. Use for testing.',
                                ])
                                ->default(0)
                                ->required()
                                ->inline(false),
                        ]),
                    Forms\Components\Wizard\Step::make('Location')
                        ->description('Where is this mentorship being conducted?')
                        ->icon('heroicon-o-map-pin')
                        ->columns(2)
                        ->schema([
                            Forms\Components\Select::make('county_id')
                                ->label('County')
                                ->relationship('county', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('facility_id', null))
                                ->prefixIcon('heroicon-o-map')
                                ->helperText('Select the county first'),
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
                                ->prefixIcon('heroicon-o-building-office-2')
                                ->helperText('Facilities load after selecting a county'),
                        ]),
                    Forms\Components\Wizard\Step::make('Program & Schedule')
                        ->description('What program is being mentored, and when?')
                        ->icon('heroicon-o-calendar-days')
                        ->schema([
                            ProgramPicker::make('program_id')
                                ->label('Mentorship Program')
                                ->helperText('Tap a programme card to select it.')
                                ->required()
                                ->validationMessages([
                                    'required' => 'Please pick a programme card.',
                                ])
                                ->columnSpanFull(),
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Start Date')
                                    ->required(fn (Get $get) => ! $this->isEmoncProgram($get('program_id')))
                                    ->visible(fn (Get $get) => ! $this->isEmoncProgram($get('program_id')))
                                    ->native(false)
                                    ->minDate(today())
                                    ->displayFormat('M j, Y')
                                    ->prefixIcon('heroicon-o-play'),
                                Forms\Components\DatePicker::make('end_date')
                                    ->label('End Date')
                                    ->required(fn (Get $get) => ! $this->isEmoncProgram($get('program_id')))
                                    ->visible(fn (Get $get) => ! $this->isEmoncProgram($get('program_id')))
                                    ->native(false)
                                    ->minDate(fn (Get $get) => $get('start_date') ?? now())
                                    ->after('start_date')
                                    ->displayFormat('M j, Y')
                                    ->prefixIcon('heroicon-o-stop'),
                                Forms\Components\TextInput::make('max_participants')
                                    ->label('Number of Mentees')
                                    ->numeric()
                                    ->default(20)
                                    ->suffix('mentees')
                                    ->prefixIcon('heroicon-o-users'),
                            ]),
                        ])
                        ->afterValidation(function (Get $get) {
                            try {
                                $this->createTraining([
                                    'is_pilot' => $get('is_pilot'),
                                    'county_id' => $get('county_id'),
                                    'facility_id' => $get('facility_id'),
                                    'program_id' => $get('program_id'),
                                    'start_date' => $get('start_date'),
                                    'end_date' => $get('end_date'),
                                    'max_participants' => $get('max_participants'),
                                ]);
                            } catch (\Throwable $e) {
                                $this->stepFailed($e);
                            }
                        }),
                ])
                    ->persistStepInQueryString(null)
                    ->skippable(false),
            ])
            ->statePath('data');
    }

    /**
     * Creates the Training record. Mirrors
     * CreateMentorshipTraining::mutateFormDataBeforeCreate() exactly.
     */
    public function createTraining(array $data): Training
    {
        $data['type'] = 'facility_mentorship';
        $data['mentor_id'] = auth()->id();
        $data['identifier'] = 'MT-'.strtoupper(Str::random(6));

        $program = isset($data['program_id']) ? Program::find($data['program_id']) : null;
        $facility = isset($data['facility_id']) ? Facility::find($data['facility_id']) : null;
        $date = ! empty($data['start_date']) ? \Carbon\Carbon::parse($data['start_date'])->format('M Y') : now()->format('M Y');

        $data['title'] = trim(implode(' - ', array_filter([
            $program?->name ?? 'MNCH Mentorship',
            $facility?->name,
            $date,
        ])));

        $this->training = Training::create($data);

        return $this->training;
    }

    /**
     * Shows a danger notification and halts the wizard on the current
     * step. Filament's Wizard component catches Halt exceptions thrown
     * inside afterValidation() and keeps the user on the current step
     * instead of advancing or crashing — see
     * vendor/filament/forms/src/Components/Wizard.php:105-107.
     */
    private function stepFailed(\Throwable $e): never
    {
        Notification::make()
            ->danger()
            ->title('Something Went Wrong')
            ->body($e->getMessage())
            ->send();

        throw new Halt;
    }

    private function isEmoncProgram(?int $programId): bool
    {
        if (! $programId) {
            return false;
        }

        $program = Program::find($programId);

        return $program
            && str_contains(strtolower($program->name), 'maternal')
            && str_contains(strtolower($program->name), 'emonc');
    }
}
```

- [ ] **Step 4: Create the Blade view**

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
                </p>
            @endif
            <div class="mt-4 flex gap-3">
                @if ($class)
                    <a href="{{ \App\Filament\Resources\MentorshipTrainingResource::getUrl('class-mentees', ['training' => $training->id, 'class' => $class->id]) }}"
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
        <form wire:submit="submit">
            {{ $this->form }}
        </form>
    @endif
</x-filament-panels::page>
```

- [ ] **Step 5: Add the route to `MentorshipTrainingResource`**

Find (`app/Filament/Resources/MentorshipTrainingResource.php:551-572`):

```php
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMentorshipTrainings::route('/'),
            'create' => Pages\CreateMentorshipTraining::route('/create'),
```

Replace with:

```php
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMentorshipTrainings::route('/'),
            'create' => Pages\CreateMentorshipTraining::route('/create'),
            'guided-setup' => Pages\GuidedMentorshipSetup::route('/guided-setup'),
```

(leave every other line in the array exactly as-is).

- [ ] **Step 6: Add the "Guided Setup" button to the list page**

Find (`app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php:19-27`):

```php
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Mentorship')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }
```

Replace with:

```php
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Mentorship')
                ->icon('heroicon-o-plus')
                ->color('primary'),
            Actions\Action::make('guided_setup')
                ->label('Guided Setup')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->url(fn () => MentorshipTrainingResource::getUrl('guided-setup')),
        ];
    }
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --filter=GuidedMentorshipSetupTest`
Expected: PASS (2 tests)

- [ ] **Step 8: Write and run the `createTraining()` persistence test**

Add to `tests/Feature/GuidedMentorshipSetupTest.php`:

```php
    public function test_create_training_persists_correct_attributes(): void
    {
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $facility = \App\Models\Facility::factory()->create(['name' => 'Test Facility']);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $training = $component->instance()->createTraining([
            'is_pilot' => 0,
            'county_id' => $facility->subcounty->county_id,
            'facility_id' => $facility->id,
            'program_id' => $program->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'max_participants' => 15,
        ]);

        $this->assertDatabaseHas('trainings', [
            'id' => $training->id,
            'type' => 'facility_mentorship',
            'program_id' => $program->id,
            'facility_id' => $facility->id,
            'is_pilot' => 0,
            'max_participants' => 15,
        ]);
        $this->assertStringStartsWith('MT-', $training->identifier);
        $this->assertStringContainsString('Newborn Care', $training->title);
        $this->assertStringContainsString('Test Facility', $training->title);
    }
```

Run: `php artisan test --filter=GuidedMentorshipSetupTest`
Expected: PASS (3 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php \
        resources/views/filament/pages/guided-mentorship-setup.blade.php \
        app/Filament/Resources/MentorshipTrainingResource.php \
        app/Filament/Resources/MentorshipResource/Pages/ListMentorshipTrainings.php \
        tests/Feature/GuidedMentorshipSetupTest.php
git commit -m "feat: scaffold guided mentorship setup wizard with mentorship-creation steps"
```

---

### Task 2: Step 4 — First Class

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php`
- Test: `tests/Feature/GuidedMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `$this->training` (set by Task 1's `createTraining()`).
- Produces: `GuidedMentorshipSetup::createFirstClass(array $data): MentorshipClass`, sets `$this->class`. Task 3 depends on `$this->class` existing.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/GuidedMentorshipSetupTest.php`:

```php
    public function test_create_first_class_persists_and_links_to_training(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;

        $class = $component->instance()->createFirstClass([
            'name' => 'January 2027 Cohort',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'description' => 'Gap identified: newborn resuscitation.',
        ]);

        $this->assertDatabaseHas('mentorship_classes', [
            'id' => $class->id,
            'training_id' => $training->id,
            'name' => 'January 2027 Cohort',
            'status' => 'draft',
        ]);
    }
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=GuidedMentorshipSetupTest`
Expected: FAIL — `createFirstClass` method not found.

- [ ] **Step 3: Add Step 4 and `createFirstClass()` to the page class**

In `GuidedMentorshipSetup.php`, add `MentorshipClass` to imports if not already present (it already is, from Task 1's `public ?MentorshipClass $class` property). Add a new Wizard step after the "Program & Schedule" step, inside the `Forms\Components\Wizard::make([...])` array:

```php
                    Forms\Components\Wizard\Step::make('First Class')
                        ->description("Let's create your first class or cohort.")
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            Forms\Components\TextInput::make('class_name')
                                ->label('Class/Cohort Name')
                                ->required()
                                ->placeholder('e.g., January 2027 Cohort')
                                ->maxLength(255),
                            Forms\Components\Grid::make(2)->schema([
                                Forms\Components\DatePicker::make('class_start_date')
                                    ->label('Start Date')
                                    ->required(fn () => ! $this->isEmoncProgram($this->training?->program_id))
                                    ->visible(fn () => ! $this->isEmoncProgram($this->training?->program_id))
                                    ->native(false)
                                    ->minDate(fn () => $this->training?->start_date)
                                    ->maxDate(fn () => $this->training?->end_date),
                                Forms\Components\DatePicker::make('class_end_date')
                                    ->label('End Date')
                                    ->required(fn () => ! $this->isEmoncProgram($this->training?->program_id))
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
                        ])
                        ->afterValidation(function (Get $get) {
                            try {
                                $this->createFirstClass([
                                    'name' => $get('class_name'),
                                    'start_date' => $get('class_start_date'),
                                    'end_date' => $get('class_end_date'),
                                    'description' => $get('class_description'),
                                ]);
                            } catch (\Throwable $e) {
                                $this->stepFailed($e);
                            }
                        }),
```

Add the method (mirrors `ManageMentorshipClasses::createClass()` exactly, including the pre-existing quirk where `description` is passed to `create()` but is not in `MentorshipClass::$fillable` — matching existing behavior bit-for-bit rather than fixing an unrelated gap):

```php
    /**
     * Creates the first MentorshipClass. Mirrors
     * ManageMentorshipClasses::createClass() exactly.
     */
    public function createFirstClass(array $data): MentorshipClass
    {
        $this->class = MentorshipClass::create([
            'training_id' => $this->training->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return $this->class;
    }
```

- [ ] **Step 4: Run to verify it passes**

Run: `php artisan test --filter=GuidedMentorshipSetupTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php tests/Feature/GuidedMentorshipSetupTest.php
git commit -m "feat: add First Class step to guided mentorship wizard"
```

---

### Task 3: Step 5 — Modules

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php`
- Test: `tests/Feature/GuidedMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `$this->training`, `$this->class` (from Tasks 1–2), `ModuleUsageService::getAvailableModules()` and `::assignModulesToClass()` (existing, unmodified).
- Produces: `GuidedMentorshipSetup::assignModules(array $data): int` (returns count of modules created).

- [ ] **Step 1: Write the failing tests (standard program + EmONC branch)**

Add to `tests/Feature/GuidedMentorshipSetupTest.php`:

```php
    public function test_assign_modules_creates_class_modules_for_standard_program(): void
    {
        $this->actingAsCoordinator();
        $program = Program::factory()->create(['name' => 'Newborn Care']);
        $training = \App\Models\Training::factory()->facilityMentorship()->create(['program_id' => $program->id]);
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $programModule = \App\Models\ProgramModule::factory()->create(['program_id' => $program->id, 'is_active' => true]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $created = $component->instance()->assignModules([
            'module_ids' => [$programModule->id],
            'auto_create_sessions' => false,
        ]);

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('class_modules', [
            'mentorship_class_id' => $class->id,
            'program_module_id' => $programModule->id,
            'status' => 'not_started',
        ]);
    }

    public function test_assign_modules_is_skippable(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $created = $component->instance()->assignModules(['module_ids' => [], 'auto_create_sessions' => false]);

        $this->assertSame(0, $created);
    }
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --filter=GuidedMentorshipSetupTest`
Expected: FAIL — `assignModules` method not found.

- [ ] **Step 3: Add Step 5 and `assignModules()` to the page class**

Add imports: `use App\Filament\Forms\Components\EmoncModulePicker;`, `use App\Models\ClassModule;`, `use App\Services\ModuleUsageService;`.

Add the Wizard step after "First Class". **Important:** Filament's
`Wizard\Step::schema()` only accepts one array/closure — both the picker
and the toggle must be returned together from a single closure call:

```php
                    Forms\Components\Wizard\Step::make('Modules')
                        ->description("Now let's add modules to this class. You can skip this and add them later.")
                        ->icon('heroicon-o-book-open')
                        ->schema(function () {
                            if ($this->isEmoncProgram($this->training?->program_id)) {
                                $picker = EmoncModulePicker::make('module_ids')
                                    ->label('Available Program Modules')
                                    ->training($this->training)
                                    ->class($this->class)
                                    ->helperText('Click a module to select all its tracks, or pick tracks individually.');
                            } else {
                                $available = app(ModuleUsageService::class)
                                    ->getAvailableModules($this->training, $this->class)
                                    ->mapWithKeys(fn ($module) => [$module->id => $module->name])
                                    ->toArray();

                                $picker = Forms\Components\CheckboxList::make('module_ids')
                                    ->label('Available Program Modules')
                                    ->options($available)
                                    ->searchable()
                                    ->bulkToggleable()
                                    ->helperText('Optional — you can add modules later from the class Modules page.');
                            }

                            return [
                                $picker,
                                Forms\Components\Toggle::make('auto_create_sessions')
                                    ->label('Auto-populate sessions from program template')
                                    ->default(true),
                            ];
                        })
                        ->afterValidation(function (Get $get) {
                            try {
                                $this->assignModules([
                                    'module_ids' => $get('module_ids') ?? [],
                                    'auto_create_sessions' => $get('auto_create_sessions') ?? true,
                                ]);
                            } catch (\Throwable $e) {
                                $this->stepFailed($e);
                            }
                        }),
```

Add the method (mirrors `ManageClassModules`'s `add_modules` action exactly, minus the optional module dates/notes fields per the spec):

```php
    /**
     * Assigns modules to the class. Mirrors the persistence portion of
     * ManageClassModules's "Add Modules" action exactly (dates/notes
     * fields are intentionally omitted — see design spec).
     */
    public function assignModules(array $data): int
    {
        $moduleIds = $data['module_ids'] ?? [];

        if (empty($moduleIds)) {
            return 0;
        }

        $service = app(ModuleUsageService::class);
        $createdModuleIds = [];

        $created = $service->assignModulesToClass(
            $this->training,
            $this->class,
            $moduleIds,
            null,
            function (ClassModule $classModule) use (&$createdModuleIds) {
                $createdModuleIds[] = $classModule->id;
            }
        );

        if (($data['auto_create_sessions'] ?? true) && $created > 0) {
            $this->class->load('classModules');
            foreach ($this->class->classModules as $classModule) {
                if (method_exists($classModule, 'autoCreateSessions')) {
                    $classModule->autoCreateSessions();
                }
            }
        }

        return $created;
    }
```

- [ ] **Step 4: Run to verify tests pass**

Run: `php artisan test --filter=GuidedMentorshipSetupTest`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php tests/Feature/GuidedMentorshipSetupTest.php
git commit -m "feat: add Modules step to guided mentorship wizard"
```

---

### Task 4: Step 6 — Enroll Mentees

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php`
- Test: `tests/Feature/GuidedMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `$this->class` (from Task 2), `EnrollmentService::enrollInClass()` (existing, unmodified).
- Produces: `GuidedMentorshipSetup::enrollMentees(array $data): int` (returns count enrolled), sets `$this->enrolledCount`.

- [ ] **Step 1: Write the failing tests (existing user + new user)**

Add to `tests/Feature/GuidedMentorshipSetupTest.php`:

```php
    public function test_enroll_mentees_enrolls_existing_selected_users(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $mentee = User::factory()->create();

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $count = $component->instance()->enrollMentees([
            'selected_users' => [$mentee->id],
            'new_mentee' => null,
        ]);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('class_participants', [
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_enroll_mentees_creates_and_enrolls_new_mentee(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $count = $component->instance()->enrollMentees([
            'selected_users' => [],
            'new_mentee' => [
                'email' => 'jane.wanjiku@example.com',
                'first_name' => 'Jane',
                'last_name' => 'Wanjiku',
            ],
        ]);

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('users', ['email' => 'jane.wanjiku@example.com', 'role' => 'mentee']);
        $newUser = User::where('email', 'jane.wanjiku@example.com')->first();
        $this->assertDatabaseHas('class_participants', [
            'mentorship_class_id' => $class->id,
            'user_id' => $newUser->id,
        ]);
    }

    public function test_enroll_mentees_is_skippable(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $count = $component->instance()->enrollMentees(['selected_users' => [], 'new_mentee' => null]);

        $this->assertSame(0, $count);
    }
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --filter=GuidedMentorshipSetupTest`
Expected: FAIL — `enrollMentees` method not found.

- [ ] **Step 3: Add Step 6 and `enrollMentees()` to the page class**

Add imports: `use App\Models\Cadre;`, `use App\Models\Department;`, `use App\Models\User;`, `use App\Services\EnrollmentService;`, `use Illuminate\Support\Facades\Hash;`.

Add the Wizard step after "Modules":

```php
                    Forms\Components\Wizard\Step::make('Enroll Mentees')
                        ->description('Who will be mentored in this class? You can skip this and enroll mentees later.')
                        ->icon('heroicon-o-user-plus')
                        ->schema([
                            Forms\Components\CheckboxList::make('selected_users')
                                ->label('Existing Users')
                                ->options(fn () => User::query()
                                    ->where('status', 'active')
                                    ->orderBy('first_name')
                                    ->limit(100)
                                    ->get()
                                    ->mapWithKeys(fn ($u) => [
                                        $u->id => implode(' · ', array_filter([$u->name, $u->email])),
                                    ])
                                    ->toArray())
                                ->searchable()
                                ->bulkToggleable()
                                ->helperText('Search and check existing users to enroll.'),
                            Forms\Components\Fieldset::make('Or Add a New Mentee')
                                ->schema([
                                    Forms\Components\TextInput::make('new_mentee.email')
                                        ->label('Email Address')
                                        ->email()
                                        ->placeholder('e.g. jane.wanjiku@moh.go.ke'),
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
                        ])
                        ->afterValidation(function (Get $get) {
                            try {
                                $this->enrollMentees([
                                    'selected_users' => $get('selected_users') ?? [],
                                    'new_mentee' => $get('new_mentee.email') ? $get('new_mentee') : null,
                                ]);
                            } catch (\Throwable $e) {
                                $this->stepFailed($e);
                            }
                        }),
```

Add the method (mirrors `ManageClassMentees`'s `manage_from_list` and `add_mentee` actions exactly):

```php
    /**
     * Enrolls selected existing users and/or a newly-created mentee.
     * Mirrors ManageClassMentees's "Add from List" / "Add Mentee" logic.
     */
    public function enrollMentees(array $data): int
    {
        $service = app(EnrollmentService::class);
        $count = 0;

        foreach ($data['selected_users'] ?? [] as $userId) {
            $user = User::find($userId);
            if ($user) {
                $service->enrollInClass($user, $this->class, 'manual');
                $count++;
            }
        }

        $newMentee = $data['new_mentee'] ?? null;
        if (! empty($newMentee['email'])) {
            $existing = User::where('email', $newMentee['email'])->first();

            if ($existing) {
                $service->enrollInClass($existing, $this->class, 'manual');
            } else {
                $displayName = trim(implode(' ', array_filter([
                    $newMentee['first_name'] ?? null,
                    $newMentee['last_name'] ?? null,
                ])));

                $user = User::create([
                    'first_name' => $newMentee['first_name'] ?? null,
                    'last_name' => $newMentee['last_name'] ?? null,
                    'name' => $displayName,
                    'email' => $newMentee['email'],
                    'phone' => $newMentee['phone'] ?? null,
                    'cadre_id' => $newMentee['cadre_id'] ?? null,
                    'department_id' => $newMentee['department_id'] ?? null,
                    'facility_id' => $newMentee['facility_id'] ?? null,
                    'password' => Hash::make('123456'),
                    'status' => 'active',
                    'role' => 'mentee',
                ]);

                if (method_exists($user, 'assignRole')) {
                    try {
                        $user->assignRole('mentee');
                    } catch (\Exception) {
                    }
                }

                $service->enrollInClass($user, $this->class, 'manual');
            }

            $count++;
        }

        $this->enrolledCount = $count;

        return $count;
    }
```

- [ ] **Step 4: Run to verify tests pass**

Run: `php artisan test --filter=GuidedMentorshipSetupTest`
Expected: PASS (9 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php tests/Feature/GuidedMentorshipSetupTest.php
git commit -m "feat: add Enroll Mentees step to guided mentorship wizard"
```

---

### Task 5: Step 7 — Send Invitations, Done screen, and wizard submission

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php`
- Test: `tests/Feature/GuidedMentorshipSetupTest.php`

**Interfaces:**
- Consumes: `$this->class` (from Task 2), `MenteeEnrollmentInvitationMail` (existing, unmodified).
- Produces: `GuidedMentorshipSetup::sendInvitations(array $data): array` (returns `['sent' => int, 'resent' => int]`), sets `$this->invitedCount` and `$this->completed = true`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/GuidedMentorshipSetupTest.php`:

```php
    public function test_send_invitations_emails_all_enrolled_mentees_with_email(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);
        $mentee = User::factory()->create(['email' => 'mentee@example.com']);
        $participant = \App\Models\ClassParticipant::factory()->create([
            'mentorship_class_id' => $class->id,
            'user_id' => $mentee->id,
            'status' => 'enrolled',
        ]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $result = $component->instance()->sendInvitations(['recipients' => 'all']);

        $this->assertSame(1, $result['sent']);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\MenteeEnrollmentInvitationMail::class, 1);
        $this->assertTrue($component->instance()->completed);
        $participant->refresh();
        $this->assertNotNull($participant->invitation_sent_at);
    }

    public function test_send_invitations_completes_with_zero_mentees(): void
    {
        $this->actingAsCoordinator();
        $training = \App\Models\Training::factory()->facilityMentorship()->create();
        $class = \App\Models\MentorshipClass::factory()->create(['training_id' => $training->id]);

        $component = Livewire::test(GuidedMentorshipSetup::class);
        $component->instance()->training = $training;
        $component->instance()->class = $class;

        $result = $component->instance()->sendInvitations(['recipients' => 'all']);

        $this->assertSame(0, $result['sent']);
        $this->assertTrue($component->instance()->completed);
    }
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --filter=GuidedMentorshipSetupTest`
Expected: FAIL — `sendInvitations` method not found.

- [ ] **Step 3: Add Step 7, `sendInvitations()`, and the `submit()` entry point**

Add imports: `use App\Mail\MenteeEnrollmentInvitationMail;`, `use App\Models\ClassParticipant;`, `use Illuminate\Support\Facades\Mail;`, `use Illuminate\Support\Str;` (already present from Task 1).

Add the final Wizard step after "Enroll Mentees":

```php
                    Forms\Components\Wizard\Step::make('Send Invitations')
                        ->description('Time to invite your mentees!')
                        ->icon('heroicon-o-paper-airplane')
                        ->schema([
                            Forms\Components\Radio::make('recipients')
                                ->label('Who should receive the email?')
                                ->options([
                                    'all' => 'All mentees with email addresses',
                                    'not_sent' => 'Only those not yet invited',
                                ])
                                ->default('all')
                                ->required(),
                        ]),
```

Wire up the Wizard's submit button. Find (the closing of the `Wizard::make([...])` array, unchanged since Task 1):

```php
                ])
                    ->persistStepInQueryString(null)
                    ->skippable(false),
```

Replace with:

```php
                ])
                    ->persistStepInQueryString(null)
                    ->skippable(false)
                    ->submitAction(view('filament.pages.partials.guided-wizard-submit')),
```

Create `resources/views/filament/pages/partials/guided-wizard-submit.blade.php`:

```blade
<x-filament::button type="submit" wire:loading.attr="disabled">
    Finish & Send Invitations
</x-filament::button>
```

Add the `submit()` method (called by the form's native submit, matching Filament's standard `wire:submit="submit"` convention already wired in the Blade view from Task 1) and `sendInvitations()`:

```php
    public function submit(): void
    {
        $data = $this->form->getState();

        try {
            $this->sendInvitations([
                'recipients' => $data['recipients'] ?? 'all',
            ]);
        } catch (\Throwable $e) {
            // Note: unlike afterValidation() on a Wizard\Step (caught internally
            // by Wizard.php), this submit() method is invoked directly by
            // wire:submit — nothing upstream catches Halt here, so we handle
            // the failure inline instead and simply stay on this step.
            Notification::make()
                ->danger()
                ->title('Something Went Wrong')
                ->body($e->getMessage())
                ->send();
        }
    }

    /**
     * Sends enrollment invitation emails. Mirrors ManageClassMentees's
     * "Send Invitations" bulk action exactly.
     */
    public function sendInvitations(array $data): array
    {
        if (! $this->class->enrollment_token) {
            $this->class->update([
                'enrollment_token' => Str::random(32),
                'enrollment_link_active' => true,
            ]);
        } else {
            $this->class->update(['enrollment_link_active' => true]);
        }
        $this->class->refresh();

        $query = ClassParticipant::where('mentorship_class_id', $this->class->id)
            ->whereHas('user', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
            ->with('user');

        if (($data['recipients'] ?? 'all') === 'not_sent') {
            $query->whereNull('invitation_sent_at');
        }

        $participants = $query->get();
        $sent = 0;
        $resent = 0;

        foreach ($participants as $record) {
            $isResend = (bool) $record->invitation_sent_at;

            Mail::to($record->user->email)->send(new MenteeEnrollmentInvitationMail(
                $record->user,
                $this->class,
                $record,
                $isResend
            ));

            $record->update(['invitation_sent_at' => now()]);
            $isResend ? $resent++ : $sent++;
        }

        $this->invitedCount = $sent + $resent;
        $this->completed = true;

        return ['sent' => $sent, 'resent' => $resent];
    }
```

- [ ] **Step 4: Run to verify tests pass**

Run: `php artisan test --filter=GuidedMentorshipSetupTest`
Expected: PASS (11 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/GuidedMentorshipSetup.php \
        resources/views/filament/pages/partials/guided-wizard-submit.blade.php \
        tests/Feature/GuidedMentorshipSetupTest.php
git commit -m "feat: add Send Invitations step and Done screen to guided mentorship wizard"
```

---

### Task 6: End-to-end manual verification

No new automated tests in this task — this is the manual verification pass, matching this project's established convention (see Phase 5b) of a final manual-browser-verification task for user-facing flows that automated tests can't fully substitute for.

- [ ] **Step 1: Start a dev server and log in**

Use the same approach as prior phases: start `php artisan serve` on a free port from within the working tree, log in as an existing coordinator/mentor user.

- [ ] **Step 2: Verify the entry point**

Navigate to the Mentorships list page. Confirm both "New Mentorship" and "Guided Setup" buttons are visible, and that the existing "New Mentorship" button still opens the original single-page form completely unchanged.

- [ ] **Step 3: Walk the full guided journey for a standard program**

Click "Guided Setup." Complete all 7 steps for a Newborn Care or Infant & Child Care mentorship: Run Type → Location → Program & Schedule → First Class → Modules (select at least one) → Enroll Mentees (both an existing user and a newly-created one) → Send Invitations. Confirm the Done screen shows the correct counts and that "Go to Class" navigates to the real `ManageClassMentees` page showing the enrolled/invited mentees.

- [ ] **Step 4: Walk the full guided journey for EmONC**

Repeat step 3 selecting the Maternal Health (EmONC) program card, confirming: dates are hidden on the Program & Schedule and First Class steps (matching existing EmONC behavior), and the Modules step shows the `EmoncModulePicker` (track-based) instead of the standard checkbox list.

- [ ] **Step 5: Verify skippability**

Start a new guided setup, and on both the Modules step and the Enroll Mentees step, click Next without selecting anything. Confirm the wizard advances normally and the Done screen reflects zero modules/zero mentees without error.

- [ ] **Step 6: Verify abandonment leaves a normal record**

Start a new guided setup, complete through step 4 (First Class), then close the tab. Navigate to the Mentorships list and confirm the mentorship and its class exist as normal, fully-visible, fully-editable records — no special badge, no error state.

- [ ] **Step 7: Regression-check the existing flow**

Confirm creating a mentorship via the original "New Mentorship" button, then manually navigating through Classes → Modules → Mentees, still works exactly as before this feature was added (nothing in this plan touched those files' logic, only the plan's own new file and two small additions).

- [ ] **Step 8: Run the full automated test suite**

Run: `php artisan test`
Expected: All prior passing tests remain green (2 pre-existing unrelated baseline failures acceptable, matching this project's known baseline — no new failures).

- [ ] **Step 9: Finish the branch**

Invoke `superpowers:finishing-a-development-branch` to verify tests, present the merge/PR/keep options, and clean up the worktree per the user's choice.

# Dynamic Survey Platform — Phase 2 Implementation Plan (Longitudinal Events)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a `Survey` define named events (Baseline, 3-month follow-up, an open-ended repeatable "Follow-up Visit," etc.), scope sections to a subset of those events, and fill/track responses per subject across events — with auto-numbered occurrences for repeatable events and zero changes to scoring.

**Architecture:** A new `SurveyEvent` model plus a `survey_event_sections` many-to-many pivot (a section with no pivot rows is shown at every event — additive, non-destructive default). `SurveyResponse` gains nullable `survey_event_id` and `event_instance_number` columns. `SurveyFormBuilder::buildForSurvey()` gains an optional `?SurveyEvent $event` parameter that filters which sections render. Everything else (field building, conditional logic, saving, scoring) is untouched — each event occurrence is already an independent `SurveyResponse` row, so Phase 1's per-response scoring works correctly with no changes.

**Tech Stack:** Laravel 12, Filament v3, PHPUnit, MySQL.

## Global Constraints

- A section with zero rows in `survey_event_sections` is shown at **every** event of its survey — never at none. This is what makes adding events to an existing survey non-destructive.
- Events and the section-to-event mapping are **admin-panel only** — the public survey link (`/survey/{token}`) and `PublicSurveyForm` are not touched by this plan.
- Instance numbering for repeatable events is **always computed** (`max(event_instance_number) + 1` scoped to `survey_event_id` + `subject_type` + `subject_id`), never user-entered.
- No new "Record" abstraction — a subject's longitudinal record is just `SurveyResponse` rows sharing `survey_id` + `subject_type` + `subject_id`.
- `app/Services/FormKernel/` (`QuestionFieldBuilder`, `GroupedFieldRenderer`, `ScoringEngine`), `SurveyScoringService`, `SurveyQuestionResource`, and every `Assessment*` file are out of scope — none of them need to change for this plan.
- Migration filenames continue the `2026_08_12_15xxxx` numbering (after Phase 1's `2026_08_12_14xxxx` range) so they run in order.
- Commit after every task using the existing repo's commit style (`feat:`/`fix:`/`test:` prefix, no marketing language).

---

### Task 1: Migrations — `survey_events` table, `survey_event_sections` pivot, `SurveyResponse` event columns

**Files:**
- Create: `database/migrations/2026_08_12_150000_create_survey_events_table.php`
- Create: `database/migrations/2026_08_12_150100_create_survey_event_sections_table.php`
- Create: `database/migrations/2026_08_12_150200_add_event_columns_to_survey_responses_table.php`

**Interfaces:**
- Produces: `survey_events` (id, survey_id, code, name, order, repeatable, timestamps), `survey_event_sections` (survey_event_id, survey_section_id — pivot, no model), `survey_responses.survey_event_id` (nullable FK), `survey_responses.event_instance_number` (nullable int). Every later task's models/services/resources depend on these exact column names.

- [ ] **Step 1: Write `create_survey_events_table`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->integer('order')->default(0);
            $table->boolean('repeatable')->default(false);
            $table->timestamps();

            $table->unique(['survey_id', 'code']);
            $table->index(['survey_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_events');
    }
};
```

- [ ] **Step 2: Write `create_survey_event_sections_table`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_event_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_section_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['survey_event_id', 'survey_section_id'], 'survey_event_section_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_event_sections');
    }
};
```

- [ ] **Step 3: Write `add_event_columns_to_survey_responses_table`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->foreignId('survey_event_id')->nullable()->after('survey_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('event_instance_number')->nullable()->after('survey_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('survey_event_id');
            $table->dropColumn('event_instance_number');
        });
    }
};
```

- [ ] **Step 4: Run the migrations**

Run: `php artisan migrate`
Expected: `survey_events` and `survey_event_sections` created; `survey_event_id`/`event_instance_number` added to `survey_responses`, no errors.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_12_1500*.php database/migrations/2026_08_12_1501*.php database/migrations/2026_08_12_1502*.php
git commit -m "feat: add survey_events, survey_event_sections, and SurveyResponse event columns"
```

---

### Task 2: `SurveyEvent` model + relations on `Survey`/`SurveySection`/`SurveyResponse`

**Files:**
- Create: `app/Models/SurveyEvent.php`
- Modify: `app/Models/Survey.php` (add `events()`)
- Modify: `app/Models/SurveySection.php` (add `events()`)
- Modify: `app/Models/SurveyResponse.php` (add `event()`, `fillable` additions)
- Test: `tests/Feature/SurveyEventModelTest.php`

**Interfaces:**
- Consumes: `survey_events`/`survey_event_sections` tables from Task 1.
- Produces: `SurveyEvent::sections()` (BelongsToMany), `SurveyEvent::responses()` (HasMany), `SurveyEvent::scopeOrdered()`; `Survey::events()` (HasMany); `SurveySection::events()` (BelongsToMany); `SurveyResponse::event()` (BelongsTo `SurveyEvent`). These exact method names are what Tasks 3–8 call.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyEventModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_survey_has_events_ordered_by_order_column(): void
    {
        $survey = Survey::create(['code' => 'EVENT_MODEL_TEST', 'name' => 'Event Model Test', 'is_active' => true]);
        $second = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $first = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);

        $ordered = $survey->events()->ordered()->get();

        $this->assertTrue($ordered->first()->is($first));
        $this->assertTrue($ordered->last()->is($second));
    }

    public function test_event_can_be_attached_to_sections_and_back(): void
    {
        $survey = Survey::create(['code' => 'EVENT_SECTION_TEST', 'name' => 'Event Section Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'demographics', 'name' => 'Demographics', 'order' => 1]);

        $event->sections()->attach($section->id);

        $this->assertTrue($event->fresh()->sections->first()->is($section));
        $this->assertTrue($section->fresh()->events->first()->is($event));
    }

    public function test_response_belongs_to_an_event(): void
    {
        $survey = Survey::create(['code' => 'RESPONSE_EVENT_TEST', 'name' => 'Response Event Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $event->id, 'status' => 'draft']);

        $this->assertTrue($response->fresh()->event->is($event));
        $this->assertTrue($event->fresh()->responses->first()->is($response));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyEventModelTest`
Expected: FAIL — `App\Models\SurveyEvent` doesn't exist.

- [ ] **Step 3: Create `SurveyEvent`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyEvent extends Model
{
    protected $fillable = [
        'survey_id', 'code', 'name', 'order', 'repeatable',
    ];

    protected $casts = [
        'repeatable' => 'boolean',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(SurveySection::class, 'survey_event_sections');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
```

- [ ] **Step 4: Add `Survey::events()`**

In `app/Models/Survey.php`, add alongside `sections()`/`responses()`:

```php
public function events(): HasMany
{
    return $this->hasMany(SurveyEvent::class);
}
```

- [ ] **Step 5: Add `SurveySection::events()`**

In `app/Models/SurveySection.php`, add:

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

to the imports, and add alongside `questions()`/`sectionScores()`:

```php
public function events(): BelongsToMany
{
    return $this->belongsToMany(SurveyEvent::class, 'survey_event_sections');
}
```

- [ ] **Step 6: Add `SurveyResponse::event()` and fillable columns**

In `app/Models/SurveyResponse.php`, add `'survey_event_id', 'event_instance_number'` to `$fillable` (right after `'survey_id'`), and add alongside `survey()`/`subject()`:

```php
public function event(): BelongsTo
{
    return $this->belongsTo(SurveyEvent::class, 'survey_event_id');
}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=SurveyEventModelTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Models/SurveyEvent.php app/Models/Survey.php app/Models/SurveySection.php app/Models/SurveyResponse.php tests/Feature/SurveyEventModelTest.php
git commit -m "feat: add SurveyEvent model and event relations"
```

---

### Task 3: Section-visibility filtering in `SurveyFormBuilder::buildForSurvey()`

**Files:**
- Modify: `app/Services/SurveyFormBuilder.php`
- Test: `tests/Feature/SurveyFormBuilderEventFilterTest.php`

**Interfaces:**
- Consumes: `SurveySection::events()` (Task 2).
- Produces: `SurveyFormBuilder::buildForSurvey(Survey $survey, ?int $surveyResponseId = null, ?SurveyEvent $event = null): array` — the third parameter is new; existing two-argument call sites (`EditSurveyResponse::form()`, `PublicSurveyForm::form()`) keep compiling and behaving identically since it defaults to `null`. Task 5 passes a real `SurveyEvent` in.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use App\Services\SurveyFormBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyFormBuilderEventFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_section_with_no_event_mapping_appears_for_every_event(): void
    {
        $survey = Survey::create(['code' => 'FILTER_ALL_TEST', 'name' => 'Filter All Test', 'is_active' => true]);
        $baseline = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $followup = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'general', 'name' => 'General', 'order' => 1]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'FA_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);

        $baselineSections = SurveyFormBuilder::buildForSurvey($survey, null, $baseline);
        $followupSections = SurveyFormBuilder::buildForSurvey($survey, null, $followup);

        $this->assertCount(1, $baselineSections);
        $this->assertCount(1, $followupSections);
    }

    public function test_a_section_mapped_to_one_event_is_excluded_from_another(): void
    {
        $survey = Survey::create(['code' => 'FILTER_ONE_TEST', 'name' => 'Filter One Test', 'is_active' => true]);
        $baseline = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $followup = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $demographics = SurveySection::create(['survey_id' => $survey->id, 'code' => 'demographics', 'name' => 'Demographics', 'order' => 1]);
        $vitals = SurveySection::create(['survey_id' => $survey->id, 'code' => 'vitals', 'name' => 'Vitals', 'order' => 2]);
        SurveyQuestion::create(['survey_section_id' => $demographics->id, 'question_code' => 'FO_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);
        SurveyQuestion::create(['survey_section_id' => $vitals->id, 'question_code' => 'FO_Q2', 'question_text' => 'Q2', 'question_type' => 'yes_no']);
        $demographics->events()->attach($baseline->id);

        $baselineSections = SurveyFormBuilder::buildForSurvey($survey, null, $baseline);
        $followupSections = SurveyFormBuilder::buildForSurvey($survey, null, $followup);

        $this->assertCount(2, $baselineSections);
        $this->assertCount(1, $followupSections);
        $this->assertSame('Vitals', $followupSections[0]->getHeading());
    }

    public function test_passing_no_event_renders_every_active_section_unchanged(): void
    {
        $survey = Survey::create(['code' => 'FILTER_NONE_TEST', 'name' => 'Filter None Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'FN_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);

        $sections = SurveyFormBuilder::buildForSurvey($survey);

        $this->assertCount(1, $sections);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyFormBuilderEventFilterTest`
Expected: FAIL — `buildForSurvey()` doesn't accept a third argument yet (TypeError/ArgumentCountError on the two tests passing `$event`).

- [ ] **Step 3: Update `buildForSurvey()`**

In `app/Services/SurveyFormBuilder.php`, replace:

```php
public static function buildForSurvey(Survey $survey, ?int $surveyResponseId = null): array
{
    $sections = $survey->sections()->active()->orderBy('order')->get();

    return $sections->map(fn (SurveySection $section) => Forms\Components\Section::make($section->name)
        ->description($section->description)
        ->schema(static::buildForSection($section->id, $surveyResponseId))
        ->collapsible())->all();
}
```

with:

```php
public static function buildForSurvey(Survey $survey, ?int $surveyResponseId = null, ?\App\Models\SurveyEvent $event = null): array
{
    $sections = $survey->sections()->active()->orderBy('order')->get();

    if ($event) {
        $sections = $sections->filter(
            fn (SurveySection $section) => $section->events->isEmpty() || $section->events->contains($event->id)
        );
    }

    return $sections->map(fn (SurveySection $section) => Forms\Components\Section::make($section->name)
        ->description($section->description)
        ->schema(static::buildForSection($section->id, $surveyResponseId))
        ->collapsible())->all();
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SurveyFormBuilderEventFilterTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Run the existing SurveyFormBuilder suite to confirm no regression**

Run: `php artisan test --filter=SurveyFormBuilderTest`
Expected: PASS, identical to before this task (the two-argument call form is unaffected).

- [ ] **Step 6: Commit**

```bash
git add app/Services/SurveyFormBuilder.php tests/Feature/SurveyFormBuilderEventFilterTest.php
git commit -m "feat: filter sections by event in SurveyFormBuilder::buildForSurvey"
```

---

### Task 4: `EventsRelationManager` on `SurveyResource`

**Files:**
- Create: `app/Filament/Resources/SurveyResource/RelationManagers/EventsRelationManager.php`
- Modify: `app/Filament/Resources/SurveyResource.php` (register the relation manager)
- Test: `tests/Feature/SurveyEventsRelationManagerTest.php`

**Interfaces:**
- Consumes: `Survey::events()` (Task 2).
- Produces: a working `events` relation manager tab on the Survey edit page, following the exact structural pattern of the existing `SectionsRelationManager`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResource\RelationManagers\EventsRelationManager;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyEventsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_event_can_be_created_through_the_relation_manager(): void
    {
        $user = User::factory()->create();
        foreach (['view_any_survey', 'update_survey'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey', 'update_survey']);
        $this->actingAs($user);

        $survey = Survey::create(['code' => 'EVENTS_RM_TEST', 'name' => 'Events RM Test', 'is_active' => true]);

        Livewire::test(EventsRelationManager::class, [
            'ownerRecord' => $survey,
            'pageClass' => \App\Filament\Resources\SurveyResource\Pages\EditSurvey::class,
        ])
            ->callTableAction('create', data: [
                'code' => 'baseline',
                'name' => 'Baseline',
                'order' => 1,
                'repeatable' => false,
            ]);

        $this->assertDatabaseHas('survey_events', [
            'survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'repeatable' => false,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyEventsRelationManagerTest`
Expected: FAIL — `EventsRelationManager` doesn't exist.

- [ ] **Step 3: Create `EventsRelationManager`**

```php
<?php

namespace App\Filament\Resources\SurveyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Events';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->helperText('Unique within this survey'),
                Forms\Components\TextInput::make('order')->numeric()->default(0)->required(),
                Forms\Components\Toggle::make('repeatable')
                    ->default(false)
                    ->helperText('On: this event can occur any number of times per subject (e.g. "Follow-up Visit"). Off: happens once per subject (e.g. "Baseline").'),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('order')->sortable()->alignCenter(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('code')->badge()->color('gray'),
                Tables\Columns\IconColumn::make('repeatable')->boolean(),
                Tables\Columns\TextColumn::make('responses_count')->label('Responses')->counts('responses')->badge()->color('info'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (! isset($data['order']) || $data['order'] === 0) {
                            $data['order'] = ($this->getOwnerRecord()->events()->max('order') ?? 0) + 10;
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->responses()->count() > 0) {
                            Notification::make()->title('Cannot delete — has responses')->danger()->send();

                            return false;
                        }
                    }),
            ])
            ->defaultSort('order')
            ->reorderable('order');
    }
}
```

- [ ] **Step 4: Register it on `SurveyResource`**

In `app/Filament/Resources/SurveyResource.php`, update `getRelations()`:

```php
public static function getRelations(): array
{
    return [
        RelationManagers\SectionsRelationManager::class,
        RelationManagers\EventsRelationManager::class,
    ];
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SurveyEventsRelationManagerTest`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/SurveyResource/RelationManagers/EventsRelationManager.php app/Filament/Resources/SurveyResource.php tests/Feature/SurveyEventsRelationManagerTest.php
git commit -m "feat: add EventsRelationManager to SurveyResource"
```

---

### Task 5: Event `CheckboxList` on `SectionsRelationManager`

**Files:**
- Modify: `app/Filament/Resources/SurveyResource/RelationManagers/SectionsRelationManager.php`
- Test: `tests/Feature/SurveySectionEventMappingTest.php`

**Interfaces:**
- Consumes: `SurveySection::events()` (Task 2), `Survey::events()` (Task 2).
- Produces: the section form's `events` field, a multi-select bound directly to the `events()` BelongsToMany relationship (Filament resolves a field named after a relationship method automatically — no custom save-hook needed).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResource\RelationManagers\SectionsRelationManager;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveySection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveySectionEventMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_section_can_attach_it_to_specific_events(): void
    {
        $user = User::factory()->create();
        foreach (['view_any_survey', 'update_survey'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey', 'update_survey']);
        $this->actingAs($user);

        $survey = Survey::create(['code' => 'SECTION_EVENT_MAP_TEST', 'name' => 'Section Event Map Test', 'is_active' => true]);
        $baseline = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'demographics', 'name' => 'Demographics', 'order' => 1]);

        Livewire::test(SectionsRelationManager::class, [
            'ownerRecord' => $survey,
            'pageClass' => \App\Filament\Resources\SurveyResource\Pages\EditSurvey::class,
        ])
            ->callTableAction('edit', $section, data: [
                'name' => 'Demographics',
                'code' => 'demographics',
                'order' => 1,
                'is_scored' => true,
                'is_active' => true,
                'events' => [$baseline->id],
            ]);

        $this->assertSame([$baseline->id], $section->fresh()->events->pluck('id')->all());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveySectionEventMappingTest`
Expected: FAIL — the section form has no `events` field, so the pivot is never written and `events->pluck('id')` comes back empty.

- [ ] **Step 3: Add the events `CheckboxList` to the form**

In `app/Filament/Resources/SurveyResource/RelationManagers/SectionsRelationManager.php`, replace the `form()` method:

```php
public function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\Grid::make(2)->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255)->columnSpan(2),
            Forms\Components\TextInput::make('code')
                ->required()
                ->maxLength(255)
                ->alphaDash()
                ->helperText('Unique within this survey'),
            Forms\Components\TextInput::make('order')->numeric()->default(0)->required(),
            Forms\Components\Toggle::make('is_scored')->default(true),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Textarea::make('description')->rows(2)->columnSpan(2),
        ]),
        Forms\Components\CheckboxList::make('events')
            ->relationship('events', 'name')
            ->label('Shown at events')
            ->helperText('Leave all unchecked to show this section at every event. Check specific events to show it only at those.')
            ->columns(2)
            ->visible(fn () => $this->getOwnerRecord()->events()->exists())
            ->columnSpanFull(),
    ]);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SurveySectionEventMappingTest`
Expected: PASS (1 test).

- [ ] **Step 5: Run the existing SectionsRelationManager-adjacent suite to confirm no regression**

Run: `php artisan test --filter=SurveyResourceTest`
Expected: PASS, identical to before (this task doesn't touch `SurveyResource.php` itself).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/SurveyResource/RelationManagers/SectionsRelationManager.php tests/Feature/SurveySectionEventMappingTest.php
git commit -m "feat: add event mapping checkbox list to SectionsRelationManager"
```

---

### Task 6: Instance-numbering helper — `SurveyEvent::nextInstanceNumberFor()`

**Files:**
- Modify: `app/Models/SurveyEvent.php`
- Test: `tests/Feature/SurveyEventInstanceNumberTest.php`

**Interfaces:**
- Consumes: `SurveyResponse` (existing model), the `event_instance_number`/`survey_event_id`/`subject_type`/`subject_id` columns (Task 1).
- Produces: `SurveyEvent::nextInstanceNumberFor(?string $subjectType, ?int $subjectId): int` — Task 7's `CreateSurveyResponse` page calls this exact method to compute the value it writes into `event_instance_number`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyEventInstanceNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_instance_for_a_subject_is_one(): void
    {
        $survey = Survey::create(['code' => 'INSTANCE_FIRST_TEST', 'name' => 'Instance First Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 1, 'repeatable' => true]);
        $facility = Facility::factory()->create();

        $this->assertSame(1, $event->nextInstanceNumberFor(Facility::class, $facility->id));
    }

    public function test_next_instance_increments_from_the_subjects_existing_max(): void
    {
        $survey = Survey::create(['code' => 'INSTANCE_INCREMENT_TEST', 'name' => 'Instance Increment Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 1, 'repeatable' => true]);
        $facility = Facility::factory()->create();
        SurveyResponse::create([
            'survey_id' => $survey->id, 'survey_event_id' => $event->id,
            'subject_type' => Facility::class, 'subject_id' => $facility->id,
            'event_instance_number' => 1, 'status' => 'submitted',
        ]);
        SurveyResponse::create([
            'survey_id' => $survey->id, 'survey_event_id' => $event->id,
            'subject_type' => Facility::class, 'subject_id' => $facility->id,
            'event_instance_number' => 2, 'status' => 'submitted',
        ]);

        $this->assertSame(3, $event->nextInstanceNumberFor(Facility::class, $facility->id));
    }

    public function test_different_subjects_number_independently(): void
    {
        $survey = Survey::create(['code' => 'INSTANCE_INDEPENDENT_TEST', 'name' => 'Instance Independent Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 1, 'repeatable' => true]);
        $facilityA = Facility::factory()->create();
        $facilityB = Facility::factory()->create();
        SurveyResponse::create([
            'survey_id' => $survey->id, 'survey_event_id' => $event->id,
            'subject_type' => Facility::class, 'subject_id' => $facilityA->id,
            'event_instance_number' => 1, 'status' => 'submitted',
        ]);

        $this->assertSame(1, $event->nextInstanceNumberFor(Facility::class, $facilityB->id));
    }

    public function test_null_subject_shares_one_bucket(): void
    {
        $survey = Survey::create(['code' => 'INSTANCE_NULL_TEST', 'name' => 'Instance Null Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 1, 'repeatable' => true]);
        SurveyResponse::create([
            'survey_id' => $survey->id, 'survey_event_id' => $event->id,
            'event_instance_number' => 1, 'status' => 'submitted',
        ]);

        $this->assertSame(2, $event->nextInstanceNumberFor(null, null));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyEventInstanceNumberTest`
Expected: FAIL — `nextInstanceNumberFor()` doesn't exist.

- [ ] **Step 3: Add the method to `SurveyEvent`**

In `app/Models/SurveyEvent.php`, add:

```php
/**
 * Scoped to (this event, subject_type, subject_id) — including the null/null
 * "no subject" bucket, which every subject-less response to this event
 * shares. Never user-entered; called once, at response-creation time.
 */
public function nextInstanceNumberFor(?string $subjectType, ?int $subjectId): int
{
    $max = $this->responses()
        ->where('subject_type', $subjectType)
        ->where('subject_id', $subjectId)
        ->max('event_instance_number');

    return ($max ?? 0) + 1;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SurveyEventInstanceNumberTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/SurveyEvent.php tests/Feature/SurveyEventInstanceNumberTest.php
git commit -m "feat: add SurveyEvent::nextInstanceNumberFor for auto instance numbering"
```

---

### Task 7: Event picker + auto instance numbering on `SurveyResponseResource`'s Create form

**Files:**
- Modify: `app/Filament/Resources/SurveyResponseResource.php` (form)
- Modify: `app/Filament/Resources/SurveyResponseResource/Pages/CreateSurveyResponse.php`
- Test: `tests/Feature/CreateSurveyResponseEventTest.php`

**Interfaces:**
- Consumes: `Survey::events()` (Task 2), `SurveyEvent::nextInstanceNumberFor()` (Task 6).
- Produces: the Create form gains a live `survey_event_id` `Select`, visible only when the chosen survey has events; `CreateSurveyResponse::mutateFormDataBeforeCreate()` computes and injects `event_instance_number` when the chosen event is repeatable.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResponseResource\Pages\CreateSurveyResponse;
use App\Models\Facility;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CreateSurveyResponseEventTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        foreach (['view_any_survey::response', 'create_survey::response'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey::response', 'create_survey::response']);
        $this->actingAs($user);

        return $user;
    }

    public function test_creating_a_response_for_a_repeatable_event_computes_instance_number(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'CSR_EVENT_TEST', 'name' => 'CSR Event Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 1, 'repeatable' => true]);
        $facility = Facility::factory()->create();
        SurveyResponse::create([
            'survey_id' => $survey->id, 'survey_event_id' => $event->id,
            'subject_type' => Facility::class, 'subject_id' => $facility->id,
            'event_instance_number' => 1, 'status' => 'submitted',
        ]);

        Livewire::test(CreateSurveyResponse::class)
            ->fillForm([
                'survey_id' => $survey->id,
                'survey_event_id' => $event->id,
                'subject_type' => Facility::class,
                'subject_id' => $facility->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->id, 'survey_event_id' => $event->id,
            'subject_type' => Facility::class, 'subject_id' => $facility->id,
            'event_instance_number' => 2,
        ]);
    }

    public function test_creating_a_response_for_a_fixed_event_leaves_instance_number_null(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'CSR_FIXED_TEST', 'name' => 'CSR Fixed Test', 'is_active' => true]);
        $event = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1, 'repeatable' => false]);

        Livewire::test(CreateSurveyResponse::class)
            ->fillForm(['survey_id' => $survey->id, 'survey_event_id' => $event->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->id, 'survey_event_id' => $event->id, 'event_instance_number' => null,
        ]);
    }

    public function test_event_field_is_absent_from_the_form_schema_for_a_survey_with_no_events(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'CSR_NO_EVENTS_TEST', 'name' => 'CSR No Events Test', 'is_active' => true]);

        $component = Livewire::test(CreateSurveyResponse::class)
            ->fillForm(['survey_id' => $survey->id]);

        $component->assertFormFieldIsHidden('survey_event_id');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CreateSurveyResponseEventTest`
Expected: FAIL — no `survey_event_id`/`subject_type`/`subject_id` fields exist on the Create form yet, and instance numbering is never computed.

- [ ] **Step 3: Add the event/subject fields to `SurveyResponseResource::form()`**

In `app/Filament/Resources/SurveyResponseResource.php`, replace `form()`:

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\Select::make('survey_id')
            ->label('Survey')
            ->options(fn () => Survey::active()->pluck('name', 'id'))
            ->required()
            ->searchable()
            ->preload()
            ->live(),
        Forms\Components\Select::make('survey_event_id')
            ->label('Event')
            ->options(fn (Forms\Get $get) => \App\Models\SurveyEvent::where('survey_id', $get('survey_id'))->ordered()->pluck('name', 'id'))
            ->visible(fn (Forms\Get $get) => $get('survey_id') && \App\Models\SurveyEvent::where('survey_id', $get('survey_id'))->exists())
            ->live()
            ->helperText(fn (Forms\Get $get) => optional(\App\Models\SurveyEvent::find($get('survey_event_id')))->repeatable
                ? 'Repeatable — a new occurrence number is assigned automatically for the selected subject.'
                : null),
        Forms\Components\Select::make('subject_type')
            ->label('Subject type')
            ->options([
                \App\Models\Facility::class => 'Facility',
                \App\Models\User::class => 'User',
                \App\Models\MentorshipClass::class => 'Mentorship Class',
            ])
            ->live()
            ->visible(fn (Forms\Get $get) => filled($get('survey_event_id'))),
        Forms\Components\Select::make('subject_id')
            ->label('Subject')
            ->options(fn (Forms\Get $get) => match ($get('subject_type')) {
                \App\Models\Facility::class => \App\Models\Facility::query()->pluck('name', 'id'),
                \App\Models\User::class => \App\Models\User::query()->pluck('name', 'id'),
                \App\Models\MentorshipClass::class => \App\Models\MentorshipClass::query()->pluck('name', 'id'),
                default => [],
            })
            ->searchable()
            ->visible(fn (Forms\Get $get) => filled($get('subject_type'))),
        Forms\Components\TextInput::make('respondent_name')->maxLength(255),
        Forms\Components\TextInput::make('respondent_email')->email()->maxLength(255),
        Forms\Components\TextInput::make('respondent_contact')->maxLength(255),
    ]);
}
```

- [ ] **Step 4: Compute instance numbering in `CreateSurveyResponse`**

Replace `app/Filament/Resources/SurveyResponseResource/Pages/CreateSurveyResponse.php`'s `mutateFormDataBeforeCreate()`:

```php
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['created_by'] = auth()->id();
    $data['status'] = 'draft';

    if (! empty($data['survey_event_id'])) {
        $event = \App\Models\SurveyEvent::find($data['survey_event_id']);

        if ($event?->repeatable) {
            $data['event_instance_number'] = $event->nextInstanceNumberFor(
                $data['subject_type'] ?? null,
                $data['subject_id'] ?? null,
            );
        }
    }

    return $data;
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CreateSurveyResponseEventTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Run the existing SurveyResponseResource suite to confirm no regression**

Run: `php artisan test --filter=SurveyResponseResourceTest`
Expected: PASS — non-longitudinal surveys (no events) still create responses exactly as before, since every new field is conditionally hidden/empty when the survey has no events.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/SurveyResponseResource.php app/Filament/Resources/SurveyResponseResource/Pages/CreateSurveyResponse.php tests/Feature/CreateSurveyResponseEventTest.php
git commit -m "feat: add event/subject picker and auto instance numbering to response create form"
```

---

### Task 8: Event-aware rendering in `EditSurveyResponse`

**Files:**
- Modify: `app/Filament/Resources/SurveyResponseResource/Pages/EditSurveyResponse.php`
- Test: `tests/Feature/EditSurveyResponseEventTest.php`

**Interfaces:**
- Consumes: `SurveyFormBuilder::buildForSurvey()`'s new third parameter (Task 3), `SurveyResponse::event()` (Task 2).
- Produces: editing a response tied to an event now renders only that event's sections (per Task 3's filtering), instead of every active section in the survey.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResponseResource\Pages\EditSurveyResponse;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EditSurveyResponseEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_an_event_scoped_response_only_shows_that_events_sections(): void
    {
        $user = User::factory()->create();
        foreach (['view_any_survey::response', 'update_survey::response'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey::response', 'update_survey::response']);
        $this->actingAs($user);

        $survey = Survey::create(['code' => 'ESR_EVENT_TEST', 'name' => 'ESR Event Test', 'is_active' => true]);
        $baseline = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $followup = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $demographics = SurveySection::create(['survey_id' => $survey->id, 'code' => 'demographics', 'name' => 'Demographics', 'order' => 1]);
        $vitals = SurveySection::create(['survey_id' => $survey->id, 'code' => 'vitals', 'name' => 'Vitals', 'order' => 2]);
        SurveyQuestion::create(['survey_section_id' => $demographics->id, 'question_code' => 'ESR_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no']);
        SurveyQuestion::create(['survey_section_id' => $vitals->id, 'question_code' => 'ESR_Q2', 'question_text' => 'Q2', 'question_type' => 'yes_no']);
        $demographics->events()->attach($baseline->id);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $followup->id, 'status' => 'draft']);

        $component = Livewire::test(EditSurveyResponse::class, ['record' => $response->getRouteKey()]);

        $schema = $component->instance()->form->getFlatFields();
        $this->assertArrayNotHasKey("question_response_{$demographics->questions->first()->id ?? 'x'}", $schema);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EditSurveyResponseEventTest`
Expected: FAIL — `EditSurveyResponse::form()` still calls `buildForSurvey()` two-argument, so the Follow-up-tied response renders Demographics too (the assertion should trip, since the Demographics field would still be present).

Note: this assertion is written defensively (the `?? 'x'` guard) because if the filtering bug is present, `$demographics->questions->first()` should still resolve to a real question and the key really will exist in the schema — confirming the failure. If a future refactor makes `questions->first()` legitimately null, treat that as a signal the test fixture needs the same scrutiny, not that the assertion is wrong.

- [ ] **Step 3: Update `EditSurveyResponse::form()`**

In `app/Filament/Resources/SurveyResponseResource/Pages/EditSurveyResponse.php`, replace:

```php
public function form(Form $form): Form
{
    return $form->schema(
        SurveyFormBuilder::buildForSurvey($this->record->survey, $this->record->id)
    );
}
```

with:

```php
public function form(Form $form): Form
{
    return $form->schema(
        SurveyFormBuilder::buildForSurvey($this->record->survey, $this->record->id, $this->record->event)
    );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=EditSurveyResponseEventTest`
Expected: PASS (1 test).

- [ ] **Step 5: Run the existing SurveyResponseResource suite to confirm no regression**

Run: `php artisan test --filter=SurveyResponseResourceTest`
Expected: PASS — a response with no `survey_event_id` passes `null` as `$this->record->event`, which is `buildForSurvey()`'s existing no-op default from Task 3.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/SurveyResponseResource/Pages/EditSurveyResponse.php tests/Feature/EditSurveyResponseEventTest.php
git commit -m "feat: render only the response's own event sections in EditSurveyResponse"
```

---

### Task 9: Event and Subject columns/filters on `SurveyResponseResource`'s list

**Files:**
- Modify: `app/Filament/Resources/SurveyResponseResource.php` (table)
- Test: `tests/Feature/SurveyResponseListEventFilterTest.php`

**Interfaces:**
- Consumes: `SurveyResponse::event()` (Task 2), `SurveyResponse::subject()` (existing Phase 1 MorphTo).
- Produces: the list gains `Event`, `Instance #` columns; an `Event` `SelectFilter`; a `Subject` search `Filter` matching by name across `Facility`/`User`/`MentorshipClass` via `whereHasMorph`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResponseResource\Pages\ListSurveyResponses;
use App\Models\Facility;
use App\Models\Survey;
use App\Models\SurveyEvent;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyResponseListEventFilterTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'view_any_survey::response', 'guard_name' => 'web']);
        $user->givePermissionTo(['view_any_survey::response']);
        $this->actingAs($user);

        return $user;
    }

    public function test_event_filter_narrows_the_list_to_one_events_responses(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'LIST_EVENT_FILTER_TEST', 'name' => 'List Event Filter Test', 'is_active' => true]);
        $baseline = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'baseline', 'name' => 'Baseline', 'order' => 1]);
        $followup = SurveyEvent::create(['survey_id' => $survey->id, 'code' => 'followup', 'name' => 'Follow-up', 'order' => 2]);
        $baselineResponse = SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $baseline->id, 'status' => 'draft']);
        SurveyResponse::create(['survey_id' => $survey->id, 'survey_event_id' => $followup->id, 'status' => 'draft']);

        Livewire::test(ListSurveyResponses::class)
            ->filterTable('survey_event_id', $baseline->id)
            ->assertCanSeeTableRecords([$baselineResponse])
            ->assertCountTableRecords(1);
    }

    public function test_subject_filter_narrows_the_list_by_facility_name(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'LIST_SUBJECT_FILTER_TEST', 'name' => 'List Subject Filter Test', 'is_active' => true]);
        $target = Facility::factory()->create(['name' => 'Kitui District Hospital']);
        $other = Facility::factory()->create(['name' => 'Machakos Level 4']);
        $targetResponse = SurveyResponse::create(['survey_id' => $survey->id, 'subject_type' => Facility::class, 'subject_id' => $target->id, 'status' => 'draft']);
        SurveyResponse::create(['survey_id' => $survey->id, 'subject_type' => Facility::class, 'subject_id' => $other->id, 'status' => 'draft']);

        Livewire::test(ListSurveyResponses::class)
            ->filterTable('subject', 'Kitui')
            ->assertCanSeeTableRecords([$targetResponse])
            ->assertCountTableRecords(1);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyResponseListEventFilterTest`
Expected: FAIL — no `survey_event_id`/`subject` filters exist on the table yet.

- [ ] **Step 3: Update `SurveyResponseResource::table()`**

In `app/Filament/Resources/SurveyResponseResource.php`, replace `table()`:

```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('survey.name')->label('Survey')->badge()->color('primary')->searchable(),
            Tables\Columns\TextColumn::make('event.name')->label('Event')->badge()->color('gray')->placeholder('—'),
            Tables\Columns\TextColumn::make('event_instance_number')->label('Instance #')->alignCenter()->placeholder('—'),
            Tables\Columns\TextColumn::make('respondent_name')->label('Respondent')->searchable()->placeholder('—'),
            Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'submitted' ? 'success' : 'gray'),
            Tables\Columns\TextColumn::make('overall_percentage')->label('Score')->suffix('%')->placeholder('—'),
            Tables\Columns\TextColumn::make('submitted_at')->dateTime()->placeholder('—'),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('survey_id')->relationship('survey', 'name'),
            Tables\Filters\SelectFilter::make('survey_event_id')->label('Event')->relationship('event', 'name'),
            Tables\Filters\SelectFilter::make('status')->options(['draft' => 'Draft', 'submitted' => 'Submitted']),
            Tables\Filters\Filter::make('subject')
                ->form([
                    Forms\Components\TextInput::make('name')->label('Subject name contains'),
                ])
                ->query(function ($query, array $data) {
                    if (empty($data['name'])) {
                        return $query;
                    }

                    return $query->whereHasMorph(
                        'subject',
                        [\App\Models\Facility::class, \App\Models\User::class, \App\Models\MentorshipClass::class],
                        fn ($subQuery) => $subQuery->where('name', 'like', '%'.$data['name'].'%')
                    );
                }),
        ])
        ->actions([
            Tables\Actions\EditAction::make()->label('Fill / View'),
            Tables\Actions\DeleteAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
        ])
        ->defaultSort('created_at', 'desc');
}
```

Note: the `subject` filter test above calls `->filterTable('subject', 'Kitui')`, which Filament's testing helper maps onto the filter's form field named `name` — Livewire's `filterTable($filterName, $value)` sets `tableFilters.{$filterName}.name` when the filter form has a single field literally named the same as a plain value shorthand; confirm this resolves correctly when running Step 2/4, and if `filterTable` requires the nested array form instead (`->filterTable('subject', ['name' => 'Kitui'])`), use that form instead — match whichever the installed Filament testing helpers actually expect.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SurveyResponseListEventFilterTest`
Expected: PASS (2 tests). If Step 3's note applies, adjust the test's `filterTable` call accordingly and re-run.

- [ ] **Step 5: Run the existing SurveyResponseResource suite to confirm no regression**

Run: `php artisan test --filter=SurveyResponseResourceTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/SurveyResponseResource.php tests/Feature/SurveyResponseListEventFilterTest.php
git commit -m "feat: add Event/Instance columns and Event/Subject filters to response list"
```

---

### Task 10: Full regression pass and Shield permission sync

**Files:** none created — verification only.

**Interfaces:** none — confirms Tasks 1–9 compose correctly and nothing regressed.

- [ ] **Step 1: Run the complete test suite**

Run: `php artisan test`
Expected: PASS — every existing test (Phase 1 Survey* tests, facility-assessment tests, everything else) plus every new event-related test from Tasks 1–9.

- [ ] **Step 2: Confirm no new Filament resource means no new Shield permissions are needed**

`SurveyEvent` is managed entirely through relation managers nested under `SurveyResource` (`EventsRelationManager`), not a standalone Filament Resource — so it has no separate `view_any_survey::event`-style permission set to generate; access is governed by `SurveyResource`'s existing `view_any_survey`/`update_survey` permissions. Run `php artisan shield:generate --resource=SurveyResource --panel=admin --no-interaction` anyway, as a safety check that regenerating doesn't introduce anything unexpected (it should report Survey's permission set unchanged).

- [ ] **Step 3: Verify Pint formatting**

Run: `./vendor/bin/pint --test app/Models/SurveyEvent.php app/Models/Survey.php app/Models/SurveySection.php app/Models/SurveyResponse.php app/Services/SurveyFormBuilder.php app/Filament/Resources/SurveyResource.php app/Filament/Resources/SurveyResource/RelationManagers app/Filament/Resources/SurveyResponseResource.php app/Filament/Resources/SurveyResponseResource/Pages/CreateSurveyResponse.php app/Filament/Resources/SurveyResponseResource/Pages/EditSurveyResponse.php tests/Feature/SurveyEvent*.php tests/Feature/SurveySectionEventMappingTest.php tests/Feature/SurveyFormBuilderEventFilterTest.php tests/Feature/CreateSurveyResponseEventTest.php tests/Feature/EditSurveyResponseEventTest.php tests/Feature/SurveyResponseListEventFilterTest.php`
Expected: no formatting violations. If it reports fixable issues, run the same command without `--test` to apply them, then re-run Step 1.

- [ ] **Step 4: Manual smoke check**

Run: `php artisan tinker --execute="echo (new ReflectionMethod(App\Services\SurveyFormBuilder::class, 'buildForSurvey'))->getNumberOfParameters();"`
Expected: `3` — confirms the new `$event` parameter is live.

- [ ] **Step 5: Commit any formatting fixes**

```bash
git add -A
git commit -m "chore: pint formatting pass for Phase 2 event code"
```

(Skip this commit entirely if Step 3 reported no changes.)

---

## Phase 2 Definition of Done

- [ ] All 10 tasks' steps checked off, each with its own commit.
- [ ] `php artisan test` green, including every pre-existing test — event support is strictly additive to Phase 1 and the facility-assessment engine.
- [ ] An admin can, without writing code: add events (fixed and repeatable) to a survey, scope a section to a subset of those events, start a response for a specific event/subject (with instance numbers computed automatically for repeatable events), and filter the response list by event or subject to see one subject's longitudinal record.
- [ ] Phase 3 (auto-generated dashboards) remains fully unbuilt but architecturally unblocked — `SurveyEvent.order` is exactly what a trend-line x-axis needs, and per-response `SurveySectionScore` rows (unchanged by this phase) are exactly what a per-event score comparison will read.

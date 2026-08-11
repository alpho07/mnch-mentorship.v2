# Dynamic Survey Platform — Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a generic, REDCap-like survey/instrument builder (sections, all input types, conditional logic, scoring) that anyone can construct via a Filament resource, filled either by authenticated users in the admin panel or via a public token link — without a developer writing bespoke code per survey.

**Architecture:** Extract the subject-agnostic parts of `DynamicFormBuilder`/`DynamicScoringService` (field builders, layout, scoring algorithm) into a reusable `app/Services/FormKernel/` namespace, with zero behavior change to existing facility assessments. Build new `Survey`/`SurveySection`/`SurveyQuestion`/`SurveyResponse`/`SurveyQuestionResponse`/`SurveySectionScore` tables and models on top of that kernel, plus Filament CRUD and a public token-link flow.

**Tech Stack:** Laravel 12, Filament v3, PHPUnit, MySQL.

## Global Constraints

- Existing `Assessment*` tables, models, and Filament resources must NOT change behavior — every existing assessment test must stay green after the kernel extraction (Tasks 3–5). Run `php artisan test --filter=Assessment` (and the `DynamicFormBuilder*`/`GroupCompleteness` tests) after each kernel task and before committing.
- New kernel classes (`QuestionFieldBuilder`, `GroupedFieldRenderer`, `ScoringEngine`) take plain `Illuminate\Support\Collection`s and union-typed models (`AssessmentQuestion|SurveyQuestion`, etc.) — never a hardcoded `Assessment`/`Survey` type — and never persist to the database themselves. Persistence stays in the calling service (`DynamicFormBuilder`/`DynamicScoringService` for assessments, `SurveyFormBuilder`/`SurveyScoringService` for surveys).
- Model naming note (a plan-level refinement of the approved design spec): the response-instance model is `SurveyResponse` (table `survey_responses`), not `SurveyResponseSet` as sketched in the spec — this keeps Filament/Shield's permission-slug convention (`survey::response`, mirroring `Assessment` → `assessment`) predictable, and matches `Assessment`'s own one-word naming level. Nothing else about the spec's data model changes.
- `question_code` on `survey_questions` is globally unique (across all surveys), matching `assessment_questions.question_code`'s existing convention — this keeps `ConditionalLogicEvaluator`'s lookup pattern (`Model::where('question_code', $code)->first()`, no extra scoping) identical between both engines.
- Public-link access: a **survey**, not a response, owns the `access_token` (one shared open link per survey — `Survey.access_token`). Each visit to `/survey/{token}` that submits creates a brand-new `SurveyResponse` (`subject_id` null unless deliberately targeted — targeting a specific subject via link is explicitly deferred past Phase 1, see spec §2 non-goals discussion updated here). This resolves an internal inconsistency in the approved spec (§3.2 put `access_token` on the response-set row, §3.3 described it as the survey-level "Get link" action) in favor of the survey-level reading, which is the only one that supports "many anonymous respondents share one link" — the confirmed distribution requirement.
- Every new migration file uses today's date (`2026_08_12`) with an incrementing time suffix so they run in order after all existing migrations.
- FilamentShield permissions: after creating each new Filament resource, run `php artisan shield:generate --resource SurveyResource` (or the matching resource name) to generate its permissions, then grant them to whichever role(s) already hold `view_any_assessment::type`-equivalent permissions in tests via `Permission::firstOrCreate([...])` + `$user->givePermissionTo([...])`, matching the existing test convention (see `tests/Feature/CreateAssessmentTemplatePreloadTest.php`).
- Commit after every task using the existing repo's commit style (`feat:`/`fix:`/`test:` prefix, no marketing language).

---

### Task 1: Migrations for the Survey* schema

**Files:**
- Create: `database/migrations/2026_08_12_140000_create_surveys_table.php`
- Create: `database/migrations/2026_08_12_140100_create_survey_sections_table.php`
- Create: `database/migrations/2026_08_12_140200_create_survey_questions_table.php`
- Create: `database/migrations/2026_08_12_140300_create_survey_responses_table.php`
- Create: `database/migrations/2026_08_12_140400_create_survey_question_responses_table.php`
- Create: `database/migrations/2026_08_12_140500_create_survey_section_scores_table.php`

**Interfaces:**
- Produces: the six tables every later task's models/services/resources depend on. Column names below are exact — later tasks' `$fillable`/`$casts` arrays must match verbatim.

- [ ] **Step 1: Write `create_surveys_table`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version')->default('1.0');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(false);
            $table->string('access_token', 32)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};
```

- [ ] **Step 2: Write `create_survey_sections_table`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_scored')->default(true);
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['survey_id', 'code']);
            $table->index(['survey_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_sections');
    }
};
```

- [ ] **Step 3: Write `create_survey_questions_table`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_section_id')->constrained()->cascadeOnDelete();
            $table->string('question_code')->unique();
            $table->text('question_text');
            $table->text('help_text')->nullable();
            $table->string('question_type', 100);
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->json('validation_rules')->nullable();
            $table->json('display_conditions')->nullable();
            $table->json('requires_explanation_on')->nullable();
            $table->string('explanation_label')->default('Comments/Recommendations');
            $table->json('scoring_map')->nullable();
            $table->boolean('is_scored')->default(true);
            $table->integer('order')->default(0);
            $table->string('group')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['survey_section_id', 'order']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
    }
};
```

- [ ] **Step 4: Write `create_survey_responses_table`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('subject');
            $table->string('respondent_name')->nullable();
            $table->string('respondent_email')->nullable();
            $table->string('respondent_contact')->nullable();
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('overall_score', 8, 2)->nullable();
            $table->decimal('overall_percentage', 5, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['survey_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
```

- [ ] **Step 5: Write `create_survey_question_responses_table`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_question_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_question_id')->constrained()->cascadeOnDelete();
            $table->text('response_value')->nullable();
            $table->text('explanation')->nullable();
            $table->json('metadata')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['survey_response_id', 'survey_question_id'], 'survey_question_response_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_question_responses');
    }
};
```

- [ ] **Step 6: Write `create_survey_section_scores_table`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_section_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_section_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_score', 8, 2)->default(0);
            $table->decimal('max_score', 8, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->string('grade')->nullable();
            $table->integer('total_questions')->default(0);
            $table->integer('answered_questions')->default(0);
            $table->integer('skipped_questions')->default(0);
            $table->timestamps();

            $table->unique(['survey_response_id', 'survey_section_id'], 'survey_section_score_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_section_scores');
    }
};
```

- [ ] **Step 7: Run the migrations**

Run: `php artisan migrate`
Expected: all six tables created with no errors.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_12_1400*.php database/migrations/2026_08_12_1401*.php database/migrations/2026_08_12_1402*.php database/migrations/2026_08_12_1403*.php database/migrations/2026_08_12_1404*.php database/migrations/2026_08_12_1405*.php
git commit -m "feat: add Survey* schema for generic survey platform (Phase 1)"
```

---

### Task 2: Survey* Eloquent models

**Files:**
- Create: `app/Models/Survey.php`
- Create: `app/Models/SurveySection.php`
- Create: `app/Models/SurveyQuestion.php`
- Create: `app/Models/SurveyResponse.php`
- Create: `app/Models/SurveyQuestionResponse.php`
- Create: `app/Models/SurveySectionScore.php`
- Test: `tests/Feature/SurveyModelsTest.php`

**Interfaces:**
- Consumes: the six tables from Task 1.
- Produces: `Survey::sections()`, `Survey::responses()`, `Survey::scopeActive()`; `SurveySection::survey()`, `::questions()`, `::sectionScores()`, `::scopeActive()`, `::scopeScored()`, `::scopeOrdered()`; `SurveyQuestion::section()`, `::responses()`, same three scopes; `SurveyResponse::survey()`, `::subject()` (MorphTo), `::creator()`, `::questionResponses()`, `::sectionScores()`, `::scopeSubmitted()`, `::markSubmitted()`; `SurveyQuestionResponse::response()`, `::question()`; `SurveySectionScore::response()`, `::section()`. These exact method names are relied on by every later task (kernel wrappers, Filament resources, controller).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_survey_hierarchy_can_be_created_and_traversed(): void
    {
        $survey = Survey::create(['code' => 'TEST_SURVEY', 'name' => 'Test Survey', 'is_active' => true]);
        $section = SurveySection::create([
            'survey_id' => $survey->id, 'code' => 'general', 'name' => 'General', 'order' => 1,
        ]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'Q1', 'question_text' => 'Do you like it?',
            'question_type' => 'yes_no', 'scoring_map' => ['Yes' => 1, 'No' => 0],
        ]);

        $this->assertTrue($survey->fresh()->sections->first()->is($section));
        $this->assertTrue($section->fresh()->questions->first()->is($question));
        $this->assertTrue($question->fresh()->section->is($section));
    }

    public function test_survey_response_links_to_a_polymorphic_subject_or_none(): void
    {
        $survey = Survey::create(['code' => 'ANON_SURVEY', 'name' => 'Anonymous', 'is_active' => true]);

        $anonymous = SurveyResponse::create([
            'survey_id' => $survey->id, 'respondent_name' => 'Jane Doe', 'status' => 'draft',
        ]);

        $this->assertNull($anonymous->subject_type);
        $this->assertNull($anonymous->subject);

        $facility = Facility::factory()->create();
        $targeted = SurveyResponse::create([
            'survey_id' => $survey->id, 'subject_type' => Facility::class,
            'subject_id' => $facility->id, 'status' => 'draft',
        ]);

        $this->assertTrue($targeted->fresh()->subject->is($facility));
    }

    public function test_marking_a_response_submitted_stamps_timestamp(): void
    {
        $survey = Survey::create(['code' => 'SUBMIT_TEST', 'name' => 'Submit Test', 'is_active' => true]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);

        $response->markSubmitted();

        $this->assertSame('submitted', $response->fresh()->status);
        $this->assertNotNull($response->fresh()->submitted_at);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyModelsTest`
Expected: FAIL — classes `App\Models\Survey` etc. don't exist yet.

- [ ] **Step 3: Create `Survey`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'description', 'version', 'is_active', 'is_public',
        'access_token', 'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'metadata' => 'array',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(SurveySection::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
```

- [ ] **Step 4: Create `SurveySection`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveySection extends Model
{
    protected $fillable = [
        'survey_id', 'code', 'name', 'description', 'is_scored', 'order', 'is_active',
    ];

    protected $casts = [
        'is_scored' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class);
    }

    public function sectionScores(): HasMany
    {
        return $this->hasMany(SurveySectionScore::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeScored($query)
    {
        return $query->where('is_scored', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
```

- [ ] **Step 5: Create `SurveyQuestion`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyQuestion extends Model
{
    protected $fillable = [
        'survey_section_id', 'question_code', 'question_text', 'help_text',
        'question_type', 'options', 'is_required', 'validation_rules',
        'display_conditions', 'requires_explanation_on', 'explanation_label',
        'scoring_map', 'is_scored', 'order', 'group', 'is_active',
    ];

    protected $casts = [
        'options' => 'array',
        'validation_rules' => 'array',
        'display_conditions' => 'array',
        'requires_explanation_on' => 'array',
        'scoring_map' => 'array',
        'is_required' => 'boolean',
        'is_scored' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(SurveySection::class, 'survey_section_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyQuestionResponse::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeScored($query)
    {
        return $query->where('is_scored', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
```

- [ ] **Step 6: Create `SurveyResponse`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SurveyResponse extends Model
{
    protected $fillable = [
        'survey_id', 'subject_type', 'subject_id', 'respondent_name',
        'respondent_email', 'respondent_contact', 'status', 'submitted_at',
        'overall_score', 'overall_percentage', 'created_by',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'overall_score' => 'decimal:2',
        'overall_percentage' => 'decimal:2',
    ];

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questionResponses(): HasMany
    {
        return $this->hasMany(SurveyQuestionResponse::class);
    }

    public function sectionScores(): HasMany
    {
        return $this->hasMany(SurveySectionScore::class);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function markSubmitted(): void
    {
        $this->update(['status' => 'submitted', 'submitted_at' => now()]);
    }
}
```

- [ ] **Step 7: Create `SurveyQuestionResponse`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyQuestionResponse extends Model
{
    protected $fillable = [
        'survey_response_id', 'survey_question_id', 'response_value',
        'explanation', 'metadata', 'score',
    ];

    protected $casts = [
        'metadata' => 'array',
        'score' => 'float',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class, 'survey_response_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class);
    }
}
```

- [ ] **Step 8: Create `SurveySectionScore`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveySectionScore extends Model
{
    protected $fillable = [
        'survey_response_id', 'survey_section_id', 'total_score', 'max_score',
        'percentage', 'grade', 'total_questions', 'answered_questions', 'skipped_questions',
    ];

    protected $casts = [
        'total_score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'percentage' => 'decimal:2',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class, 'survey_response_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(SurveySection::class, 'survey_section_id');
    }
}
```

- [ ] **Step 9: Run test to verify it passes**

Run: `php artisan test --filter=SurveyModelsTest`
Expected: PASS (3 tests).

- [ ] **Step 10: Commit**

```bash
git add app/Models/Survey.php app/Models/SurveySection.php app/Models/SurveyQuestion.php app/Models/SurveyResponse.php app/Models/SurveyQuestionResponse.php app/Models/SurveySectionScore.php tests/Feature/SurveyModelsTest.php
git commit -m "feat: add Survey* Eloquent models"
```

---

### Task 3: Extract `QuestionFieldBuilder` kernel (behavior-preserving)

**Files:**
- Create: `app/Services/FormKernel/QuestionFieldBuilder.php`
- Modify: `app/Services/DynamicFormBuilder.php` (delegate to the kernel)
- Test: existing `tests/Feature/DynamicFormBuilder*.php`, `tests/Feature/GroupCompletenessTest.php` (must stay green, unmodified)

**Interfaces:**
- Consumes: `App\Models\AssessmentQuestion`, `App\Models\AssessmentQuestionResponse`, `App\Models\SurveyQuestion`, `App\Models\SurveyQuestionResponse`, `App\Models\Cadre` (from Task 2 and existing code).
- Produces: `QuestionFieldBuilder::buildField(AssessmentQuestion|SurveyQuestion $question, AssessmentQuestionResponse|SurveyQuestionResponse|null $response): mixed` — the single entry point every later task (`SurveyFormBuilder`, and the widened `DynamicFormBuilder`) calls. Returns `null` for a `question_type` it doesn't recognize (callers handle their own domain-specific types before falling back to this).

This is a **refactor of working code**, not new-feature TDD — the existing `DynamicFormBuilder*`/`GroupCompleteness` tests are the safety net. No new test file for this task; the step sequence is "move code, keep tests green," verified at Step 3.

- [ ] **Step 1: Confirm the safety net passes before touching anything**

Run: `php artisan test --filter=DynamicFormBuilder`
Run: `php artisan test --filter=GroupCompleteness`
Expected: PASS (baseline, before any refactor).

- [ ] **Step 2: Create `QuestionFieldBuilder` — move every `build*Field()` method verbatim except the two domain-specific ones**

Move `buildYesNoField`, `buildYesNoPartialField`, `normalizeExplanationArray`, `buildRepeaterField`, `buildTextField`, `buildShortTextField`, `buildNumberField`, `buildSelectField`, `buildCadreSelectField`, `buildRadioField`, `buildGroupCompletenessField`, `buildProportionField` out of `app/Services/DynamicFormBuilder.php` into the new file below — **identical bodies**, only these two changes: (a) `protected static function` → `public static function` (the dispatcher lives in this same file now, but `DynamicFormBuilder` still needs to reach some of them directly — see Task 6+ where new types are added here too), (b) every `AssessmentQuestion $question` parameter becomes `AssessmentQuestion|SurveyQuestion $question`, every `?AssessmentQuestionResponse $response` becomes `AssessmentQuestionResponse|SurveyQuestionResponse|null $response`. `buildUnitCapacityField` and `buildMortalityThreeMonthField` (and their `mortalityMonthKeys()` helper) are **not** moved — they're facility-assessment domain logic, stay in `DynamicFormBuilder`.

```php
<?php

namespace App\Services\FormKernel;

use App\Models\AssessmentQuestion;
use App\Models\AssessmentQuestionResponse;
use App\Models\Cadre;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use Filament\Forms;

/**
 * Subject-agnostic question-type-to-Filament-field builders, shared between
 * the facility-assessment engine (AssessmentQuestion) and the generic survey
 * engine (SurveyQuestion) — both models expose identical attribute names for
 * everything referenced here, so a union type is enough; no DTO/adapter
 * layer needed. Two question types stay out of this kernel because they're
 * facility-assessment domain logic, not generic form concerns: NBU/Paediatric
 * unit-capacity (INFRA_NBU/INFRA_PAED question codes) and the 3-month
 * mortality register — both remain in DynamicFormBuilder.
 */
class QuestionFieldBuilder
{
    /**
     * Single dispatch entry point. Returns null for any question_type this
     * kernel doesn't know — callers with their own domain-specific types
     * (DynamicFormBuilder's NBU/mortality handling) check those first and
     * only fall back to this for everything else.
     */
    public static function buildField(AssessmentQuestion|SurveyQuestion $question, AssessmentQuestionResponse|SurveyQuestionResponse|null $response): mixed
    {
        $fieldName = "question_response_{$question->id}";

        return match ($question->question_type) {
            'yes_no' => static::buildYesNoField($question, $fieldName, $response),
            'yes_no_partial' => static::buildYesNoPartialField($question, $fieldName, $response),
            'proportion' => static::buildProportionField($question, $fieldName, $response),
            'number' => static::buildNumberField($question, $fieldName, $response),
            'text' => static::buildTextField($question, $fieldName, $response),
            'select' => static::buildSelectField($question, $fieldName, $response),
            'radio' => static::buildRadioField($question, $fieldName, $response),
            'group_completeness' => static::buildGroupCompletenessField($question, $fieldName, $response),
            'repeater' => static::buildRepeaterField($question, $fieldName, $response),
            'cadre_select' => static::buildCadreSelectField($question, $fieldName, $response),
            'short_text' => static::buildShortTextField($question, $fieldName, $response),
            default => null,
        };
    }

    public static function buildYesNoField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return static::buildYesNoPartialField($question, $fieldName, $response, ['Yes', 'No']);
    }

    public static function buildYesNoPartialField(
        AssessmentQuestion|SurveyQuestion $question,
        string $fieldName,
        AssessmentQuestionResponse|SurveyQuestionResponse|null $response,
        array $options = ['Yes', 'No', 'Partially']
    ) {
        $field = Forms\Components\Radio::make($fieldName)
            ->label($question->question_text)
            ->options(array_combine($options, $options))
            ->required($question->is_required)
            ->inline()
            ->live()
            ->default($response?->response_value);

        if ($question->help_text) {
            $field->helperText($question->help_text);
        }

        $fields = [$field];

        $requiresExplanationOn = $question->requires_explanation_on ?? ['No', 'Partially'];
        $requiresExplanationOn = static::normalizeExplanationArray($requiresExplanationOn);

        $explanationField = Forms\Components\Textarea::make("{$fieldName}_explanation")
            ->label($question->explanation_label ?? 'Comments/Recommendations/Remarks')
            ->rows(3)
            ->placeholder('Please provide details, recommendations, or action plans...')
            ->visible(function (Forms\Get $get) use ($fieldName, $requiresExplanationOn) {
                $value = $get($fieldName);

                return in_array($value, $requiresExplanationOn, true);
            })
            ->default($response?->explanation);

        $fields[] = $explanationField;

        return Forms\Components\Group::make($fields)->columnSpanFull();
    }

    public static function normalizeExplanationArray($value): array
    {
        if (! $value) {
            return ['No', 'Partially'];
        }

        if (is_string($value) && str_starts_with(trim($value), '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_string($value)) {
            return array_map('trim', explode(',', $value));
        }

        if (! is_array($value)) {
            return [$value];
        }

        return $value;
    }

    public static function buildRepeaterField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        $columns = is_array($question->options) ? $question->options : [];

        $rows = [];
        if ($response?->response_value) {
            $decoded = json_decode($response->response_value, true);
            if (is_array($decoded)) {
                $rows = $decoded;
            }
        }

        $itemSchema = collect($columns)->map(function (array $column) {
            $key = $column['key'];
            $label = $column['label'];

            return match ($column['type'] ?? 'text') {
                'select' => Forms\Components\Select::make($key)
                    ->label($label)
                    ->options(array_combine($column['options'] ?? [], $column['options'] ?? [])),
                'date' => Forms\Components\DatePicker::make($key)->label($label),
                'number' => Forms\Components\TextInput::make($key)->label($label)->numeric(),
                default => Forms\Components\TextInput::make($key)->label($label),
            };
        })->all();

        return Forms\Components\Repeater::make($fieldName)
            ->label($question->question_text)
            ->schema($itemSchema)
            ->columns(max(count($columns), 1))
            ->default($rows)
            ->addActionLabel('Add row')
            ->reorderable(false)
            ->extraAttributes(['class' => 'aqs-repeater-table'])
            ->columnSpanFull();
    }

    public static function buildTextField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return Forms\Components\Textarea::make($fieldName)
            ->label($question->question_text)
            ->rows(3)
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text)
            ->columnSpanFull();
    }

    public static function buildShortTextField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return Forms\Components\TextInput::make($fieldName)
            ->label($question->question_text)
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text);
    }

    public static function buildNumberField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        $field = Forms\Components\TextInput::make($fieldName)
            ->label($question->question_text)
            ->numeric()
            ->integer()
            ->required($question->is_required)
            ->default($response?->response_value)
            ->minValue(0);

        if ($question->help_text) {
            $field->helperText($question->help_text);
        }

        if ($question->validation_rules) {
            $rules = is_string($question->validation_rules)
                ? json_decode($question->validation_rules, true)
                : $question->validation_rules;

            if (isset($rules['min'])) {
                $field->minValue($rules['min']);
            }
            if (isset($rules['max'])) {
                $field->maxValue($rules['max'])
                    ->helperText("Maximum value: {$rules['max']}");
            }
        }

        return $field;
    }

    public static function buildSelectField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        $options = $question->options;
        if (is_string($options)) {
            $options = json_decode($options, true) ?? [];
        }

        $optionsArray = is_array($options) ? array_combine($options, $options) : [];

        return Forms\Components\Select::make($fieldName)
            ->label($question->question_text)
            ->options($optionsArray)
            ->required($question->is_required)
            ->searchable()
            ->default($response?->response_value)
            ->helperText($question->help_text)
            ->live();
    }

    public static function buildCadreSelectField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return Forms\Components\Select::make($fieldName)
            ->label($question->question_text)
            ->options(fn () => Cadre::active()->ordered()->pluck('name', 'name'))
            ->required($question->is_required)
            ->searchable()
            ->default($response?->response_value)
            ->helperText($question->help_text)
            ->live();
    }

    public static function buildRadioField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        return Forms\Components\Radio::make($fieldName)
            ->label($question->question_text)
            ->options(array_combine($question->options ?? [], $question->options ?? []))
            ->required($question->is_required)
            ->default($response?->response_value)
            ->helperText($question->help_text);
    }

    public static function buildGroupCompletenessField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        $content = match ($response?->response_value) {
            'Yes' => '✓ Complete',
            'No' => '✗ Incomplete',
            default => 'Not yet calculated — save this section to compute',
        };

        return Forms\Components\Placeholder::make($fieldName)
            ->label($question->question_text)
            ->content($content)
            ->columnSpanFull();
    }

    public static function buildProportionField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
    {
        $metadata = $response?->metadata ?? [];
        $sampleSize = $question->validation_rules['sample_size'] ?? 10;

        return Forms\Components\Group::make([
            Forms\Components\Placeholder::make("{$fieldName}_label")
                ->label('')
                ->content($question->question_text)
                ->columnSpanFull(),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make("{$fieldName}_sample_size")
                    ->label('Sample Size')
                    ->numeric()
                    ->default($metadata['sample_size'] ?? $sampleSize)
                    ->disabled()
                    ->dehydrated(false)
                    ->hint("(Fixed at {$sampleSize})"),
                Forms\Components\TextInput::make("{$fieldName}_positive_count")
                    ->label('Positive Count')
                    ->numeric()
                    ->required()
                    ->default($metadata['positive_count'] ?? 0)
                    ->live()
                    ->minValue(0)
                    ->maxValue($sampleSize)
                    ->afterStateUpdated(function (Forms\Set $set, $state) use ($fieldName, $sampleSize) {
                        if (is_numeric($state) && $state >= 0 && $state <= $sampleSize) {
                            $proportion = ($state / $sampleSize) * 100;
                            $set("{$fieldName}_proportion", number_format($proportion, 1));
                        }
                    }),
                Forms\Components\TextInput::make("{$fieldName}_proportion")
                    ->label('Proportion (%)')
                    ->default($metadata['calculated_proportion'] ?? 0)
                    ->disabled()
                    ->dehydrated(false)
                    ->suffix('%'),
            ]),
        ])->columnSpanFull();
    }
}
```

- [ ] **Step 3: Rewrite `DynamicFormBuilder::buildFieldForQuestion` to delegate**

In `app/Services/DynamicFormBuilder.php`, replace the body of `buildFieldForQuestion` (the `match ($question->question_type) { ... }` block) so it checks the two domain-specific cases first, then falls back to the kernel:

```php
protected static function buildFieldForQuestion(AssessmentQuestion $question, ?AssessmentQuestionResponse $existingResponse): mixed
{
    $fieldName = "question_response_{$question->id}";

    if (in_array($question->question_code, ['INFRA_NBU', 'INFRA_PAED'])) {
        return static::buildUnitCapacityField($question, $fieldName, $existingResponse);
    }

    if ($question->question_type === 'mortality_three_month') {
        return static::buildMortalityThreeMonthField($question, $fieldName, $existingResponse);
    }

    $field = \App\Services\FormKernel\QuestionFieldBuilder::buildField($question, $existingResponse);

    $conditions = $question->display_conditions;

    if ($field && $conditions) {
        if (is_string($conditions)) {
            $conditions = json_decode($conditions, true);
        }

        if (is_array($conditions)) {
            $field = static::applyConditionalLogic($field, $conditions);
        }
    }

    return $field;
}
```

Then delete the now-moved `build*Field()` method bodies from `DynamicFormBuilder.php` (`buildYesNoField` through `buildProportionField`, everything moved in Step 2) — keep `buildUnitCapacityField`, `buildMortalityThreeMonthField`, `mortalityMonthKeys`, `saveResponses`, `applyConditionalLogic`, `buildForSection`, `renderRuns`, `buildGroupFieldset`, `normalizeColumnSpans`, `buildTableFieldset` exactly as they are (the layout methods move in Task 4, not this one).

- [ ] **Step 4: Run the safety net again**

Run: `php artisan test --filter=DynamicFormBuilder`
Run: `php artisan test --filter=GroupCompleteness`
Expected: PASS, identical results to Step 1 — confirms the refactor didn't change behavior.

- [ ] **Step 5: Commit**

```bash
git add app/Services/FormKernel/QuestionFieldBuilder.php app/Services/DynamicFormBuilder.php
git commit -m "refactor: extract QuestionFieldBuilder into shared FormKernel"
```

---

### Task 4: Extract `GroupedFieldRenderer` kernel (behavior-preserving)

**Files:**
- Create: `app/Services/FormKernel/GroupedFieldRenderer.php`
- Modify: `app/Services/DynamicFormBuilder.php` (delegate to the kernel)
- Test: existing `tests/Feature/DynamicFormBuilderGroupingTest.php` (must stay green, unmodified)

**Interfaces:**
- Produces: `GroupedFieldRenderer::renderRuns(array $runs): array`, `::buildGroupFieldset(string $label, array $fields)`, `::buildTableFieldset(string $title, array $rows)`, `::normalizeColumnSpans(array $fields): void` — pure layout functions over already-built Filament components and plain arrays/strings, zero model dependency, so no type-hint widening is needed at all (unlike Task 3). `SurveyFormBuilder` (Task 10) calls `renderRuns()` the same way `DynamicFormBuilder` does.

- [ ] **Step 1: Confirm the safety net passes**

Run: `php artisan test --filter=DynamicFormBuilderGroupingTest`
Expected: PASS (baseline).

- [ ] **Step 2: Create `GroupedFieldRenderer` — move the four methods verbatim (no signature changes needed)**

```php
<?php

namespace App\Services\FormKernel;

use Filament\Forms;

/**
 * Pure layout: turns a run-collapsed list of built fields into the actual
 * Filament components a section renders — grouped fieldsets, merged tables,
 * or the fields passed through untouched. No model dependency: operates
 * entirely on already-built Filament components and plain label/string
 * data, so it's identical for the assessment engine and the survey engine.
 */
class GroupedFieldRenderer
{
    /**
     * Second pass: turns each run into a rendered field/layout component.
     * Ungrouped runs (`group === null`) render their fields directly.
     * Table-row runs (see DynamicFormBuilder/SurveyFormBuilder's
     * buildGroupedField() convention) that share the same table title AND
     * appear consecutively merge into one table with a single shared header
     * row — everything else renders as its own component.
     */
    public static function renderRuns(array $runs): array
    {
        $fields = [];
        $tableBuffer = [];
        $tableTitle = null;

        $flushTable = function () use (&$fields, &$tableBuffer, &$tableTitle) {
            if ($tableBuffer !== []) {
                $fields[] = static::buildTableFieldset($tableTitle, $tableBuffer);
            }
            $tableBuffer = [];
            $tableTitle = null;
        };

        foreach ($runs as $run) {
            if ($run['group'] === null) {
                $flushTable();
                array_push($fields, ...$run['fields']);

                continue;
            }

            $parts = explode('|', $run['group']);

            if (count($parts) !== 3) {
                $flushTable();
                $fields[] = static::buildGroupFieldset($run['group'], $run['fields']);

                continue;
            }

            [$title, $rowLabelHeader, $rowLabel] = $parts;

            if ($tableTitle !== null && $tableTitle !== $title) {
                $flushTable();
            }

            $tableTitle = $title;
            $tableBuffer[] = ['header' => $rowLabelHeader, 'label' => $rowLabel, 'fields' => $run['fields']];
        }

        $flushTable();

        return $fields;
    }

    /**
     * A plain group: small groups (<=7 fields) lay out side by side,
     * table-style. Larger groups stay a single readable column.
     */
    public static function buildGroupFieldset(string $label, array $fields)
    {
        $columns = count($fields) <= 7 ? count($fields) : 1;

        if ($columns > 1) {
            static::normalizeColumnSpans($fields);
        }

        $fieldset = Forms\Components\Fieldset::make($label)
            ->schema($fields)
            ->columns(['default' => $columns, 'sm' => $columns, 'md' => $columns, 'lg' => $columns, 'xl' => $columns, '2xl' => $columns])
            ->columnSpanFull();

        if ($columns > 1) {
            $fieldset->extraAttributes(['class' => 'aqs-info-table']);
        }

        return $fieldset;
    }

    /**
     * Undoes any per-field-type forced ->columnSpanFull() so fields sit
     * side by side under a shared table/group header instead of stacking
     * full-width.
     */
    public static function normalizeColumnSpans(array $fields): void
    {
        foreach ($fields as $field) {
            if (method_exists($field, 'columnSpan')) {
                $field->columnSpan(1);
            }
        }
    }

    /**
     * Renders $rows as one table with a genuine, dedicated header row
     * followed by one full data row per entry in $rows.
     */
    public static function buildTableFieldset(string $title, array $rows)
    {
        $cells = [];

        $cells[] = Forms\Components\Placeholder::make('table_header_rowlabel_'.md5($title))
            ->label($rows[0]['header'])
            ->content('')
            ->extraAttributes(['class' => 'aqs-header-cell']);

        foreach ($rows[0]['fields'] as $field) {
            $cells[] = Forms\Components\Placeholder::make('table_header_col_'.md5($title).'_'.count($cells))
                ->label(method_exists($field, 'getLabel') ? $field->getLabel() : '')
                ->content('')
                ->extraAttributes(['class' => 'aqs-header-cell']);
        }

        foreach ($rows as $index => $row) {
            $rowLabelCell = Forms\Components\Placeholder::make('table_row_label_'.md5($title.$row['label'].$index))
                ->hiddenLabel()
                ->content($row['label']);

            $cells[] = $rowLabelCell;

            foreach ($row['fields'] as $field) {
                if (method_exists($field, 'hiddenLabel')) {
                    $field->hiddenLabel();
                }

                if (method_exists($field, 'columnSpan')) {
                    $field->columnSpan(1);
                }

                $cells[] = $field;
            }
        }

        $columnsPerRow = 1 + count($rows[0]['fields']);

        return Forms\Components\Fieldset::make($title)
            ->schema($cells)
            ->columns(['default' => $columnsPerRow, 'sm' => $columnsPerRow, 'md' => $columnsPerRow, 'lg' => $columnsPerRow, 'xl' => $columnsPerRow, '2xl' => $columnsPerRow])
            ->extraAttributes(['class' => 'aqs-data-table'])
            ->columnSpanFull();
    }
}
```

- [ ] **Step 3: Rewrite `DynamicFormBuilder`'s four moved methods to delegate**

Replace each of `DynamicFormBuilder::renderRuns()`, `::buildGroupFieldset()`, `::normalizeColumnSpans()`, `::buildTableFieldset()` with a one-line delegation:

```php
protected static function renderRuns(array $runs): array
{
    return \App\Services\FormKernel\GroupedFieldRenderer::renderRuns($runs);
}

protected static function buildGroupFieldset(string $label, array $fields)
{
    return \App\Services\FormKernel\GroupedFieldRenderer::buildGroupFieldset($label, $fields);
}

protected static function normalizeColumnSpans(array $fields): void
{
    \App\Services\FormKernel\GroupedFieldRenderer::normalizeColumnSpans($fields);
}

protected static function buildTableFieldset(string $title, array $rows)
{
    return \App\Services\FormKernel\GroupedFieldRenderer::buildTableFieldset($title, $rows);
}
```

- [ ] **Step 4: Run the safety net again**

Run: `php artisan test --filter=DynamicFormBuilderGroupingTest`
Expected: PASS, identical to Step 1.

- [ ] **Step 5: Commit**

```bash
git add app/Services/FormKernel/GroupedFieldRenderer.php app/Services/DynamicFormBuilder.php
git commit -m "refactor: extract GroupedFieldRenderer into shared FormKernel"
```

---

### Task 5: Extract `ScoringEngine` kernel (behavior-preserving, pure functions)

**Files:**
- Create: `app/Services/FormKernel/ScoringEngine.php`
- Modify: `app/Services/DynamicScoringService.php` (delegate to the kernel; stays responsible for all persistence)
- Test: existing `tests/Feature/GroupCompletenessTest.php` and any assessment-scoring tests under `tests/Feature/` (must stay green, unmodified)

**Interfaces:**
- Produces: `ScoringEngine::resolveGroupCompletenessResponses(Collection $questions, Collection $responsesByQuestionId): array` (returns `[['question_id'=>int,'response_value'=>'Yes'|'No','score'=>1|0], ...]` — caller persists each); `::excludeConditionallyHiddenQuestions(Collection $questions, array $responseValuesByQuestionCode): Collection`; `::calculateSectionScore(Collection $questions, Collection $responses): array` (returns `['total_score','max_score','percentage','grade','total_questions','answered_questions','skipped_questions']`); `::calculateOverallScore(Collection $sectionScores): array` (returns `['total_score','percentage','grade']`); `::calculateGrade(float $percentage): string`. None of these touch the database — every input is an already-fetched `Collection`, every output is a plain array the caller persists into its own score table.

- [ ] **Step 1: Confirm the safety net passes**

Run: `php artisan test --filter=GroupCompleteness`
Expected: PASS (baseline).

- [ ] **Step 2: Create `ScoringEngine`**

```php
<?php

namespace App\Services\FormKernel;

use App\Services\ConditionalLogicEvaluator;
use Illuminate\Support\Collection;

/**
 * Pure scoring algorithm — section/overall scoring, group-completeness
 * resolution, and conditional exclusion — with zero database access and no
 * dependency on which concrete Assessment*/Survey* models the caller uses.
 * Every method takes already-fetched Collections and returns plain arrays;
 * the caller (DynamicScoringService for assessments, SurveyScoringService
 * for surveys) is responsible for building those inputs from its own tables
 * and persisting the returned data into its own score table. This split is
 * what lets both engines share the identical scoring rules without sharing
 * a database schema.
 */
class ScoringEngine
{
    /**
     * A `group_completeness` question's value is derived from its scored
     * sibling questions (same `group`, same section, excluding other
     * group_completeness questions): complete (1/"Yes") iff every sibling
     * currently has a response scored at that sibling's own maximum
     * possible score; incomplete (0/"No") otherwise, including when a
     * sibling is unanswered. $responsesByQuestionId must already be keyed
     * by question id and cover every non-group_completeness question in
     * $questions.
     *
     * @return array<int, array{question_id: int, response_value: string, score: int}>
     */
    public static function resolveGroupCompletenessResponses(Collection $questions, Collection $responsesByQuestionId): array
    {
        $completenessQuestions = $questions->where('question_type', 'group_completeness');

        if ($completenessQuestions->isEmpty()) {
            return [];
        }

        $updates = [];

        foreach ($completenessQuestions as $completenessQuestion) {
            $siblings = $questions->filter(fn ($q) => $q->group === $completenessQuestion->group
                && $q->id !== $completenessQuestion->id
                && $q->question_type !== 'group_completeness');

            if ($siblings->isEmpty()) {
                continue;
            }

            $allComplete = $siblings->every(function ($sibling) use ($responsesByQuestionId) {
                $response = $responsesByQuestionId->get($sibling->id);

                if (! $response || $response->score === null) {
                    return false;
                }

                $maxForSibling = ! empty($sibling->scoring_map) ? max($sibling->scoring_map) : 1;

                return (float) $response->score >= (float) $maxForSibling;
            });

            $updates[] = [
                'question_id' => $completenessQuestion->id,
                'response_value' => $allComplete ? 'Yes' : 'No',
                'score' => $allComplete ? 1 : 0,
            ];
        }

        return $updates;
    }

    /**
     * Excludes any scored question whose display_conditions evaluate to
     * "hidden" given the already-submitted responses — a question that
     * wouldn't have been shown on the form shouldn't count toward the
     * section's score. $responseValuesByQuestionCode maps question_code =>
     * response_value across the WHOLE survey/assessment (not just the
     * current section), since a condition can reference a question
     * elsewhere.
     */
    public static function excludeConditionallyHiddenQuestions(Collection $questions, array $responseValuesByQuestionCode): Collection
    {
        $conditional = $questions->filter(fn ($q) => ! empty($q->display_conditions));

        if ($conditional->isEmpty()) {
            return $questions;
        }

        $valueResolver = fn (string $questionCode) => $responseValuesByQuestionCode[$questionCode] ?? null;

        return $questions->filter(function ($question) use ($valueResolver) {
            if (empty($question->display_conditions)) {
                return true;
            }

            return ConditionalLogicEvaluator::isVisible($question->display_conditions, $valueResolver);
        });
    }

    /**
     * @return array{total_score: float, max_score: int, percentage: float, grade: string, total_questions: int, answered_questions: int, skipped_questions: int}
     */
    public static function calculateSectionScore(Collection $questions, Collection $responses): array
    {
        $totalQuestions = $questions->count();

        if ($totalQuestions === 0) {
            return [
                'total_score' => 0, 'max_score' => 0, 'percentage' => 0,
                'grade' => static::calculateGrade(0), 'total_questions' => 0,
                'answered_questions' => 0, 'skipped_questions' => 0,
            ];
        }

        $totalScore = $responses->whereNotNull('score')->sum('score');
        $maxScore = $totalQuestions;
        $answeredQuestions = $responses->whereNotNull('response_value')->count();
        $skippedQuestions = $totalQuestions - $answeredQuestions;
        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        return [
            'total_score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => $percentage,
            'grade' => static::calculateGrade($percentage),
            'total_questions' => $totalQuestions,
            'answered_questions' => $answeredQuestions,
            'skipped_questions' => $skippedQuestions,
        ];
    }

    /**
     * @return array{total_score: float, percentage: float, grade: ?string}
     */
    public static function calculateOverallScore(Collection $sectionScores): array
    {
        if ($sectionScores->isEmpty()) {
            return ['total_score' => 0, 'percentage' => 0, 'grade' => null];
        }

        $totalScore = $sectionScores->sum('total_score');
        $overallPercentage = round($sectionScores->avg('percentage'), 2);

        return [
            'total_score' => $totalScore,
            'percentage' => $overallPercentage,
            'grade' => static::calculateGrade($overallPercentage),
        ];
    }

    /**
     * Grade thresholds: ≥80% = green, ≥50% = yellow, <50% = red.
     */
    public static function calculateGrade(float $percentage): string
    {
        if ($percentage >= 80) {
            return 'green';
        }
        if ($percentage >= 50) {
            return 'yellow';
        }

        return 'red';
    }
}
```

- [ ] **Step 3: Rewrite `DynamicScoringService` to orchestrate + persist, delegating computation to the kernel**

Replace `recalculateSectionScore`, `resolveGroupCompletenessResponses`, `excludeConditionallyHiddenQuestions`, `recalculateOverallScore`, and `calculateGrade` with:

```php
public static function recalculateSectionScore(int $assessmentId, int $sectionId): void
{
    $section = AssessmentSection::findOrFail($sectionId);

    if (! $section->is_scored) {
        return;
    }

    $questions = $section->questions()
        ->active()
        ->scored()
        ->where('question_type', '!=', 'mortality_three_month')
        ->get();

    self::resolveGroupCompletenessResponses($assessmentId, $questions);

    $responsesByCode = AssessmentQuestionResponse::query()
        ->where('assessment_id', $assessmentId)
        ->join('assessment_questions', 'assessment_questions.id', '=', 'assessment_question_responses.assessment_question_id')
        ->pluck('assessment_question_responses.response_value', 'assessment_questions.question_code')
        ->all();

    $questions = \App\Services\FormKernel\ScoringEngine::excludeConditionallyHiddenQuestions($questions, $responsesByCode);

    if ($questions->isEmpty()) {
        return;
    }

    $responses = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
        ->whereIn('assessment_question_id', $questions->pluck('id'))
        ->get();

    $scoreData = \App\Services\FormKernel\ScoringEngine::calculateSectionScore($questions, $responses);

    AssessmentSectionScore::updateOrCreate(
        ['assessment_id' => $assessmentId, 'assessment_section_id' => $sectionId],
        $scoreData
    );

    self::recalculateOverallScore($assessmentId);
}

private static function resolveGroupCompletenessResponses(int $assessmentId, $questions): void
{
    $completenessQuestions = $questions->where('question_type', 'group_completeness');

    if ($completenessQuestions->isEmpty()) {
        return;
    }

    $siblingIds = $questions->where('question_type', '!=', 'group_completeness')->pluck('id');

    $responsesByQuestionId = AssessmentQuestionResponse::where('assessment_id', $assessmentId)
        ->whereIn('assessment_question_id', $siblingIds)
        ->get()
        ->keyBy('assessment_question_id');

    $updates = \App\Services\FormKernel\ScoringEngine::resolveGroupCompletenessResponses($questions, $responsesByQuestionId);

    foreach ($updates as $update) {
        AssessmentQuestionResponse::updateOrCreate(
            ['assessment_id' => $assessmentId, 'assessment_question_id' => $update['question_id']],
            ['response_value' => $update['response_value'], 'score' => $update['score']]
        );
    }
}

public static function recalculateOverallScore(int $assessmentId): void
{
    $sectionScores = AssessmentSectionScore::where('assessment_id', $assessmentId)->get();

    if ($sectionScores->isEmpty()) {
        return;
    }

    $overall = \App\Services\FormKernel\ScoringEngine::calculateOverallScore($sectionScores);

    Assessment::where('id', $assessmentId)->update([
        'overall_score' => $overall['total_score'],
        'overall_percentage' => $overall['percentage'],
        'overall_grade' => $overall['grade'],
    ]);
}

protected static function calculateGrade(float $percentage): string
{
    return \App\Services\FormKernel\ScoringEngine::calculateGrade($percentage);
}
```

Delete the old bodies these replace (the old `excludeConditionallyHiddenQuestions` method is fully removed, not just its body — its logic now lives only in `ScoringEngine`). Leave every other method in `DynamicScoringService` (`isSectionComplete`, `getSectionResponses`, `getAssessmentSummary`, `getAssessmentStats`, `recalculateAllSections`) untouched — they already call `self::calculateGrade()`, which still works via the one-line delegator above.

- [ ] **Step 4: Run the safety net again**

Run: `php artisan test --filter=GroupCompleteness`
Run: `php artisan test`
Expected: full suite PASS — this is the last kernel-extraction task, so this is the final confirmation that all three extractions (Tasks 3–5) are fully behavior-preserving.

- [ ] **Step 5: Commit**

```bash
git add app/Services/FormKernel/ScoringEngine.php app/Services/DynamicScoringService.php
git commit -m "refactor: extract ScoringEngine into shared FormKernel"
```

---

### Task 6: New question types — `date`, `datetime`, `email`, `phone`, `checkbox`, `rating`

**Files:**
- Modify: `app/Services/FormKernel/QuestionFieldBuilder.php` (add six `build*Field()` methods + dispatcher cases)
- Test: `tests/Feature/QuestionFieldBuilderNewTypesTest.php`

**Interfaces:**
- Consumes: `QuestionFieldBuilder::buildField()` from Task 3.
- Produces: `question_type` values `date`, `datetime`, `email`, `phone`, `checkbox`, `rating` now resolve to real Filament fields instead of `buildField()`'s `default => null`. `checkbox` stores/reads its answer as a JSON-encoded array in `response_value` (mirrors how `repeater` already does this) — `SurveyFormBuilder::saveResponses()` (Task 10) must `json_encode` the submitted array for this type, same as it does for `repeater`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use App\Services\FormKernel\QuestionFieldBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionFieldBuilderNewTypesTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuestion(string $type, array $overrides = []): SurveyQuestion
    {
        $survey = Survey::create(['code' => 'NEWTYPES_'.$type, 'name' => 'New Types', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);

        return SurveyQuestion::create(array_merge([
            'survey_section_id' => $section->id,
            'question_code' => 'NT_'.strtoupper($type),
            'question_text' => "A {$type} question",
            'question_type' => $type,
        ], $overrides));
    }

    public function test_date_field_builds_a_date_picker(): void
    {
        $field = QuestionFieldBuilder::buildField($this->makeQuestion('date'), null);

        $this->assertInstanceOf(\Filament\Forms\Components\DatePicker::class, $field);
    }

    public function test_datetime_field_builds_a_datetime_picker(): void
    {
        $field = QuestionFieldBuilder::buildField($this->makeQuestion('datetime'), null);

        $this->assertInstanceOf(\Filament\Forms\Components\DateTimePicker::class, $field);
    }

    public function test_email_field_enables_email_validation(): void
    {
        $field = QuestionFieldBuilder::buildField($this->makeQuestion('email'), null);

        $this->assertSame('email', $field->getType());
    }

    public function test_phone_field_enables_tel_validation(): void
    {
        $field = QuestionFieldBuilder::buildField($this->makeQuestion('phone'), null);

        $this->assertSame('tel', $field->getType());
    }

    public function test_checkbox_field_builds_a_checkbox_list_with_options(): void
    {
        $question = $this->makeQuestion('checkbox', ['options' => ['Red', 'Green', 'Blue']]);

        $field = QuestionFieldBuilder::buildField($question, null);

        $this->assertInstanceOf(\Filament\Forms\Components\CheckboxList::class, $field);
        $this->assertSame(['Red' => 'Red', 'Green' => 'Green', 'Blue' => 'Blue'], $field->getOptions());
    }

    public function test_rating_field_builds_options_up_to_configured_max(): void
    {
        $question = $this->makeQuestion('rating', ['validation_rules' => ['max' => 3]]);

        $field = QuestionFieldBuilder::buildField($question, null);

        $this->assertInstanceOf(\Filament\Forms\Components\Radio::class, $field);
        $this->assertSame(['1' => '1', '2' => '2', '3' => '3'], $field->getOptions());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=QuestionFieldBuilderNewTypesTest`
Expected: FAIL — `buildField()` returns `null` for all six types (assertInstanceOf on null fails).

- [ ] **Step 3: Add the six field builders + dispatcher cases**

In `app/Services/FormKernel/QuestionFieldBuilder.php`, add these cases to the `match` in `buildField()`:

```php
'date' => static::buildDateField($question, $fieldName, $response),
'datetime' => static::buildDatetimeField($question, $fieldName, $response),
'email' => static::buildEmailField($question, $fieldName, $response),
'phone' => static::buildPhoneField($question, $fieldName, $response),
'checkbox' => static::buildCheckboxField($question, $fieldName, $response),
'rating' => static::buildRatingField($question, $fieldName, $response),
```

And add the six methods:

```php
public static function buildDateField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
{
    return Forms\Components\DatePicker::make($fieldName)
        ->label($question->question_text)
        ->native(false)
        ->required($question->is_required)
        ->default($response?->response_value)
        ->helperText($question->help_text);
}

public static function buildDatetimeField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
{
    return Forms\Components\DateTimePicker::make($fieldName)
        ->label($question->question_text)
        ->native(false)
        ->required($question->is_required)
        ->default($response?->response_value)
        ->helperText($question->help_text);
}

public static function buildEmailField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
{
    return Forms\Components\TextInput::make($fieldName)
        ->label($question->question_text)
        ->email()
        ->required($question->is_required)
        ->default($response?->response_value)
        ->helperText($question->help_text);
}

public static function buildPhoneField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
{
    return Forms\Components\TextInput::make($fieldName)
        ->label($question->question_text)
        ->tel()
        ->required($question->is_required)
        ->default($response?->response_value)
        ->helperText($question->help_text);
}

/**
 * Multi-select. Selected options round-trip as a JSON-encoded array in
 * response_value, the same convention `repeater` already uses — see
 * SurveyFormBuilder::saveResponses() for the write side.
 */
public static function buildCheckboxField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
{
    $options = $question->options;
    if (is_string($options)) {
        $options = json_decode($options, true) ?? [];
    }
    $optionsArray = is_array($options) ? array_combine($options, $options) : [];

    $selected = [];
    if ($response?->response_value) {
        $decoded = json_decode($response->response_value, true);
        if (is_array($decoded)) {
            $selected = $decoded;
        }
    }

    return Forms\Components\CheckboxList::make($fieldName)
        ->label($question->question_text)
        ->options($optionsArray)
        ->required($question->is_required)
        ->default($selected)
        ->helperText($question->help_text)
        ->columns(2);
}

/**
 * A numeric scale (1..N, N from validation_rules.max, default 5) rendered
 * as inline radio buttons — plain integer response_value, scored the same
 * way any other radio/select question is (via scoring_map).
 */
public static function buildRatingField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
{
    $max = $question->validation_rules['max'] ?? 5;
    $options = collect(range(1, $max))->mapWithKeys(fn ($n) => [(string) $n => (string) $n])->all();

    return Forms\Components\Radio::make($fieldName)
        ->label($question->question_text)
        ->options($options)
        ->inline()
        ->required($question->is_required)
        ->default($response?->response_value)
        ->helperText($question->help_text);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=QuestionFieldBuilderNewTypesTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/FormKernel/QuestionFieldBuilder.php tests/Feature/QuestionFieldBuilderNewTypesTest.php
git commit -m "feat: add date/datetime/email/phone/checkbox/rating question types"
```

---

### Task 7: New question types — `file_upload`, `signature`

**Files:**
- Modify: `app/Services/FormKernel/QuestionFieldBuilder.php` (add two `build*Field()` methods + dispatcher cases)
- Create: `resources/views/filament/forms/components/signature-pad.blade.php`
- Test: `tests/Feature/QuestionFieldBuilderFileSignatureTest.php`

**Interfaces:**
- Consumes: the `public` disk (`config/filesystems.php`, already `visibility => public`) — no new disk needed.
- Produces: `question_type` values `file_upload` (stores the uploaded file's path string in `response_value`, via Filament's own `FileUpload` state — no custom save-side handling needed) and `signature` (stores a base64 PNG data URI string in `response_value`, captured via a self-contained canvas — no new npm/JS dependency).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use App\Services\FormKernel\QuestionFieldBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionFieldBuilderFileSignatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeQuestion(string $type): SurveyQuestion
    {
        $survey = Survey::create(['code' => 'FS_'.$type, 'name' => 'File/Signature', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);

        return SurveyQuestion::create([
            'survey_section_id' => $section->id,
            'question_code' => 'FS_'.strtoupper($type),
            'question_text' => "A {$type} question",
            'question_type' => $type,
        ]);
    }

    public function test_file_upload_field_uses_the_public_disk(): void
    {
        $field = QuestionFieldBuilder::buildField($this->makeQuestion('file_upload'), null);

        $this->assertInstanceOf(\Filament\Forms\Components\FileUpload::class, $field);
        $this->assertSame('public', $field->getDiskName());
        $this->assertSame('survey-uploads', $field->getDirectory());
    }

    public function test_signature_field_renders_the_signature_pad_view(): void
    {
        $field = QuestionFieldBuilder::buildField($this->makeQuestion('signature'), null);

        $this->assertInstanceOf(\Filament\Forms\Components\ViewField::class, $field);
        $this->assertSame('filament.forms.components.signature-pad', $field->getView());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=QuestionFieldBuilderFileSignatureTest`
Expected: FAIL — both types resolve to `null`.

- [ ] **Step 3: Add dispatcher cases + the two field builders**

Add to the `match` in `QuestionFieldBuilder::buildField()`:

```php
'file_upload' => static::buildFileUploadField($question, $fieldName, $response),
'signature' => static::buildSignatureField($question, $fieldName, $response),
```

Add the two methods:

```php
public static function buildFileUploadField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
{
    return Forms\Components\FileUpload::make($fieldName)
        ->label($question->question_text)
        ->disk('public')
        ->directory('survey-uploads')
        ->required($question->is_required)
        ->default($response?->response_value)
        ->helperText($question->help_text);
}

/**
 * A hand-drawn signature captured on an inline <canvas> (self-contained —
 * no new JS package), round-tripped as a base64 PNG data URI in
 * response_value via the same convention any other free-text-shaped answer
 * uses. See resources/views/filament/forms/components/signature-pad.blade.php.
 */
public static function buildSignatureField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
{
    return Forms\Components\ViewField::make($fieldName)
        ->label($question->question_text)
        ->view('filament.forms.components.signature-pad')
        ->required($question->is_required)
        ->default($response?->response_value)
        ->helperText($question->help_text)
        ->columnSpanFull();
}
```

- [ ] **Step 4: Create the signature pad view**

```blade
@php
    $statePath = $getStatePath();
@endphp
<div
    x-data="{
        state: $wire.entangle('{{ $statePath }}'),
        drawing: false,
        ctx: null,
        init() {
            const canvas = this.$refs.canvas;
            this.ctx = canvas.getContext('2d');
            this.ctx.strokeStyle = '#111827';
            this.ctx.lineWidth = 2;
            if (this.state) {
                const img = new Image();
                img.onload = () => this.ctx.drawImage(img, 0, 0);
                img.src = this.state;
            }
        },
        start(e) {
            this.drawing = true;
            const rect = this.$refs.canvas.getBoundingClientRect();
            this.ctx.beginPath();
            this.ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
        },
        move(e) {
            if (!this.drawing) return;
            const rect = this.$refs.canvas.getBoundingClientRect();
            this.ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
            this.ctx.stroke();
        },
        stop() {
            if (!this.drawing) return;
            this.drawing = false;
            this.state = this.$refs.canvas.toDataURL('image/png');
        },
        clear() {
            this.ctx.clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
            this.state = null;
        },
    }"
    class="fi-signature-pad"
>
    <canvas
        x-ref="canvas"
        width="400"
        height="150"
        style="border:1px solid #d1d5db;border-radius:0.5rem;touch-action:none;background:#fff;max-width:100%;"
        @mousedown="start" @mousemove="move" @mouseup="stop" @mouseleave="stop"
    ></canvas>
    <button type="button" @click="clear" class="fi-btn fi-btn-size-sm" style="margin-top:0.5rem;">Clear signature</button>
</div>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=QuestionFieldBuilderFileSignatureTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/FormKernel/QuestionFieldBuilder.php resources/views/filament/forms/components/signature-pad.blade.php tests/Feature/QuestionFieldBuilderFileSignatureTest.php
git commit -m "feat: add file_upload and signature question types"
```

---

### Task 8: New question type — `matrix` (a Likert-style grid)

**Files:**
- Modify: `app/Services/FormKernel/QuestionFieldBuilder.php` (add `buildMatrixField()` + dispatcher case)
- Test: `tests/Feature/QuestionFieldBuilderMatrixTest.php`

**Interfaces:**
- Consumes: `$question->options` for a `matrix` question is structured differently from every other type's flat array — `{"columns": ["Disagree","Neutral","Agree"], "rows": [{"key":"clarity","label":"The instructions were clear"}, {"key":"pace","label":"The pace was right"}]}`. One shared radio-per-row grid, all rows sharing the same `columns` option set.
- Produces: `response_value` for a `matrix` answer is a JSON object `{rowKey: selectedColumnValue}` — the same "JSON object keyed by sub-field" convention `mortality_three_month` already established. **Task 10 (`SurveyFormBuilder::saveResponses`) depends on this**: it must read `$question->options['rows']` to know which `{$fieldName}_{$rowKey}` sub-fields to collect on save, mirroring how `DynamicFormBuilder::saveResponses` already collects `mortality_three_month`'s sub-fields via `mortalityMonthKeys()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Services\FormKernel\QuestionFieldBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionFieldBuilderMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function makeMatrixQuestion(): SurveyQuestion
    {
        $survey = Survey::create(['code' => 'MATRIX_TEST', 'name' => 'Matrix', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);

        return SurveyQuestion::create([
            'survey_section_id' => $section->id,
            'question_code' => 'MATRIX_Q1',
            'question_text' => 'Rate the training session',
            'question_type' => 'matrix',
            'options' => [
                'columns' => ['Disagree', 'Neutral', 'Agree'],
                'rows' => [
                    ['key' => 'clarity', 'label' => 'The instructions were clear'],
                    ['key' => 'pace', 'label' => 'The pace was right'],
                ],
            ],
        ]);
    }

    public function test_matrix_field_builds_one_radio_group_per_row_sharing_the_column_options(): void
    {
        $field = QuestionFieldBuilder::buildField($this->makeMatrixQuestion(), null);

        $this->assertInstanceOf(\Filament\Forms\Components\Group::class, $field);

        $radios = collect($field->getChildComponents())
            ->filter(fn ($c) => $c instanceof \Filament\Forms\Components\Radio);

        $this->assertCount(2, $radios);
        $this->assertTrue($radios->every(fn ($r) => $r->getOptions() === ['Disagree' => 'Disagree', 'Neutral' => 'Neutral', 'Agree' => 'Agree']));
    }

    public function test_matrix_field_prefills_existing_per_row_answers(): void
    {
        $question = $this->makeMatrixQuestion();
        $survey = Survey::find($question->section->survey_id);
        $surveyResponse = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);
        $existing = SurveyQuestionResponse::create([
            'survey_response_id' => $surveyResponse->id,
            'survey_question_id' => $question->id,
            'response_value' => json_encode(['clarity' => 'Agree', 'pace' => 'Neutral']),
        ]);

        $field = QuestionFieldBuilder::buildField($question, $existing);

        $clarityRadio = collect($field->getChildComponents())
            ->first(fn ($c) => $c instanceof \Filament\Forms\Components\Radio && str_ends_with($c->getName(), '_clarity'));

        $this->assertSame('Agree', $clarityRadio->getDefaultState());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=QuestionFieldBuilderMatrixTest`
Expected: FAIL — `matrix` resolves to `null`.

- [ ] **Step 3: Add the dispatcher case + `buildMatrixField()`**

Add to the `match` in `buildField()`:

```php
'matrix' => static::buildMatrixField($question, $fieldName, $response),
```

```php
/**
 * A Likert-style grid: one radio group per row (question->options.rows),
 * all sharing the same column option set (question->options.columns).
 * response_value is a JSON object {rowKey: selectedColumnValue} — the same
 * "JSON object keyed by sub-field" convention mortality_three_month uses.
 */
public static function buildMatrixField(AssessmentQuestion|SurveyQuestion $question, string $fieldName, AssessmentQuestionResponse|SurveyQuestionResponse|null $response)
{
    $config = is_array($question->options) ? $question->options : [];
    $columns = $config['columns'] ?? [];
    $rows = $config['rows'] ?? [];
    $columnOptions = array_combine($columns, $columns);

    $existing = [];
    if ($response?->response_value) {
        $decoded = json_decode($response->response_value, true);
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }

    $fields = [
        Forms\Components\Placeholder::make("{$fieldName}_label")
            ->label('')
            ->content($question->question_text)
            ->columnSpanFull(),
    ];

    foreach ($rows as $row) {
        $rowKey = $row['key'];
        $fields[] = Forms\Components\Radio::make("{$fieldName}_{$rowKey}")
            ->label($row['label'])
            ->options($columnOptions)
            ->inline()
            ->required($question->is_required)
            ->default($existing[$rowKey] ?? null);
    }

    if ($question->help_text) {
        $fields[0]->content($question->question_text."\n".$question->help_text);
    }

    return Forms\Components\Group::make($fields)->columnSpanFull();
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=QuestionFieldBuilderMatrixTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/FormKernel/QuestionFieldBuilder.php tests/Feature/QuestionFieldBuilderMatrixTest.php
git commit -m "feat: add matrix (Likert grid) question type"
```

---

### Task 9: `SurveyScoringService` — thin wrapper over `ScoringEngine` for Survey* models

**Files:**
- Create: `app/Services/SurveyScoringService.php`
- Test: `tests/Feature/SurveyScoringServiceTest.php`

**Interfaces:**
- Consumes: `App\Services\FormKernel\ScoringEngine` (Task 5), `SurveySection`/`SurveyQuestion`/`SurveyQuestionResponse`/`SurveyResponse`/`SurveySectionScore` (Task 2).
- Produces: `SurveyScoringService::recalculateSectionScore(int $surveyResponseId, int $surveySectionId): void`, `::recalculateOverallScore(int $surveyResponseId): void` — the exact two entry points Task 10's `SurveyFormBuilder::saveResponses()` calls after persisting a section's answers, mirroring `DynamicScoringService`'s public surface for assessments.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\SurveySectionScore;
use App\Services\SurveyScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_score_is_calculated_from_scored_question_responses(): void
    {
        $survey = Survey::create(['code' => 'SCORE_TEST', 'name' => 'Score Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1, 'is_scored' => true]);
        $q1 = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'SCORE_Q1', 'question_text' => 'Q1',
            'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0],
        ]);
        $q2 = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'SCORE_Q2', 'question_text' => 'Q2',
            'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0],
        ]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);
        SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $q1->id, 'response_value' => 'Yes', 'score' => 1]);
        SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $q2->id, 'response_value' => 'No', 'score' => 0]);

        SurveyScoringService::recalculateSectionScore($response->id, $section->id);

        $sectionScore = SurveySectionScore::where('survey_response_id', $response->id)->where('survey_section_id', $section->id)->first();

        $this->assertNotNull($sectionScore);
        $this->assertEquals(1, $sectionScore->total_score);
        $this->assertEquals(2, $sectionScore->max_score);
        $this->assertEquals(50.0, (float) $sectionScore->percentage);
        $this->assertEquals(1, $sectionScore->answered_questions);
    }

    public function test_overall_score_averages_across_sections(): void
    {
        $survey = Survey::create(['code' => 'OVERALL_TEST', 'name' => 'Overall Test', 'is_active' => true]);
        $sectionA = SurveySection::create(['survey_id' => $survey->id, 'code' => 'a', 'name' => 'A', 'order' => 1, 'is_scored' => true]);
        $sectionB = SurveySection::create(['survey_id' => $survey->id, 'code' => 'b', 'name' => 'B', 'order' => 2, 'is_scored' => true]);
        $qa = SurveyQuestion::create(['survey_section_id' => $sectionA->id, 'question_code' => 'OA_Q1', 'question_text' => 'QA', 'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0]]);
        $qb = SurveyQuestion::create(['survey_section_id' => $sectionB->id, 'question_code' => 'OB_Q1', 'question_text' => 'QB', 'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0]]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);
        SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $qa->id, 'response_value' => 'Yes', 'score' => 1]);
        SurveyQuestionResponse::create(['survey_response_id' => $response->id, 'survey_question_id' => $qb->id, 'response_value' => 'No', 'score' => 0]);

        SurveyScoringService::recalculateSectionScore($response->id, $sectionA->id);
        SurveyScoringService::recalculateSectionScore($response->id, $sectionB->id);

        $this->assertEquals(50.0, (float) $response->fresh()->overall_percentage);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyScoringServiceTest`
Expected: FAIL — `App\Services\SurveyScoringService` doesn't exist.

- [ ] **Step 3: Create `SurveyScoringService`**

```php
<?php

namespace App\Services;

use App\Models\SurveyQuestionResponse;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\SurveySectionScore;
use App\Services\FormKernel\ScoringEngine;

class SurveyScoringService
{
    public static function recalculateSectionScore(int $surveyResponseId, int $surveySectionId): void
    {
        $section = SurveySection::findOrFail($surveySectionId);

        if (! $section->is_scored) {
            return;
        }

        $questions = $section->questions()->active()->scored()->get();

        self::resolveGroupCompletenessResponses($surveyResponseId, $questions);

        $responsesByCode = SurveyQuestionResponse::query()
            ->where('survey_response_id', $surveyResponseId)
            ->join('survey_questions', 'survey_questions.id', '=', 'survey_question_responses.survey_question_id')
            ->pluck('survey_question_responses.response_value', 'survey_questions.question_code')
            ->all();

        $questions = ScoringEngine::excludeConditionallyHiddenQuestions($questions, $responsesByCode);

        if ($questions->isEmpty()) {
            return;
        }

        $responses = SurveyQuestionResponse::where('survey_response_id', $surveyResponseId)
            ->whereIn('survey_question_id', $questions->pluck('id'))
            ->get();

        $scoreData = ScoringEngine::calculateSectionScore($questions, $responses);

        SurveySectionScore::updateOrCreate(
            ['survey_response_id' => $surveyResponseId, 'survey_section_id' => $surveySectionId],
            $scoreData
        );

        self::recalculateOverallScore($surveyResponseId);
    }

    private static function resolveGroupCompletenessResponses(int $surveyResponseId, $questions): void
    {
        $completenessQuestions = $questions->where('question_type', 'group_completeness');

        if ($completenessQuestions->isEmpty()) {
            return;
        }

        $siblingIds = $questions->where('question_type', '!=', 'group_completeness')->pluck('id');

        $responsesByQuestionId = SurveyQuestionResponse::where('survey_response_id', $surveyResponseId)
            ->whereIn('survey_question_id', $siblingIds)
            ->get()
            ->keyBy('survey_question_id');

        $updates = ScoringEngine::resolveGroupCompletenessResponses($questions, $responsesByQuestionId);

        foreach ($updates as $update) {
            SurveyQuestionResponse::updateOrCreate(
                ['survey_response_id' => $surveyResponseId, 'survey_question_id' => $update['question_id']],
                ['response_value' => $update['response_value'], 'score' => $update['score']]
            );
        }
    }

    public static function recalculateOverallScore(int $surveyResponseId): void
    {
        $sectionScores = SurveySectionScore::where('survey_response_id', $surveyResponseId)->get();

        if ($sectionScores->isEmpty()) {
            return;
        }

        $overall = ScoringEngine::calculateOverallScore($sectionScores);

        SurveyResponse::where('id', $surveyResponseId)->update([
            'overall_score' => $overall['total_score'],
            'overall_percentage' => $overall['percentage'],
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SurveyScoringServiceTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/SurveyScoringService.php tests/Feature/SurveyScoringServiceTest.php
git commit -m "feat: add SurveyScoringService"
```

---

### Task 10: `SurveyFormBuilder` — renders and saves a survey using the kernel

**Files:**
- Create: `app/Services/SurveyFormBuilder.php`
- Test: `tests/Feature/SurveyFormBuilderTest.php`

**Interfaces:**
- Consumes: `QuestionFieldBuilder::buildField()` (Task 3), `GroupedFieldRenderer::renderRuns()` (Task 4), `SurveyScoringService::recalculateSectionScore()` (Task 9), `ConditionalLogicEvaluator::isVisible()` (existing, unchanged).
- Produces: `SurveyFormBuilder::buildForSection(int $surveySectionId, ?int $surveyResponseId = null): array`, `::buildForSurvey(Survey $survey, ?int $surveyResponseId = null): array` (one Filament `Section` component per active survey section, each containing that section's `buildForSection()` output), `::saveResponses(int $surveyResponseId, int $surveySectionId, array $data): void`. These three are the exact methods Task 12's `SurveyResponseResource` (admin fill-out) and Task 14's public-link `PublicSurveyForm` Livewire component both call — no other entry points exist into this class.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\SurveySectionScore;
use App\Services\SurveyFormBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyFormBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_for_section_renders_a_field_per_active_question(): void
    {
        $survey = Survey::create(['code' => 'BUILD_TEST', 'name' => 'Build Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'BT_Q1', 'question_text' => 'Q1', 'question_type' => 'yes_no', 'order' => 1]);
        SurveyQuestion::create(['survey_section_id' => $section->id, 'question_code' => 'BT_Q2', 'question_text' => 'Q2', 'question_type' => 'text', 'order' => 2, 'is_active' => false]);

        $fields = SurveyFormBuilder::buildForSection($section->id);

        $this->assertNotEmpty($fields);
    }

    public function test_build_for_survey_wraps_each_active_section_in_its_own_form_section(): void
    {
        $survey = Survey::create(['code' => 'WRAP_TEST', 'name' => 'Wrap Test', 'is_active' => true]);
        $sectionA = SurveySection::create(['survey_id' => $survey->id, 'code' => 'a', 'name' => 'Section A', 'order' => 1]);
        $sectionB = SurveySection::create(['survey_id' => $survey->id, 'code' => 'b', 'name' => 'Section B', 'order' => 2]);
        SurveyQuestion::create(['survey_section_id' => $sectionA->id, 'question_code' => 'WT_QA', 'question_text' => 'QA', 'question_type' => 'yes_no']);
        SurveyQuestion::create(['survey_section_id' => $sectionB->id, 'question_code' => 'WT_QB', 'question_text' => 'QB', 'question_type' => 'yes_no']);

        $sections = SurveyFormBuilder::buildForSurvey($survey);

        $this->assertCount(2, $sections);
        $this->assertTrue(collect($sections)->every(fn ($s) => $s instanceof \Filament\Forms\Components\Section));
    }

    public function test_save_responses_persists_scored_answer_and_triggers_section_scoring(): void
    {
        $survey = Survey::create(['code' => 'SAVE_TEST', 'name' => 'Save Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1, 'is_scored' => true]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'ST_Q1', 'question_text' => 'Q1',
            'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0],
        ]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);

        SurveyFormBuilder::saveResponses($response->id, $section->id, [
            "question_response_{$question->id}" => 'Yes',
        ]);

        $saved = SurveyQuestionResponse::where('survey_response_id', $response->id)->where('survey_question_id', $question->id)->first();
        $this->assertSame('Yes', $saved->response_value);
        $this->assertEquals(1, $saved->score);
        $this->assertNotNull(SurveySectionScore::where('survey_response_id', $response->id)->where('survey_section_id', $section->id)->first());
    }

    public function test_save_responses_json_encodes_checkbox_and_matrix_answers(): void
    {
        $survey = Survey::create(['code' => 'JSON_SAVE_TEST', 'name' => 'JSON Save Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);
        $checkbox = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'JS_CB', 'question_text' => 'Pick colors',
            'question_type' => 'checkbox', 'options' => ['Red', 'Green', 'Blue'],
        ]);
        $matrix = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'JS_MX', 'question_text' => 'Rate it',
            'question_type' => 'matrix', 'options' => ['columns' => ['Agree', 'Disagree'], 'rows' => [['key' => 'r1', 'label' => 'Row 1']]],
        ]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);

        SurveyFormBuilder::saveResponses($response->id, $section->id, [
            "question_response_{$checkbox->id}" => ['Red', 'Blue'],
            "question_response_{$matrix->id}_r1" => 'Agree',
        ]);

        $cbSaved = SurveyQuestionResponse::where('survey_question_id', $checkbox->id)->first();
        $mxSaved = SurveyQuestionResponse::where('survey_question_id', $matrix->id)->first();

        $this->assertSame(['Red', 'Blue'], json_decode($cbSaved->response_value, true));
        $this->assertSame(['r1' => 'Agree'], json_decode($mxSaved->response_value, true));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyFormBuilderTest`
Expected: FAIL — `App\Services\SurveyFormBuilder` doesn't exist.

- [ ] **Step 3: Create `SurveyFormBuilder`**

```php
<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionResponse;
use App\Models\SurveySection;
use App\Services\FormKernel\GroupedFieldRenderer;
use App\Services\FormKernel\QuestionFieldBuilder;
use Filament\Forms;

class SurveyFormBuilder
{
    public static function buildForSection(int $surveySectionId, ?int $surveyResponseId = null): array
    {
        $questions = SurveyQuestion::where('survey_section_id', $surveySectionId)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        if ($questions->isEmpty()) {
            return [
                Forms\Components\Placeholder::make('no_questions')
                    ->label('')
                    ->content('No questions configured for this section yet.')
                    ->columnSpanFull(),
            ];
        }

        $runs = [];
        $currentGroup = null;
        $currentRun = null;
        $started = false;

        foreach ($questions as $question) {
            $existingResponse = null;

            if ($surveyResponseId) {
                $existingResponse = SurveyQuestionResponse::where('survey_response_id', $surveyResponseId)
                    ->where('survey_question_id', $question->id)
                    ->first();
            }

            $field = static::buildFieldForQuestion($question, $existingResponse);

            if (! $field) {
                continue;
            }

            if (! $started || $question->group !== $currentGroup) {
                if ($currentRun !== null) {
                    $runs[] = $currentRun;
                }
                $currentGroup = $question->group;
                $currentRun = ['group' => $currentGroup, 'fields' => []];
                $started = true;
            }

            $currentRun['fields'][] = $field;
        }

        if ($currentRun !== null) {
            $runs[] = $currentRun;
        }

        return GroupedFieldRenderer::renderRuns($runs);
    }

    /**
     * One Filament Section per active survey section, in order — used for
     * a single-page fill-out (admin panel and public link both render the
     * whole survey at once; there's no per-section wizard in Phase 1).
     */
    public static function buildForSurvey(Survey $survey, ?int $surveyResponseId = null): array
    {
        $sections = $survey->sections()->active()->orderBy('order')->get();

        return $sections->map(fn (SurveySection $section) => Forms\Components\Section::make($section->name)
            ->description($section->description)
            ->schema(static::buildForSection($section->id, $surveyResponseId))
            ->collapsible())->all();
    }

    protected static function buildFieldForQuestion(SurveyQuestion $question, ?SurveyQuestionResponse $existingResponse): mixed
    {
        $field = QuestionFieldBuilder::buildField($question, $existingResponse);

        $conditions = $question->display_conditions;

        if ($field && $conditions) {
            if (is_string($conditions)) {
                $conditions = json_decode($conditions, true);
            }

            if (is_array($conditions)) {
                $field = static::applyConditionalLogic($field, $conditions);
            }
        }

        return $field;
    }

    /**
     * checkbox and matrix are the two survey-only types needing special
     * save handling beyond "read the raw submitted value" — checkbox
     * because its state is an array (JSON-encode it, same convention
     * `repeater` already uses), matrix because it's really N sub-fields
     * (one radio per row) that must be collected back into a single JSON
     * object keyed by row — the same "sub-field" convention
     * mortality_three_month established in DynamicFormBuilder.
     */
    public static function saveResponses(int $surveyResponseId, int $surveySectionId, array $data): void
    {
        $questions = SurveyQuestion::where('survey_section_id', $surveySectionId)
            ->where('is_active', true)
            ->get();

        foreach ($questions as $question) {
            $fieldName = "question_response_{$question->id}";

            if ($question->question_type === 'matrix') {
                $rows = is_array($question->options) ? ($question->options['rows'] ?? []) : [];
                $firstRowKey = $rows[0]['key'] ?? null;

                if ($firstRowKey === null || ! array_key_exists("{$fieldName}_{$firstRowKey}", $data)) {
                    continue;
                }

                $answers = [];
                foreach ($rows as $row) {
                    $answers[$row['key']] = $data["{$fieldName}_{$row['key']}"] ?? null;
                }

                $responseValue = json_encode($answers);
            } else {
                if (! array_key_exists($fieldName, $data)) {
                    continue;
                }

                $responseValue = $data[$fieldName];

                if (in_array($question->question_type, ['repeater', 'checkbox'], true)) {
                    $responseValue = json_encode(array_values($responseValue ?? []));
                }
            }

            $explanation = $data["{$fieldName}_explanation"] ?? null;

            $score = null;
            if (! in_array($question->question_type, ['repeater', 'checkbox', 'matrix', 'file_upload', 'signature'], true)
                && $question->is_scored && $question->scoring_map) {
                $score = $question->scoring_map[$responseValue] ?? 0;
            }

            SurveyQuestionResponse::updateOrCreate(
                ['survey_response_id' => $surveyResponseId, 'survey_question_id' => $question->id],
                ['response_value' => $responseValue, 'explanation' => $explanation, 'score' => $score]
            );
        }

        app(SurveyScoringService::class)->recalculateSectionScore($surveyResponseId, $surveySectionId);
    }

    protected static function applyConditionalLogic($field, array $conditionalLogic)
    {
        return $field->visible(function (Forms\Get $get) use ($conditionalLogic) {
            return ConditionalLogicEvaluator::isVisible($conditionalLogic, function (string $questionCode) use ($get) {
                $parentQuestion = SurveyQuestion::where('question_code', $questionCode)->first();

                if (! $parentQuestion) {
                    return null;
                }

                return $get("question_response_{$parentQuestion->id}");
            });
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SurveyFormBuilderTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/SurveyFormBuilder.php tests/Feature/SurveyFormBuilderTest.php
git commit -m "feat: add SurveyFormBuilder"
```

---

### Task 11: `SurveyResource` — Filament CRUD for survey templates + sections

**Files:**
- Create: `app/Filament/Resources/SurveyResource.php`
- Create: `app/Filament/Resources/SurveyResource/Pages/ListSurveys.php`
- Create: `app/Filament/Resources/SurveyResource/Pages/CreateSurvey.php`
- Create: `app/Filament/Resources/SurveyResource/Pages/EditSurvey.php`
- Create: `app/Filament/Resources/SurveyResource/RelationManagers/SectionsRelationManager.php`
- Test: `tests/Feature/SurveyResourceTest.php`

**Interfaces:**
- Consumes: `Survey`/`SurveySection` (Task 2). Mirrors the existing `AssessmentTypeResource`/`SectionsRelationManager` UX exactly, trimmed of assessment-only concepts (no `section_type` kind picker — every survey section is the one generic kind).
- Produces: routes `admin/surveys`, `admin/surveys/create`, `admin/surveys/{record}/edit`; the "Get Link" table action used by Task 15's public-link flow (generates and persists `Survey.access_token`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResource\Pages\CreateSurvey;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyResourceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        foreach (['view_any_survey', 'create_survey', 'update_survey'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey', 'create_survey', 'update_survey']);
        $this->actingAs($user);

        return $user;
    }

    public function test_a_survey_can_be_created_through_the_resource_form(): void
    {
        $this->actingAdmin();

        Livewire::test(CreateSurvey::class)
            ->fillForm([
                'name' => 'Training Feedback',
                'code' => 'TRAINING_FEEDBACK',
                'version' => '1.0',
                'is_active' => true,
                'is_public' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('surveys', ['code' => 'TRAINING_FEEDBACK', 'name' => 'Training Feedback']);
    }

    public function test_get_link_action_generates_a_token_for_a_public_survey(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'PUBLIC_TEST', 'name' => 'Public Test', 'is_active' => true, 'is_public' => true]);

        $this->assertNull($survey->access_token);

        \Livewire\Livewire::test(\App\Filament\Resources\SurveyResource\Pages\ListSurveys::class)
            ->callTableAction('get_link', $survey);

        $this->assertNotNull($survey->fresh()->access_token);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyResourceTest`
Expected: FAIL — `App\Filament\Resources\SurveyResource` and its pages don't exist.

- [ ] **Step 3: Create `SurveyResource`**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurveyResource\Pages;
use App\Filament\Resources\SurveyResource\RelationManagers;
use App\Models\Survey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SurveyResource extends Resource
{
    protected static ?string $model = Survey::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Survey Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Surveys';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_survey');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_survey');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Survey Details')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Survey Title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->alphaDash()
                            ->helperText('Unique identifier, e.g. TRAINING_FEEDBACK_2026'),
                        Forms\Components\TextInput::make('version')
                            ->default('1.0')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Only active surveys are selectable for filling out.'),
                        Forms\Components\Toggle::make('is_public')
                            ->default(false)
                            ->helperText('Enables the "Get Link" action, which generates a shareable public link.'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpan(2),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (Survey $record): ?string => $record->description),
                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                Tables\Columns\TextColumn::make('version')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('sections_count')
                    ->label('Sections')
                    ->counts('sections')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('responses_count')
                    ->label('Responses')
                    ->counts('responses')
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_public')->boolean()->label('Public'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->default(true),
            ])
            ->actions([
                Tables\Actions\Action::make('get_link')
                    ->label('Get Link')
                    ->icon('heroicon-o-link')
                    ->visible(fn (Survey $record): bool => $record->is_public)
                    ->action(function (Survey $record) {
                        if (! $record->access_token) {
                            $record->update(['access_token' => Str::random(32)]);
                        }

                        Notification::make()
                            ->title('Public link ready')
                            ->body(url('/survey/'.$record->fresh()->access_token))
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SectionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveys::route('/'),
            'create' => Pages\CreateSurvey::route('/create'),
            'edit' => Pages\EditSurvey::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Survey::active()->count();
    }
}
```

- [ ] **Step 4: Create the Pages**

```php
<?php

namespace App\Filament\Resources\SurveyResource\Pages;

use App\Filament\Resources\SurveyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSurveys extends ListRecords
{
    protected static string $resource = SurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
```

```php
<?php

namespace App\Filament\Resources\SurveyResource\Pages;

use App\Filament\Resources\SurveyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSurvey extends CreateRecord
{
    protected static string $resource = SurveyResource::class;
}
```

```php
<?php

namespace App\Filament\Resources\SurveyResource\Pages;

use App\Filament\Resources\SurveyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSurvey extends EditRecord
{
    protected static string $resource = SurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
```

- [ ] **Step 5: Create `SectionsRelationManager`**

```php
<?php

namespace App\Filament\Resources\SurveyResource\RelationManagers;

use App\Filament\Resources\SurveyQuestionResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Survey Sections';

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
                Tables\Columns\TextColumn::make('questions_count')->label('Questions')->counts('questions')->badge()->color('info'),
                Tables\Columns\IconColumn::make('is_scored')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (! isset($data['order']) || $data['order'] === 0) {
                            $data['order'] = ($this->getOwnerRecord()->sections()->max('order') ?? 0) + 10;
                        }

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('manage_questions')
                    ->label('Questions')
                    ->icon('heroicon-o-queue-list')
                    ->url(fn ($record): string => SurveyQuestionResource::getUrl('index', [
                        'tableFilters' => ['survey_section_id' => ['value' => $record->id]],
                    ])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record) {
                        if ($record->questions()->count() > 0) {
                            Notification::make()->title('Cannot delete — has questions')->danger()->send();

                            return false;
                        }
                    }),
            ])
            ->defaultSort('order')
            ->reorderable('order');
    }
}
```

- [ ] **Step 6: Generate Shield permissions**

Run: `php artisan shield:generate --resource=SurveyResource`
Expected: creates `view_any_survey`, `view_survey`, `create_survey`, `update_survey`, `delete_survey`, etc. permission rows.

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=SurveyResourceTest`
Expected: PASS (2 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/SurveyResource.php app/Filament/Resources/SurveyResource/ tests/Feature/SurveyResourceTest.php
git commit -m "feat: add SurveyResource (Filament) with sections relation manager"
```

---

### Task 12: `SurveyQuestionResource` — global question management across all surveys

**Files:**
- Create: `app/Filament/Resources/SurveyQuestionResource.php`
- Create: `app/Filament/Resources/SurveyQuestionResource/Pages/ListSurveyQuestions.php`
- Create: `app/Filament/Resources/SurveyQuestionResource/Pages/CreateSurveyQuestion.php`
- Create: `app/Filament/Resources/SurveyQuestionResource/Pages/EditSurveyQuestion.php`
- Test: `tests/Feature/SurveyQuestionResourceTest.php`

**Interfaces:**
- Consumes: `SurveyQuestion`/`SurveySection` (Task 2). Mirrors `AssessmentQuestionResource`'s form/table structure, extended with the 13 new question types from Tasks 6–8.
- Produces: `options` is edited two ways depending on type — a `TagsInput` (flat list) for `select`/`radio`/`checkbox`, or a raw-JSON `Textarea` (bound to a virtual `options_json` field, decoded into the real `options` column by the Create/Edit pages' `mutateFormDataBeforeCreate`/`mutateFormDataBeforeSave` hooks) for `repeater`/`matrix`, whose `options` shape is structured, not a flat list.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyQuestionResource\Pages\CreateSurveyQuestion;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyQuestionResourceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        foreach (['view_any_survey::question', 'create_survey::question'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey::question', 'create_survey::question']);
        $this->actingAs($user);

        return $user;
    }

    public function test_a_simple_question_can_be_created_through_the_resource_form(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'SQR_TEST', 'name' => 'SQR Test', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);

        Livewire::test(CreateSurveyQuestion::class)
            ->fillForm([
                'survey_section_id' => $section->id,
                'question_code' => 'SQR_Q1',
                'question_text' => 'Did you enjoy the training?',
                'question_type' => 'yes_no',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('survey_questions', ['question_code' => 'SQR_Q1', 'question_type' => 'yes_no']);
    }

    public function test_a_matrix_question_persists_structured_json_options_from_the_raw_editor(): void
    {
        $this->actingAdmin();
        $survey = Survey::create(['code' => 'SQR_MATRIX', 'name' => 'SQR Matrix', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1]);

        Livewire::test(CreateSurveyQuestion::class)
            ->fillForm([
                'survey_section_id' => $section->id,
                'question_code' => 'SQR_MX1',
                'question_text' => 'Rate the session',
                'question_type' => 'matrix',
                'options_json' => json_encode(['columns' => ['Agree', 'Disagree'], 'rows' => [['key' => 'r1', 'label' => 'Row 1']]]),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $question = SurveyQuestion::where('question_code', 'SQR_MX1')->firstOrFail();
        $this->assertSame(['Agree', 'Disagree'], $question->options['columns']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyQuestionResourceTest`
Expected: FAIL — resource/pages don't exist.

- [ ] **Step 3: Create `SurveyQuestionResource`**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurveyQuestionResource\Pages;
use App\Models\SurveyQuestion;
use App\Models\SurveySection;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SurveyQuestionResource extends Resource
{
    protected static ?string $model = SurveyQuestion::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = 'Survey Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'All Questions';

    protected static ?string $recordTitleAttribute = 'question_text';

    private const TYPE_OPTIONS = [
        'yes_no' => 'Yes / No',
        'yes_no_partial' => 'Yes / No / Partially',
        'number' => 'Number',
        'text' => 'Free Text (multi-line)',
        'short_text' => 'Short Text (single-line)',
        'proportion' => 'Proportion',
        'select' => 'Dropdown Select',
        'radio' => 'Radio Buttons',
        'checkbox' => 'Checkboxes (multi-select)',
        'rating' => 'Rating Scale',
        'date' => 'Date',
        'datetime' => 'Date & Time',
        'email' => 'Email',
        'phone' => 'Phone',
        'file_upload' => 'File Upload',
        'signature' => 'Signature',
        'matrix' => 'Matrix / Likert Grid',
        'repeater' => 'Repeating Rows',
        'cadre_select' => 'Cadre Dropdown',
        'group_completeness' => 'Group Completeness (derived)',
    ];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_survey::question');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_survey::question');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Question Identity')
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('survey_section_id')
                            ->label('Section')
                            ->options(fn () => SurveySection::where('is_active', true)->orderBy('order')->get()
                                ->mapWithKeys(fn ($s) => [$s->id => "{$s->survey->name} — {$s->name}"]))
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('question_code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Globally unique — used in conditional logic'),
                        Forms\Components\TextInput::make('order')->numeric()->default(0)->required(),
                    ]),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('group')
                            ->label('Group / Sub-section')
                            ->maxLength(150),
                        Forms\Components\Select::make('question_type')
                            ->options(self::TYPE_OPTIONS)
                            ->required()
                            ->live()
                            ->default('yes_no'),
                    ]),
                    Forms\Components\Textarea::make('question_text')->required()->rows(2)->columnSpanFull(),
                    Forms\Components\Textarea::make('help_text')->rows(2)->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Options & Flags')
                ->schema([
                    Forms\Components\TagsInput::make('options')
                        ->label('Answer Options')
                        ->visible(fn (Get $get) => in_array($get('question_type'), ['select', 'radio', 'checkbox']))
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('options_json')
                        ->label('Options (JSON)')
                        ->visible(fn (Get $get) => in_array($get('question_type'), ['repeater', 'matrix']))
                        ->rows(6)
                        ->dehydrated(false)
                        ->helperText('repeater: [{"key":"...","label":"...","type":"text|select|date|number","options":[...]}] · matrix: {"columns":[...],"rows":[{"key":"...","label":"..."}]}')
                        ->afterStateHydrated(function (Forms\Components\Textarea $component, $record) {
                            if ($record && $record->options) {
                                $component->state(json_encode($record->options, JSON_PRETTY_PRINT));
                            }
                        })
                        ->columnSpanFull(),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Toggle::make('is_required')->default(false),
                        Forms\Components\Toggle::make('is_scored')->default(true)->live(),
                        Forms\Components\Toggle::make('is_active')->default(true),
                    ]),
                ]),
            Forms\Components\Section::make('Scoring Configuration')
                ->collapsible()
                ->collapsed()
                ->visible(fn (Get $get) => (bool) $get('is_scored'))
                ->schema([
                    Forms\Components\KeyValue::make('scoring_map')
                        ->label('Scoring Map (Response → Score)')
                        ->keyLabel('Response Value')
                        ->valueLabel('Score')
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Conditional Logic')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('conditional_logic_parent')
                            ->label('Parent Question Code')
                            ->options(fn () => SurveyQuestion::where('is_active', true)->orderBy('question_code')->get()
                                ->mapWithKeys(fn ($q) => [$q->question_code => "[{$q->question_code}] {$q->question_text}"]))
                            ->searchable()
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $set('display_conditions', ['question_code' => $state, 'operator' => 'equals', 'value' => '']);
                                }
                            })
                            ->afterStateHydrated(function (Forms\Components\Select $component, $record) {
                                if ($record && ! empty($record->display_conditions['question_code'])) {
                                    $component->state($record->display_conditions['question_code']);
                                }
                            }),
                        Forms\Components\Select::make('display_conditions.operator')
                            ->options(['equals' => 'Equals', 'not_equals' => 'Not Equals', 'in' => 'Is One Of', 'not_in' => 'Is Not One Of', 'greater_than' => 'Greater Than', 'less_than' => 'Less Than'])
                            ->default('equals'),
                        Forms\Components\TextInput::make('display_conditions.value')->label('Trigger Value'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('section.survey.name')->label('Survey')->badge()->color('gray')->searchable(),
                Tables\Columns\TextColumn::make('section.name')->label('Section')->badge()->color('primary')->searchable(),
                Tables\Columns\TextColumn::make('order')->sortable()->alignCenter()->width(70),
                Tables\Columns\TextColumn::make('question_code')->badge()->color('gray')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('question_text')->searchable()->limit(70)->wrap(),
                Tables\Columns\TextColumn::make('question_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::TYPE_OPTIONS[$state] ?? $state),
                Tables\Columns\IconColumn::make('is_scored')->boolean()->alignCenter(),
                Tables\Columns\IconColumn::make('is_required')->boolean()->alignCenter(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->alignCenter(),
            ])
            ->defaultSort('survey_section_id')
            ->filters([
                Tables\Filters\SelectFilter::make('survey_section_id')->label('Section')->relationship('section', 'name')->preload()->searchable(),
                Tables\Filters\SelectFilter::make('question_type')->options(self::TYPE_OPTIONS),
                Tables\Filters\TernaryFilter::make('is_active')->default(true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (SurveyQuestion $record) {
                        if ($record->responses()->count() > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot delete — has responses')
                                ->body('Deactivate this question instead.')
                                ->danger()
                                ->send();

                            return false;
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveyQuestions::route('/'),
            'create' => Pages\CreateSurveyQuestion::route('/create'),
            'edit' => Pages\EditSurveyQuestion::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Create the Pages, decoding `options_json` on save**

```php
<?php

namespace App\Filament\Resources\SurveyQuestionResource\Pages;

use App\Filament\Resources\SurveyQuestionResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListSurveyQuestions extends ListRecords
{
    protected static string $resource = SurveyQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
```

```php
<?php

namespace App\Filament\Resources\SurveyQuestionResource\Pages;

use App\Filament\Resources\SurveyQuestionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSurveyQuestion extends CreateRecord
{
    protected static string $resource = SurveyQuestionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->decodeOptionsJson($data);
    }

    private function decodeOptionsJson(array $data): array
    {
        if (in_array($data['question_type'] ?? null, ['repeater', 'matrix'], true) && ! empty($this->form->getRawState()['options_json'] ?? null)) {
            $decoded = json_decode($this->form->getRawState()['options_json'], true);
            if (is_array($decoded)) {
                $data['options'] = $decoded;
            }
        }

        return $data;
    }
}
```

```php
<?php

namespace App\Filament\Resources\SurveyQuestionResource\Pages;

use App\Filament\Resources\SurveyQuestionResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditSurveyQuestion extends EditRecord
{
    protected static string $resource = SurveyQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array($data['question_type'] ?? null, ['repeater', 'matrix'], true) && ! empty($this->form->getRawState()['options_json'] ?? null)) {
            $decoded = json_decode($this->form->getRawState()['options_json'], true);
            if (is_array($decoded)) {
                $data['options'] = $decoded;
            }
        }

        return $data;
    }
}
```

- [ ] **Step 5: Generate Shield permissions**

Run: `php artisan shield:generate --resource=SurveyQuestionResource`

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=SurveyQuestionResourceTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/SurveyQuestionResource.php app/Filament/Resources/SurveyQuestionResource/ tests/Feature/SurveyQuestionResourceTest.php
git commit -m "feat: add SurveyQuestionResource (Filament)"
```

---

### Task 13: `SurveyResponseResource` — authenticated fill-out in the admin panel

**Files:**
- Create: `app/Filament/Resources/SurveyResponseResource.php`
- Create: `app/Filament/Resources/SurveyResponseResource/Pages/ListSurveyResponses.php`
- Create: `app/Filament/Resources/SurveyResponseResource/Pages/CreateSurveyResponse.php`
- Create: `app/Filament/Resources/SurveyResponseResource/Pages/EditSurveyResponse.php`
- Test: `tests/Feature/SurveyResponseResourceTest.php`

**Interfaces:**
- Consumes: `SurveyFormBuilder::buildForSurvey()`/`::saveResponses()` (Task 10), `SurveyResponse::markSubmitted()` (Task 2).
- Produces: `EditSurveyResponse` overrides `form()` (not `SurveyResponseResource::form()`, which only covers the Create page's fixed fields) to render the survey's actual questions per-record via `SurveyFormBuilder::buildForSurvey($this->record->survey, $this->record->id)`, and overrides `handleRecordUpdate()` to route the submitted dynamic `question_response_*` fields through `SurveyFormBuilder::saveResponses()` once per active section rather than a plain `$record->update($data)` (the model itself has no form-bound columns beyond what `CreateSurveyResponse` sets). **Scope note**: Create only captures `survey_id` + free-text respondent fields — attaching a response to a specific polymorphic subject (Facility/User/etc.) from the admin panel is not built in Phase 1 (the public-link flow in Task 14 always creates subject-less responses too); every Phase 1 response's `subject_type`/`subject_id` stay null. Targeted subject-linking is a natural Phase 1.5 addition, not required by anything Phase 1 commits to.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\SurveyResponseResource\Pages\CreateSurveyResponse;
use App\Filament\Resources\SurveyResponseResource\Pages\EditSurveyResponse;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyResponseResourceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $user = User::factory()->create();
        foreach (['view_any_survey::response', 'create_survey::response', 'update_survey::response'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $user->givePermissionTo(['view_any_survey::response', 'create_survey::response', 'update_survey::response']);

        return $user;
    }

    public function test_a_draft_response_can_be_created_for_a_survey(): void
    {
        $user = $this->actingAdmin();
        $this->actingAs($user);
        $survey = Survey::create(['code' => 'SRR_TEST', 'name' => 'SRR Test', 'is_active' => true]);

        Livewire::test(CreateSurveyResponse::class)
            ->fillForm(['survey_id' => $survey->id, 'respondent_name' => 'Jane Doe'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->id, 'respondent_name' => 'Jane Doe', 'status' => 'draft', 'created_by' => $user->id,
        ]);
    }

    public function test_submitting_the_edit_page_saves_answers_and_marks_the_response_submitted(): void
    {
        $this->actingAs($this->actingAdmin());
        $survey = Survey::create(['code' => 'SRR_SUBMIT', 'name' => 'SRR Submit', 'is_active' => true]);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1, 'is_scored' => true]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'SRR_Q1', 'question_text' => 'Q1',
            'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0],
        ]);
        $response = SurveyResponse::create(['survey_id' => $survey->id, 'status' => 'draft']);

        Livewire::test(EditSurveyResponse::class, ['record' => $response->getRouteKey()])
            ->fillForm(["question_response_{$question->id}" => 'Yes'])
            ->callAction('submit');

        $response->refresh();
        $this->assertSame('submitted', $response->status);
        $this->assertNotNull($response->submitted_at);
        $this->assertDatabaseHas('survey_question_responses', [
            'survey_response_id' => $response->id, 'survey_question_id' => $question->id, 'response_value' => 'Yes', 'score' => 1,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SurveyResponseResourceTest`
Expected: FAIL — resource/pages don't exist.

- [ ] **Step 3: Create `SurveyResponseResource`**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SurveyResponseResource\Pages;
use App\Models\Survey;
use App\Models\SurveyResponse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SurveyResponseResource extends Resource
{
    protected static ?string $model = SurveyResponse::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Survey Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Responses';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_survey::response');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_any_survey::response');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('survey_id')
                ->label('Survey')
                ->options(fn () => Survey::active()->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload(),
            Forms\Components\TextInput::make('respondent_name')->maxLength(255),
            Forms\Components\TextInput::make('respondent_email')->email()->maxLength(255),
            Forms\Components\TextInput::make('respondent_contact')->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('survey.name')->label('Survey')->badge()->color('primary')->searchable(),
                Tables\Columns\TextColumn::make('respondent_name')->label('Respondent')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'submitted' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('overall_percentage')->label('Score')->suffix('%')->placeholder('—'),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('survey_id')->relationship('survey', 'name'),
                Tables\Filters\SelectFilter::make('status')->options(['draft' => 'Draft', 'submitted' => 'Submitted']),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveyResponses::route('/'),
            'create' => Pages\CreateSurveyResponse::route('/create'),
            'edit' => Pages\EditSurveyResponse::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Create the Pages**

```php
<?php

namespace App\Filament\Resources\SurveyResponseResource\Pages;

use App\Filament\Resources\SurveyResponseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSurveyResponses extends ListRecords
{
    protected static string $resource = SurveyResponseResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
```

```php
<?php

namespace App\Filament\Resources\SurveyResponseResource\Pages;

use App\Filament\Resources\SurveyResponseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSurveyResponse extends CreateRecord
{
    protected static string $resource = SurveyResponseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] = 'draft';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return SurveyResponseResource::getUrl('edit', ['record' => $this->record]);
    }
}
```

```php
<?php

namespace App\Filament\Resources\SurveyResponseResource\Pages;

use App\Filament\Resources\SurveyResponseResource;
use App\Services\SurveyFormBuilder;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSurveyResponse extends EditRecord
{
    protected static string $resource = SurveyResponseResource::class;

    public function form(Form $form): Form
    {
        return $form->schema(
            SurveyFormBuilder::buildForSurvey($this->record->survey, $this->record->id)
        );
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        foreach ($record->survey->sections()->active()->get() as $section) {
            SurveyFormBuilder::saveResponses($record->id, $section->id, $data);
        }

        return $record->fresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('submit')
                ->label('Submit')
                ->color('success')
                ->action(function () {
                    $this->save();
                    $this->record->markSubmitted();

                    Notification::make()->title('Response submitted')->success()->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
```

- [ ] **Step 5: Generate Shield permissions**

Run: `php artisan shield:generate --resource=SurveyResponseResource`

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=SurveyResponseResourceTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/SurveyResponseResource.php app/Filament/Resources/SurveyResponseResource/ tests/Feature/SurveyResponseResourceTest.php
git commit -m "feat: add SurveyResponseResource (Filament) for authenticated fill-out"
```

---

### Task 14: Public survey link — controller, route, and the anonymous fill-out form

**Files:**
- Create: `app/Http/Controllers/SurveyController.php`
- Create: `app/Livewire/PublicSurveyForm.php`
- Create: `resources/views/survey/public.blade.php`
- Create: `resources/views/survey/invalid.blade.php`
- Create: `resources/views/livewire/public-survey-form.blade.php`
- Modify: `routes/web.php` (add the public route)
- Test: `tests/Feature/PublicSurveyLinkTest.php`

**Interfaces:**
- Consumes: `Survey.access_token`/`Survey.is_public` (Task 1/11's "Get Link" action), `SurveyFormBuilder::buildForSurvey()`/`::saveResponses()` (Task 10), `SurveyResponse::markSubmitted()` (Task 2).
- Produces: `GET /survey/{token}` → `SurveyController@show`, resolving `Survey::where('access_token', $token)->where('is_public', true)->where('is_active', true)->first()`. No `POST` route is needed — the Livewire component (`App\Livewire\PublicSurveyForm`, auto-discovered by Livewire v3 under the standard `App\Livewire\` namespace, no manual registration) handles submission itself via `wire:submit`. Every response created through this path has `subject_type`/`subject_id` null and `status` moves straight from nonexistent to `submitted` (no draft-saving mid-fill in Phase 1 — matches Global Constraints' "create-on-submit only" simplification, avoiding orphaned draft rows from abandoned visits).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Livewire\PublicSurveyForm;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\SurveySection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicSurveyLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_an_unknown_token_shows_the_invalid_link_page(): void
    {
        $response = $this->get('/survey/not-a-real-token');

        $response->assertOk();
        $response->assertSee('not available');
    }

    public function test_visiting_a_valid_public_survey_token_renders_the_form(): void
    {
        Survey::create(['code' => 'PUB_LINK', 'name' => 'Public Link Survey', 'is_active' => true, 'is_public' => true, 'access_token' => 'testtoken123']);

        $response = $this->get('/survey/testtoken123');

        $response->assertOk();
        $response->assertSee('Public Link Survey');
    }

    public function test_submitting_the_public_form_creates_a_response_and_saves_answers(): void
    {
        $survey = Survey::create(['code' => 'PUB_SUBMIT', 'name' => 'Public Submit Survey', 'is_active' => true, 'is_public' => true, 'access_token' => 'submittoken']);
        $section = SurveySection::create(['survey_id' => $survey->id, 'code' => 'main', 'name' => 'Main', 'order' => 1, 'is_scored' => true]);
        $question = SurveyQuestion::create([
            'survey_section_id' => $section->id, 'question_code' => 'PUB_Q1', 'question_text' => 'Q1',
            'question_type' => 'yes_no', 'is_scored' => true, 'scoring_map' => ['Yes' => 1, 'No' => 0],
        ]);

        Livewire::test(PublicSurveyForm::class, ['surveyId' => $survey->id])
            ->fillForm([
                'respondent_name' => 'Anon Respondent',
                "question_response_{$question->id}" => 'Yes',
            ])
            ->call('submit');

        $response = SurveyResponse::where('survey_id', $survey->id)->firstOrFail();
        $this->assertSame('submitted', $response->status);
        $this->assertSame('Anon Respondent', $response->respondent_name);
        $this->assertNull($response->subject_type);

        $this->assertDatabaseHas('survey_question_responses', [
            'survey_response_id' => $response->id, 'survey_question_id' => $question->id, 'response_value' => 'Yes', 'score' => 1,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PublicSurveyLinkTest`
Expected: FAIL — route/controller/component don't exist.

- [ ] **Step 3: Create `SurveyController`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Survey;

class SurveyController extends Controller
{
    public function show(string $token)
    {
        $survey = Survey::where('access_token', $token)
            ->where('is_public', true)
            ->where('is_active', true)
            ->first();

        if (! $survey) {
            return view('survey.invalid', [
                'reason' => 'This survey link is no longer active or does not exist.',
            ]);
        }

        return view('survey.public', compact('survey'));
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, alongside the other public/guest routes (near `/enroll/{token}`):

```php
Route::get('/survey/{token}', [\App\Http\Controllers\SurveyController::class, 'show'])
    ->name('survey.public.show');
```

- [ ] **Step 5: Create `PublicSurveyForm`**

```php
<?php

namespace App\Livewire;

use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Services\SurveyFormBuilder;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;

class PublicSurveyForm extends Component implements HasForms
{
    use InteractsWithForms;

    public int $surveyId;

    public ?array $data = [];

    public bool $submitted = false;

    public function mount(int $surveyId): void
    {
        $this->surveyId = $surveyId;
        $this->form->fill();
    }

    protected function getSurvey(): Survey
    {
        return Survey::findOrFail($this->surveyId);
    }

    public function form(Form $form): Form
    {
        $respondentSection = Forms\Components\Section::make('Your Details')
            ->schema([
                Forms\Components\TextInput::make('respondent_name')->label('Name')->maxLength(255),
                Forms\Components\TextInput::make('respondent_email')->label('Email')->email()->maxLength(255),
                Forms\Components\TextInput::make('respondent_contact')->label('Phone')->tel()->maxLength(255),
            ]);

        return $form
            ->schema([$respondentSection, ...SurveyFormBuilder::buildForSurvey($this->getSurvey())])
            ->statePath('data');
    }

    public function submit(): void
    {
        $survey = $this->getSurvey();
        $formData = $this->form->getState();

        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'respondent_name' => $formData['respondent_name'] ?? null,
            'respondent_email' => $formData['respondent_email'] ?? null,
            'respondent_contact' => $formData['respondent_contact'] ?? null,
            'status' => 'draft',
        ]);

        foreach ($survey->sections()->active()->get() as $section) {
            SurveyFormBuilder::saveResponses($response->id, $section->id, $formData);
        }

        $response->markSubmitted();

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.public-survey-form');
    }
}
```

- [ ] **Step 6: Create the three views**

```blade
{{-- resources/views/livewire/public-survey-form.blade.php --}}
<div>
    @if ($submitted)
        <div class="rounded-lg bg-green-50 p-6 text-center">
            <h2 class="text-lg font-semibold text-green-800">Thank you!</h2>
            <p class="mt-1 text-sm text-green-700">Your response has been submitted.</p>
        </div>
    @else
        <form wire:submit="submit">
            {{ $this->form }}

            <button type="submit" class="fi-btn fi-btn-color-primary mt-6">
                Submit
            </button>
        </form>
    @endif
</div>
```

```blade
{{-- resources/views/survey/public.blade.php --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $survey->name }}</title>
    @filamentStyles
    @vite('resources/css/app.css')
</head>
<body class="h-full bg-gray-50">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-2xl font-bold text-gray-900">{{ $survey->name }}</h1>
        @if ($survey->description)
            <p class="mt-2 text-gray-600">{{ $survey->description }}</p>
        @endif

        <div class="mt-8">
            <livewire:public-survey-form :survey-id="$survey->id" />
        </div>
    </div>

    @livewire('notifications')
    @filamentScripts
    @vite('resources/js/app.js')
</body>
</html>
```

```blade
{{-- resources/views/survey/invalid.blade.php --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <title>Link not available</title>
</head>
<body class="h-full flex items-center justify-center bg-gray-50">
    <div class="text-center">
        <h1 class="text-xl font-semibold text-gray-900">This link is not available</h1>
        <p class="mt-2 text-gray-600">{{ $reason }}</p>
    </div>
</body>
</html>
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=PublicSurveyLinkTest`
Expected: PASS (3 tests).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/SurveyController.php app/Livewire/PublicSurveyForm.php resources/views/survey/ resources/views/livewire/public-survey-form.blade.php routes/web.php tests/Feature/PublicSurveyLinkTest.php
git commit -m "feat: add public survey link (/survey/{token})"
```

---

### Task 15: Full regression pass and final permission sync

**Files:** none created — verification only.

**Interfaces:** none — this task's only job is confirming Tasks 1–14 compose correctly as a whole and that nothing in the existing assessment engine regressed.

- [ ] **Step 1: Run the complete test suite**

Run: `php artisan test`
Expected: PASS — every existing test (assessments, mentorship, EmONC, etc.) plus every new `Survey*`/`QuestionFieldBuilder*`/kernel test from Tasks 1–14.

- [ ] **Step 2: Regenerate all Shield permissions**

Run: `php artisan shield:generate --all`
Expected: confirms the four new resources' permissions (`SurveyResource`, `SurveyQuestionResource`, `SurveyResponseResource`) are present alongside every existing resource's — this is idempotent and safe to run even though Tasks 11–13 already generated them individually.

- [ ] **Step 3: Verify Pint formatting**

Run: `./vendor/bin/pint --test`
Expected: no formatting violations in the new files. If it reports fixable issues, run `./vendor/bin/pint` (no `--test`) to apply them, then re-run Step 1 to confirm nothing broke.

- [ ] **Step 4: Manual smoke check**

Run: `php artisan route:list | grep -i survey`
Expected: shows `admin/surveys*`, `admin/survey-questions*` (or however Filament slugs it), `admin/survey-responses*`, and `GET survey/{token}` — confirms every resource and the public route registered correctly.

- [ ] **Step 5: Commit any formatting fixes**

```bash
git add -A
git commit -m "chore: pint formatting pass for Survey* code (Phase 1 complete)"
```

(Skip this commit entirely if Step 3 reported no changes.)

---

## Phase 1 Definition of Done

- [ ] All 15 tasks' steps checked off, each with its own commit.
- [ ] `php artisan test` green, including every pre-existing test — the kernel extraction (Tasks 3–5) introduced no behavior change to facility assessments.
- [ ] An admin can, without writing code: create a `Survey`, add sections and any of the 20 question types, generate a public link, and see both admin-panel-filled and public-link-filled responses scored consistently.
- [ ] Phases 2 (longitudinal events), 3 (auto dashboards), 4 (AI narrative layer) remain fully unbuilt but architecturally unblocked — `SurveyResponse` already carries `subject_type`/`subject_id` for Phase 2's grouping, `SurveySectionScore` already gives Phase 3 per-section numbers to chart, and `SurveyScoringService`'s pure `ScoringEngine` inputs/outputs are exactly what Phase 4's dashboard-summarizer will consume.

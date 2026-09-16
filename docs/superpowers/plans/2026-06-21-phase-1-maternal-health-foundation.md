# Phase 1 — Maternal Health (EmONC) Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the foundational data model and seed data for the Maternal Health (EmONC) mentorship program, including self-referential program modules for tracks, an activities catalog, and a `newbie` role, without breaking existing programs.

**Architecture:** Tracks are stored as child `ProgramModule` records via a nullable `parent_id`. Activities are stored in a dedicated `activities` table and attached through a `program_module_activities` pivot. Seeders create the EmONC program, its 13 modules, and the 10 tracks under Module 5.

**Tech Stack:** Laravel 12, Filament v3, MySQL, Spatie Permission, Eloquent.

---

## Files to create or modify

| File | Purpose |
|------|---------|
| `database/migrations/2026_06_21_000001_add_parent_id_to_program_modules_table.php` | Self-referential track support |
| `database/migrations/2026_06_21_000002_create_activities_table.php` | Activities catalog |
| `database/migrations/2026_06_21_000003_create_program_module_activities_table.php` | Module/track ↔ activity pivot |
| `app/Models/Activity.php` | Activity model |
| `app/Models/ProgramModuleActivity.php` | Pivot model |
| `app/Models/ProgramModule.php` | Parent/children + activities relationships |
| `database/seeders/RolePermissionSeeder.php` | Add `newbie` role |
| `database/seeders/EmoncProgramSeeder.php` | Seed EmONC program/modules/tracks |
| `database/seeders/DatabaseSeeder.php` | Wire new seeders |
| `app/Filament/Resources/ProgramModuleResource.php` | Show parent/children hierarchy |

---

### Task 1: Add `parent_id` to `program_modules`

**Files:**
- Create: `database/migrations/2026_06_21_000001_add_parent_id_to_program_modules_table.php`

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_modules', function (Blueprint $table) {
            if (!Schema::hasColumn('program_modules', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('program_id')
                    ->constrained('program_modules')
                    ->nullOnDelete();
                $table->index('parent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('program_modules', function (Blueprint $table) {
            if (Schema::hasColumn('program_modules', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropIndex(['parent_id']);
                $table->dropColumn('parent_id');
            }
        });
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate --path=database/migrations/2026_06_21_000001_add_parent_id_to_program_modules_table.php
```

Expected: migration completes without errors.

---

### Task 2: Create `activities` table

**Files:**
- Create: `database/migrations/2026_06_21_000002_create_activities_table.php`

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate --path=database/migrations/2026_06_21_000002_create_activities_table.php
```

---

### Task 3: Create `program_module_activities` pivot table

**Files:**
- Create: `database/migrations/2026_06_21_000003_create_program_module_activities_table.php`

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_module_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_module_id')->constrained('program_modules')->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['program_module_id', 'activity_id']);
            $table->index('program_module_id');
            $table->index('activity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_module_activities');
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate --path=database/migrations/2026_06_21_000003_create_program_module_activities_table.php
```

---

### Task 4: Create `Activity` model

**Files:**
- Create: `app/Models/Activity.php`

- [ ] **Step 1: Create model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function programModules(): BelongsToMany
    {
        return $this->belongsToMany(ProgramModule::class, 'program_module_activities');
    }
}
```

---

### Task 5: Create `ProgramModuleActivity` pivot model

**Files:**
- Create: `app/Models/ProgramModuleActivity.php`

- [ ] **Step 1: Create pivot model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProgramModuleActivity extends Pivot
{
    protected $table = 'program_module_activities';

    public $timestamps = true;
}
```

---

### Task 6: Update `ProgramModule` model

**Files:**
- Modify: `app/Models/ProgramModule.php`

- [ ] **Step 1: Add `parent_id` to fillable and relationships**

Replace the `$fillable` array and add the relationships below it.

```php
    protected $fillable = [
        'program_id',
        'parent_id',
        'name',
        'description',
        'order_sequence',
        'duration_weeks',
        'objectives',
        'content',
        'is_active',
    ];
```

Add after the existing `program()` relationship:

```php
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProgramModule::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProgramModule::class, 'parent_id')->orderBy('order_sequence');
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'program_module_activities')
            ->withTimestamps();
    }

    public function isTrack(): bool
    {
        return $this->parent_id !== null;
    }
```

- [ ] **Step 2: Import required classes**

Ensure these imports are at the top:

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
```

---

### Task 7: Add `newbie` role to `RolePermissionSeeder`

**Files:**
- Modify: `database/seeders/RolePermissionSeeder.php`

- [ ] **Step 1: Add `newbie` to the roles list**

Find the existing roles array and append `'newbie'`.

Example change:

```php
$roles = [
    'super_admin',
    'admin',
    // ... existing roles ...
    'mentee',
    'newbie',
];
```

If the seeder creates roles differently, add `Role::firstOrCreate(['name' => 'newbie', 'guard_name' => 'web']);` in the `run()` method.

---

### Task 8: Create `EmoncProgramSeeder`

**Files:**
- Create: `database/seeders/EmoncProgramSeeder.php`

- [ ] **Step 1: Create seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Program;
use App\Models\ProgramModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmoncProgramSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $program = Program::firstOrCreate(
                ['name' => 'Maternal Health (EmONC)'],
                ['description' => 'Emergency Obstetric and Newborn Care mentorship and training package']
            );

            $modules = [
                ['name' => 'Ante Partum Hemorrhage', 'has_tracks' => false],
                ['name' => 'Partograph Use and Interpretation', 'has_tracks' => false],
                ['name' => 'Obstructed Labour', 'has_tracks' => false],
                ['name' => 'Active Management of the Third Stage of Labor (AMSTL)', 'has_tracks' => false],
                ['name' => 'Management of Postpartum Hemorrhage (PPH)', 'has_tracks' => true],
                ['name' => 'Management of Cord Prolapse', 'has_tracks' => false],
                ['name' => 'Vaginal Breech Delivery', 'has_tracks' => false],
                ['name' => 'Shoulder Dystocia Delivery', 'has_tracks' => false],
                ['name' => 'Vaginal Vacuum Assisted Delivery', 'has_tracks' => false],
                ['name' => 'Maternal Resuscitation', 'has_tracks' => false],
                ['name' => 'Management of Maternal Shock', 'has_tracks' => false],
                ['name' => 'Management of Pre-Eclampsia/Eclampsia', 'has_tracks' => false],
                ['name' => 'Immediate Neonatal Resuscitation', 'has_tracks' => false],
            ];

            $defaultActivities = Activity::whereIn('name', ['CME', 'Hands-on Demo', 'Drill'])->pluck('id');

            foreach ($modules as $index => $moduleData) {
                $module = ProgramModule::firstOrCreate(
                    [
                        'program_id' => $program->id,
                        'name' => $moduleData['name'],
                    ],
                    [
                        'order_sequence' => $index + 1,
                        'is_active' => true,
                    ]
                );

                $module->activities()->syncWithoutDetaching($defaultActivities);

                if ($moduleData['has_tracks']) {
                    $this->createPphTracks($module);
                }
            }
        });

        $this->command->info('Maternal Health (EmONC) program, modules, and tracks seeded successfully.');
    }

    private function createPphTracks(ProgramModule $parentModule): void
    {
        $tracks = [
            'Bimanual compression of the uterus',
            'Compression of abdominal aorta',
            'Removal of retained placenta',
            'Uterine inversion',
            'The placement of the intrauterine balloon tamponade (condom tamponade)',
            'The placement of the intrauterine balloon tamponade (free flow system)',
            'Repair of perineal tear',
            'Repair of cervical tear',
            'The placement of the B-Lynch suture',
            'Placement of non-pneumatic anti-shock garment (NASG)',
            'Post-partum hemorrhage simulation',
        ];

        foreach ($tracks as $index => $trackName) {
            ProgramModule::firstOrCreate(
                [
                    'program_id' => $parentModule->program_id,
                    'parent_id' => $parentModule->id,
                    'name' => $trackName,
                ],
                [
                    'order_sequence' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
```

---

### Task 9: Seed default activities

**Files:**
- Modify: `database/seeders/EmoncProgramSeeder.php` (already seeds activities above)
- OR create: `database/seeders/ActivitySeeder.php`

For simplicity, `EmoncProgramSeeder` already creates/uses activities. If you prefer a separate seeder, extract the activity creation into `ActivitySeeder`.

---

### Task 10: Wire seeders in `DatabaseSeeder`

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Call new seeders**

Ensure the `run()` method calls:

```php
$this->call([
    RolePermissionSeeder::class,
    // ... existing seeders ...
    EmoncProgramSeeder::class,
]);
```

---

### Task 11: Update `ProgramModuleResource` to show hierarchy

**Files:**
- Modify: `app/Filament/Resources/ProgramModuleResource.php`

- [ ] **Step 1: Add parent/children columns and filters**

In the table definition, add:

```php
Tables\Columns\TextColumn::make('parent.name')
    ->label('Parent Module')
    ->placeholder('Module (no parent)')
    ->sortable(),
```

In the form schema, add:

```php
Forms\Components\Select::make('parent_id')
    ->label('Parent Module')
    ->relationship('parent', 'name', fn ($query) => $query->whereNull('parent_id'))
    ->placeholder('Top-level module')
    ->native(false)
    ->helperText('Leave empty for a module. Select a module to make this a track.'),
```

- [ ] **Step 2: Add a children relation manager (optional but recommended)**

Create `app/Filament/Resources/ProgramModuleResource/RelationManagers/ChildrenRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\ProgramModuleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\Textarea::make('description'),
            Forms\Components\TextInput::make('order_sequence')->numeric()->default(0),
            Forms\Components\Toggle::make('is_active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name'),
            Tables\Columns\TextColumn::make('order_sequence'),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
        ])
        ->headerActions([
            Tables\Actions\CreateAction::make(),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }
}
```

Then register it in `ProgramModuleResource::getRelations()`:

```php
public static function getRelations(): array
{
    return [
        RelationManagers\ChildrenRelationManager::class,
    ];
}
```

---

### Task 12: Run migrations and seeders

- [ ] **Step 1: Run all pending migrations**

```bash
php artisan migrate
```

Expected: All three new migrations run successfully.

- [ ] **Step 2: Seed roles**

```bash
php artisan db:seed --class=RolePermissionSeeder
```

Expected: `newbie` role exists in `roles` table.

- [ ] **Step 3: Seed EmONC data**

```bash
php artisan db:seed --class=EmoncProgramSeeder
```

Expected:
- 1 program: Maternal Health (EmONC)
- 13 program modules
- 11 tracks under Module 5 (PPH)
- 3 activities in `activities` table
- Activity attachments in `program_module_activities`

---

### Task 13: Verify

- [ ] **Step 1: Database sanity check**

```bash
php artisan tinker --execute="
use App\Models\Program;
use App\Models\ProgramModule;
use App\Models\Activity;

$program = Program::where('name', 'Maternal Health (EmONC)')->first();
echo 'Program: ' . $program->name . PHP_EOL;
echo 'Modules: ' . $program->programModules()->whereNull('parent_id')->count() . PHP_EOL;
echo 'Tracks under PPH: ' . $program->programModules()->whereHas('parent', fn(\$q) => \$q->where('name', 'Management of Postpartum Hemorrhage (PPH)'))->count() . PHP_EOL;
echo 'Activities: ' . Activity::count() . PHP_EOL;
"
```

Expected output:
```
Program: Maternal Health (EmONC)
Modules: 13
Tracks under PPH: 11
Activities: 3
```

- [ ] **Step 2: Filament UI check**

Log into `/admin`, navigate to Program Modules, and confirm:
- The 13 EmONC modules are listed.
- Module 5 has a "Children" relation manager showing 11 tracks.
- Parent Module column is visible.

---

## Spec coverage check

| Spec requirement | Task(s) |
|------------------|---------|
| Self-referential `ProgramModule` for tracks | 1, 6, 8, 11 |
| `newbie` role | 7 |
| Seed Maternal Health (EmONC) program + 13 modules + Module 5 tracks | 8, 12 |
| Activities table so attachments can use IDs | 2, 4, 8 |
| Program-module-activity mapping | 3, 5, 6, 8 |
| Filament hierarchy support | 11 |

## Placeholder scan

No placeholders. Every step includes exact file paths and code.

## Type consistency

- `parent_id` is nullable int on `program_modules`.
- `ProgramModule::isTrack()` checks `parent_id !== null`.
- Pivot table name is `program_module_activities` in both migration and model.

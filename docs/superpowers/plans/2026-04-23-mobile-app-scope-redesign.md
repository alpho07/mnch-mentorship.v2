# Mobile App Scope Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce a scope-based navigation architecture to the mobile app so users land on a role-driven hub after login and enter isolated navigation contexts per scope (Assessments, Mentorships, Trainings), with scope config managed from the Filament admin panel.

**Architecture:** ScopeShell sits between App.jsx and all feature screens — it owns scope routing and the active scope header. Each scope (Assessments, Mentorships, Trainings) is a self-contained component with its own tab/modal state. Role→scope access is stored in the database and served via `/api/v1/auth/me`, cached in IndexedDB for offline use.

**Tech Stack:** Laravel 12, Filament v3, Sanctum, React 18, IndexedDB (offline-store.js), Vite

**Spec:** `docs/superpowers/specs/2026-04-23-mobile-app-scope-redesign-design.md`

---

## File Map

### Backend — New
- `database/migrations/2026_04_23_000001_create_scopes_table.php`
- `database/migrations/2026_04_23_000002_create_scope_role_access_table.php`
- `database/seeders/ScopeSeeder.php`
- `app/Models/Scope.php`
- `app/Models/ScopeRoleAccess.php`
- `app/Filament/Resources/ScopeResource.php`
- `app/Filament/Resources/ScopeResource/Pages/CreateScope.php`
- `app/Filament/Resources/ScopeResource/Pages/EditScope.php`
- `app/Filament/Resources/ScopeResource/Pages/ListScopes.php`
- `app/Http/Controllers/Api/ScopeConfigController.php`

### Backend — Modified
- `routes/api.php` — add scope-config route
- `app/Http/Controllers/Api/AuthController.php` — include scopes in `me()` response
- `app/Http/Resources/Api/UserResource.php` — add `scopes` field
- `database/seeders/DatabaseSeeder.php` — call ScopeSeeder

### Mobile — New
- `public/m-assessment-app/src/scope-config.js`
- `public/m-assessment-app/src/components/ScopeShell.jsx`
- `public/m-assessment-app/src/components/ScopeHubScreen.jsx`
- `public/m-assessment-app/src/scopes/AssessmentsScope.jsx`
- `public/m-assessment-app/src/scopes/MentorshipsScope.jsx`
- `public/m-assessment-app/src/scopes/TrainingsScope.jsx`

### Mobile — Modified
- `public/m-assessment-app/src/services/offline-store.js` — add `scopeConfig` store (bump to v7)
- `public/m-assessment-app/src/App.jsx` — strip to auth only (~60 lines)
- `public/m-assessment-app/src/services/api.service.js` — add `scopes.getConfig()` method

---

## PHASE 1 — BACKEND

---

### Task 1: Migrations + Models

**Files:**
- Create: `database/migrations/2026_04_23_000001_create_scopes_table.php`
- Create: `database/migrations/2026_04_23_000002_create_scope_role_access_table.php`
- Create: `app/Models/Scope.php`
- Create: `app/Models/ScopeRoleAccess.php`

- [ ] **Step 1: Create the scopes migration**

```bash
php artisan make:migration create_scopes_table --path=database/migrations
```

Replace the generated file content with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scopes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('label', 100);
            $table->string('icon', 20)->default('📋');
            $table->string('color', 20)->default('#6366F1');
            $table->json('gradient');
            $table->json('tabs');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scopes');
    }
};
```

- [ ] **Step 2: Create the scope_role_access migration**

```bash
php artisan make:migration create_scope_role_access_table --path=database/migrations
```

Replace content with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scope_role_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scope_id')->constrained('scopes')->cascadeOnDelete();
            $table->string('role_name', 100);
            $table->unique(['scope_id', 'role_name']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scope_role_access');
    }
};
```

- [ ] **Step 3: Run migrations**

```bash
php artisan migrate
```

Expected: `2026_04_23_000001_create_scopes_table` and `2026_04_23_000002_create_scope_role_access_table` both show "Migrating... Done."

- [ ] **Step 4: Create ScopeRoleAccess model**

Create `app/Models/ScopeRoleAccess.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScopeRoleAccess extends Model
{
    protected $fillable = ['scope_id', 'role_name'];

    public function scope(): BelongsTo
    {
        return $this->belongsTo(Scope::class);
    }
}
```

- [ ] **Step 5: Create Scope model**

Create `app/Models/Scope.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scope extends Model
{
    protected $fillable = [
        'slug', 'label', 'icon', 'color', 'gradient', 'tabs', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'gradient'  => 'array',
        'tabs'      => 'array',
        'is_active' => 'boolean',
        'sort_order'=> 'integer',
    ];

    public function roleAccess(): HasMany
    {
        return $this->hasMany(ScopeRoleAccess::class);
    }

    /** Returns the role names that may access this scope. */
    public function roleNames(): array
    {
        return $this->roleAccess()->pluck('role_name')->toArray();
    }

    /**
     * Returns active scopes allowed for the given user.
     * super_admin always receives all active scopes (lockout prevention).
     */
    public static function forUser(User $user): Collection
    {
        $roles = $user->getRoleNames()->toArray();

        if (in_array('super_admin', $roles)) {
            return static::where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        }

        return static::where('is_active', true)
            ->whereHas('roleAccess', fn($q) => $q->whereIn('role_name', $roles))
            ->orderBy('sort_order')
            ->get();
    }
}
```

- [ ] **Step 6: Write a PHPUnit test for Scope::forUser()**

Create `tests/Unit/Models/ScopeTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Scope;
use App\Models\ScopeRoleAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScopeTest extends TestCase
{
    use RefreshDatabase;

    private function createScope(string $slug, array $roles, bool $active = true): Scope
    {
        $scope = Scope::create([
            'slug'       => $slug,
            'label'      => ucfirst($slug),
            'icon'       => '📋',
            'color'      => '#6366F1',
            'gradient'   => ['#6366F1', '#4F46E5'],
            'tabs'       => ['home', $slug, 'profile'],
            'is_active'  => $active,
            'sort_order' => 0,
        ]);
        foreach ($roles as $role) {
            ScopeRoleAccess::create(['scope_id' => $scope->id, 'role_name' => $role]);
        }
        return $scope;
    }

    public function test_returns_only_allowed_scopes_for_role(): void
    {
        $this->createScope('assessments', ['facility_mentor']);
        $this->createScope('mentorships', ['facility_mentor', 'mentee']);
        $this->createScope('trainings', ['admin']);

        Role::create(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('facility_mentor');

        $scopes = Scope::forUser($user);

        $this->assertCount(2, $scopes);
        $this->assertTrue($scopes->pluck('slug')->contains('assessments'));
        $this->assertTrue($scopes->pluck('slug')->contains('mentorships'));
        $this->assertFalse($scopes->pluck('slug')->contains('trainings'));
    }

    public function test_super_admin_gets_all_active_scopes(): void
    {
        $this->createScope('assessments', []);
        $this->createScope('mentorships', []);
        $this->createScope('trainings', []);
        $this->createScope('inactive_scope', [], false);

        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $scopes = Scope::forUser($user);

        $this->assertCount(3, $scopes); // inactive excluded
        $this->assertFalse($scopes->pluck('slug')->contains('inactive_scope'));
    }

    public function test_inactive_scope_not_returned(): void
    {
        $this->createScope('assessments', ['facility_mentor'], false);

        Role::create(['name' => 'facility_mentor', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('facility_mentor');

        $scopes = Scope::forUser($user);

        $this->assertCount(0, $scopes);
    }
}
```

- [ ] **Step 7: Run the test — expect it to pass**

```bash
composer test -- --filter=ScopeTest
```

Expected output: `3 tests, 3 assertions` — all green.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_04_23_000001_create_scopes_table.php \
        database/migrations/2026_04_23_000002_create_scope_role_access_table.php \
        app/Models/Scope.php \
        app/Models/ScopeRoleAccess.php \
        tests/Unit/Models/ScopeTest.php
git commit -m "feat: add Scope and ScopeRoleAccess models with migrations"
```

---

### Task 2: ScopeSeeder

**Files:**
- Create: `database/seeders/ScopeSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Create the seeder**

Create `database/seeders/ScopeSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Scope;
use App\Models\ScopeRoleAccess;
use Illuminate\Database\Seeder;

class ScopeSeeder extends Seeder
{
    public function run(): void
    {
        $scopes = [
            [
                'slug'       => 'assessments',
                'label'      => 'Assessments',
                'icon'       => '🏥',
                'color'      => '#6366F1',
                'gradient'   => ['#6366F1', '#4F46E5'],
                'tabs'       => ['home', 'assessments', 'reports', 'profile'],
                'is_active'  => true,
                'sort_order' => 1,
                'roles'      => [
                    'super_admin', 'admin', 'division', 'national', 'division_lead',
                    'county', 'subcounty', 'facility_mentor', 'facility_mentor_lead',
                ],
            ],
            [
                'slug'       => 'mentorships',
                'label'      => 'Mentorships',
                'icon'       => '🎓',
                'color'      => '#0EA5E9',
                'gradient'   => ['#0EA5E9', '#0284C7'],
                'tabs'       => ['home', 'mentorships', 'classes', 'profile'],
                'is_active'  => true,
                'sort_order' => 2,
                'roles'      => [
                    'super_admin', 'admin', 'division', 'national', 'division_lead',
                    'national_mentor_lead', 'county', 'county_mentor_lead',
                    'subcounty', 'subcounty_mentor_lead', 'facility_mentor',
                    'facility_mentor_lead', 'spoke_mentor', 'spoke_mentor_lead', 'mentee',
                ],
            ],
            [
                'slug'       => 'trainings',
                'label'      => 'Trainings',
                'icon'       => '📋',
                'color'      => '#10B981',
                'gradient'   => ['#10B981', '#059669'],
                'tabs'       => ['home', 'trainings', 'profile'],
                'is_active'  => true,
                'sort_order' => 3,
                'roles'      => [
                    'super_admin', 'admin', 'division', 'national',
                    'division_lead', 'national_mentor_lead',
                ],
            ],
        ];

        foreach ($scopes as $data) {
            $roles = $data['roles'];
            unset($data['roles']);

            $scope = Scope::updateOrCreate(['slug' => $data['slug']], $data);

            // Full sync — safe to run multiple times
            $scope->roleAccess()->delete();
            foreach ($roles as $role) {
                ScopeRoleAccess::create(['scope_id' => $scope->id, 'role_name' => $role]);
            }
        }
    }
}
```

- [ ] **Step 2: Register in DatabaseSeeder**

Open `database/seeders/DatabaseSeeder.php`. Add the call inside `run()`:

```php
$this->call(ScopeSeeder::class);
```

- [ ] **Step 3: Run the seeder**

```bash
php artisan db:seed --class=ScopeSeeder
```

Expected: No errors. 3 rows in `scopes` table, roles populated in `scope_role_access`.

- [ ] **Step 4: Verify in MySQL**

```bash
php artisan tinker --execute="echo App\Models\Scope::count() . ' scopes, ' . App\Models\ScopeRoleAccess::count() . ' role entries';"
```

Expected output: `3 scopes, 29 role entries`

- [ ] **Step 5: Commit**

```bash
git add database/seeders/ScopeSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat: seed initial scope and role access data"
```

---

### Task 3: ScopeResource (Filament Admin)

**Files:**
- Create: `app/Filament/Resources/ScopeResource.php`
- Create: `app/Filament/Resources/ScopeResource/Pages/ListScopes.php`
- Create: `app/Filament/Resources/ScopeResource/Pages/CreateScope.php`
- Create: `app/Filament/Resources/ScopeResource/Pages/EditScope.php`

- [ ] **Step 1: Create the Resource**

Create `app/Filament/Resources/ScopeResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScopeResource\Pages;
use App\Models\Scope;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ScopeResource extends Resource
{
    protected static ?string $model = Scope::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'App Configuration';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identity')->schema([
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->regex('/^[a-z0-9_]+$/')
                    ->maxLength(50)
                    ->helperText('Lowercase letters, numbers, underscores only. e.g. assessments'),
                Forms\Components\TextInput::make('label')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('icon')
                    ->required()
                    ->maxLength(10)
                    ->helperText('Paste an emoji. e.g. 🏥'),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower number = appears first on hub screen'),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->helperText('Inactive scopes are hidden from all users immediately'),
            ])->columns(2),

            Forms\Components\Section::make('Visual Style')->schema([
                Forms\Components\ColorPicker::make('color')
                    ->required()
                    ->label('Primary Color'),
                Forms\Components\ColorPicker::make('gradient.0')
                    ->required()
                    ->label('Gradient Start'),
                Forms\Components\ColorPicker::make('gradient.1')
                    ->required()
                    ->label('Gradient End'),
            ])->columns(3),

            Forms\Components\Section::make('Bottom Nav Tabs')->schema([
                Forms\Components\Repeater::make('tabs')
                    ->simple(
                        Forms\Components\TextInput::make('tab')
                            ->required()
                            ->placeholder('e.g. home, assessments, reports, profile')
                    )
                    ->reorderable()
                    ->addActionLabel('Add Tab')
                    ->helperText('Tab slugs in display order. Must match tab IDs used in the mobile app scope component.'),
            ]),

            Forms\Components\Section::make('Role Access')->schema([
                Forms\Components\CheckboxList::make('role_names')
                    ->label('Roles that can access this scope')
                    ->options([
                        'super_admin'             => 'Super Admin',
                        'admin'                   => 'Admin',
                        'division'                => 'Division',
                        'national'                => 'National',
                        'division_lead'           => 'Division Lead',
                        'national_mentor_lead'    => 'National Mentor Lead',
                        'county'                  => 'County',
                        'county_mentor_lead'      => 'County Mentor Lead',
                        'subcounty'               => 'Subcounty',
                        'subcounty_mentor_lead'   => 'Subcounty Mentor Lead',
                        'facility_mentor'         => 'Facility Mentor',
                        'facility_mentor_lead'    => 'Facility Mentor Lead',
                        'spoke_mentor'            => 'Spoke Mentor',
                        'spoke_mentor_lead'       => 'Spoke Mentor Lead',
                        'mentee'                  => 'Mentee',
                    ])
                    ->columns(3)
                    ->helperText('Note: super_admin always sees all active scopes regardless of this setting'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->sortable()->label('#'),
                Tables\Columns\TextColumn::make('icon'),
                Tables\Columns\TextColumn::make('label')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\TextColumn::make('tabs')
                    ->formatStateUsing(fn($state) => implode(', ', $state ?? []))
                    ->label('Tabs'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Active'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListScopes::route('/'),
            'create' => Pages\CreateScope::route('/create'),
            'edit'   => Pages\EditScope::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 2: Create ListScopes page**

Create `app/Filament/Resources/ScopeResource/Pages/ListScopes.php`:

```php
<?php

namespace App\Filament\Resources\ScopeResource\Pages;

use App\Filament\Resources\ScopeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScopes extends ListRecords
{
    protected static string $resource = ScopeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
```

- [ ] **Step 3: Create CreateScope page with role sync**

Create `app/Filament\Resources\ScopeResource\Pages\CreateScope.php`:

```php
<?php

namespace App\Filament\Resources\ScopeResource\Pages;

use App\Filament\Resources\ScopeResource;
use App\Models\ScopeRoleAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateScope extends CreateRecord
{
    protected static string $resource = ScopeResource::class;

    protected function afterCreate(): void
    {
        $this->syncRoles($this->getRecord(), $this->form->getState()['role_names'] ?? []);
    }

    private function syncRoles($record, array $roleNames): void
    {
        $record->roleAccess()->delete();
        foreach ($roleNames as $role) {
            ScopeRoleAccess::create(['scope_id' => $record->id, 'role_name' => $role]);
        }
    }
}
```

- [ ] **Step 4: Create EditScope page with role sync**

Create `app/Filament/Resources/ScopeResource/Pages/EditScope.php`:

```php
<?php

namespace App\Filament\Resources\ScopeResource\Pages;

use App\Filament\Resources\ScopeResource;
use App\Models\ScopeRoleAccess;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditScope extends EditRecord
{
    protected static string $resource = ScopeResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role_names'] = $this->getRecord()
            ->roleAccess()
            ->pluck('role_name')
            ->toArray();
        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncRoles($this->getRecord(), $this->form->getState()['role_names'] ?? []);
    }

    private function syncRoles($record, array $roleNames): void
    {
        $record->roleAccess()->delete();
        foreach ($roleNames as $role) {
            ScopeRoleAccess::create(['scope_id' => $record->id, 'role_name' => $role]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
```

- [ ] **Step 5: Verify the resource loads in Filament**

Start the server and open `/admin`. Navigate to **App Configuration → Scopes**. Verify:
- 3 seeded scopes appear in the table
- Edit "Assessments" → role checkboxes are pre-ticked correctly
- Save → no errors

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/ScopeResource.php \
        app/Filament/Resources/ScopeResource/Pages/
git commit -m "feat: add ScopeResource to Filament admin panel"
```

---

### Task 4: ScopeConfigController + API Route

**Files:**
- Create: `app/Http/Controllers/Api/ScopeConfigController.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create the controller**

Create `app/Http/Controllers/Api/ScopeConfigController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacilityAssessment;
use App\Models\MentorshipClass;
use App\Models\Scope;
use App\Models\Training;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScopeConfigController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $scopes = Scope::forUser($user);

        $result = $scopes->map(fn(Scope $scope) => [
            'id'       => $scope->slug,
            'label'    => $scope->label,
            'icon'     => $scope->icon,
            'color'    => $scope->color,
            'gradient' => $scope->gradient,
            'tabs'     => $scope->tabs,
            'summary'  => $this->summary($scope->slug, $user),
        ]);

        return response()->json(['scopes' => $result->values()]);
    }

    private function summary(string $slug, $user): array
    {
        return match ($slug) {
            'assessments' => [
                'in_progress' => FacilityAssessment::where('created_by', $user->id)
                    ->where('status', 'in_progress')->count(),
                'completed' => FacilityAssessment::where('created_by', $user->id)
                    ->where('status', 'completed')->count(),
            ],
            'mentorships' => [
                'active_classes' => MentorshipClass::whereHas(
                    'training',
                    fn($q) => $q->where('user_id', $user->id)
                )->where('status', 'active')->count(),
            ],
            'trainings' => [
                'upcoming' => Training::where('type', 'global_training')
                    ->where('start_date', '>=', now())
                    ->count(),
            ],
            default => [],
        };
    }
}
```

- [ ] **Step 2: Add the route**

Open `routes/api.php`. Inside the `auth:sanctum + api.active` middleware group, add after the Resources route:

```php
// ── Scope config ──────────────────────────────────────────────────────────────
Route::get('scope-config', [\App\Http\Controllers\Api\ScopeConfigController::class, 'index'])
    ->name('scope-config');
```

- [ ] **Step 3: Manually test the endpoint**

Using Tinker or Postman. In Tinker:

```bash
php artisan tinker
```

```php
$user = App\Models\User::first(); // use a test user
$token = $user->createToken('test')->plainTextToken;
echo $token;
```

Then in Postman or curl:

```bash
curl -H "Authorization: Bearer {token}" http://localhost:8000/api/v1/scope-config
```

Expected: JSON with `scopes` array containing 1-3 scope objects depending on the user's roles.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/ScopeConfigController.php routes/api.php
git commit -m "feat: add GET /api/v1/scope-config endpoint"
```

---

### Task 5: Include Scopes in `/auth/me` Response

**Files:**
- Modify: `app/Http/Resources/Api/UserResource.php`
- Modify: `app/Http/Controllers/Api/AuthController.php`

- [ ] **Step 1: Add scopes to UserResource**

Open `app/Http/Resources/Api/UserResource.php`. The `toArray()` method currently ends before the closing `]`. Add the `scopes` field:

```php
public function toArray($request): array
{
    return [
        'id'           => $this->id,
        'name'         => $this->name,
        'email'        => $this->email,
        'phone'        => $this->phone,
        'role'         => $this->getRoleNames()->first() ?? 'user',
        'roles'        => $this->getRoleNames()->values(),
        'status'       => $this->status ?? 'active',
        'avatar'       => $this->avatar ? asset('storage/' . $this->avatar) : null,
        'initials'     => $this->getInitials(),
        'facility_id'  => $this->facility_id,
        'subcounty_id' => $this->whenLoaded('facility', fn() => $this->facility->subcounty_id ?? null),
        'county_id'    => $this->whenLoaded('facility', fn() => $this->facility->subcounty->county_id ?? null),
        'facility'     => $this->whenLoaded('facility', fn() => [
            'id'           => $this->facility->id,
            'name'         => $this->facility->name,
            'mfl_code'     => $this->facility->mfl_code,
            'subcounty_id' => $this->facility->subcounty_id ?? null,
            'county'       => $this->facility->subcounty->county->name ?? null,
            'county_id'    => $this->facility->subcounty->county_id ?? null,
            'subcounty'    => $this->facility->subcounty->name ?? null,
        ]),
        'cadre'        => $this->whenLoaded('cadre', fn() => $this->cadre?->name),
        'department'   => $this->whenLoaded('department', fn() => $this->department?->name),
        'created_at'   => $this->created_at?->toDateString(),
        // Scope config — included so mobile app doesn't need a second API call
        'scopes'       => $this->when(
            $this->relationLoaded('roles'),
            fn() => \App\Models\Scope::forUser($this->resource)
                ->map(fn($scope) => [
                    'id'       => $scope->slug,
                    'label'    => $scope->label,
                    'icon'     => $scope->icon,
                    'color'    => $scope->color,
                    'gradient' => $scope->gradient,
                    'tabs'     => $scope->tabs,
                    'summary'  => [],   // summaries fetched lazily via /scope-config
                ])
                ->values()
        ),
    ];
}
```

- [ ] **Step 2: Verify `/auth/me` response includes scopes**

```bash
curl -H "Authorization: Bearer {token}" http://localhost:8000/api/v1/auth/me
```

Expected: `user` object includes a `scopes` array.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Resources/Api/UserResource.php
git commit -m "feat: include scopes in /auth/me response"
```

---

## PHASE 2 — MOBILE

---

### Task 6: Add scopeConfig to offline-store.js

**Files:**
- Modify: `public/m-assessment-app/src/services/offline-store.js`

The current DB version is `6`. Bump to `7` and add the `scopeConfig` store.

- [ ] **Step 1: Update DB_VERSION and STORES**

In `offline-store.js`, change line 13 from `const DB_VERSION = 6;` to:

```js
const DB_VERSION = 7;
```

In the `STORES` object, add after the last entry (`conflicts`):

```js
    // v7 — scope config (role-driven navigation)
    scopeConfig: "scopeConfig",   // keyed by "config"
```

- [ ] **Step 2: Add scopeConfig methods to the offlineStore public API**

In the `offlineStore` object, add before the `clearAll` method:

```js
    // ── Scope Config ──────────────────────────────────────────────────────────
    getScopeConfig: () => dbGet(STORES.scopeConfig, "config"),
    saveScopeConfig: (scopes) => dbPut(STORES.scopeConfig, "config", scopes),
    clearScopeConfig: () => dbDelete(STORES.scopeConfig, "config"),
```

- [ ] **Step 3: Verify the store opens without errors**

Run the dev server:

```bash
npm run dev
```

Open the app in the browser. Open DevTools → Application → IndexedDB → `mnch_offline`. Confirm version is now `7` and the `scopeConfig` object store exists.

- [ ] **Step 4: Commit**

```bash
cd public/m-assessment-app
git add src/services/offline-store.js
git commit -m "feat(mobile): add scopeConfig store to IndexedDB (v7)"
```

---

### Task 7: Create scope-config.js

**Files:**
- Create: `public/m-assessment-app/src/scope-config.js`

- [ ] **Step 1: Create the file**

Create `public/m-assessment-app/src/scope-config.js`:

```js
/**
 * scope-config.js
 *
 * Provides getScopesForUser() — the single function ScopeShell calls to get
 * the list of scopes a user may access.
 *
 * Source of truth: the database (via /auth/me → cached in IndexedDB).
 * Fallback: FALLBACK_SCOPES — used only on first offline boot before any
 * successful login has populated the cache.
 */

import offlineStore from "./services/offline-store.js";

// ── Hardcoded fallback (first offline boot only) ─────────────────────────────
const FALLBACK_SCOPES = [
    {
        id: "assessments",
        label: "Assessments",
        icon: "🏥",
        color: "#6366F1",
        gradient: ["#6366F1", "#4F46E5"],
        tabs: ["home", "assessments", "reports", "profile"],
        summary: {},
    },
    {
        id: "mentorships",
        label: "Mentorships",
        icon: "🎓",
        color: "#0EA5E9",
        gradient: ["#0EA5E9", "#0284C7"],
        tabs: ["home", "mentorships", "classes", "profile"],
        summary: {},
    },
    {
        id: "trainings",
        label: "Trainings",
        icon: "📋",
        color: "#10B981",
        gradient: ["#10B981", "#059669"],
        tabs: ["home", "trainings", "profile"],
        summary: {},
    },
];

/**
 * Returns scopes from IndexedDB cache (populated by ScopeShell from /auth/me).
 * Falls back to FALLBACK_SCOPES when cache is empty.
 *
 * @returns {Promise<Array>} Array of scope objects
 */
export async function getScopesFromCache() {
    const cached = await offlineStore.getScopeConfig();
    return Array.isArray(cached) && cached.length > 0 ? cached : FALLBACK_SCOPES;
}

/**
 * Saves scope config to IndexedDB cache.
 * Called by ScopeShell after a successful /auth/me response.
 *
 * @param {Array} scopes — scopes array from /auth/me user.scopes
 */
export async function cacheScopeConfig(scopes) {
    if (Array.isArray(scopes) && scopes.length > 0) {
        await offlineStore.saveScopeConfig(scopes);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add public/m-assessment-app/src/scope-config.js
git commit -m "feat(mobile): add scope-config.js with cache reader and fallback"
```

---

### Task 8: Refactor App.jsx

**Files:**
- Modify: `public/m-assessment-app/src/App.jsx`

App.jsx hands off all navigation state to ScopeShell. It keeps only: token check, `api.auth.me()`, `user` state, `loading` state, offline ID-resolved event listeners.

- [ ] **Step 1: Replace App.jsx content**

Open `public/m-assessment-app/src/App.jsx`. Replace the entire file with:

```jsx
import { useState, useEffect } from "react";
import { LoginScreen } from "./screens/screen-login.jsx";
import { ScopeShell } from "./components/ScopeShell.jsx";
import api from "./services/api.service.js";

// ── normaliseUser — kept here because it's needed for session restore ─────────
function normaliseUser(u) {
    if (!u) return u;
    const fac = typeof u.facility === "object" && u.facility !== null ? u.facility : null;
    return {
        ...u,
        facility:    fac?.name ?? (typeof u.facility === "string" ? u.facility : ""),
        facility_id: u.facility_id ?? fac?.id ?? null,
        county:      fac?.county ?? u.county ?? "",
        county_id:   u.county_id ?? fac?.county_id ?? null,
        subcounty:   fac?.subcounty ?? u.subcounty ?? "",
        mfl_code:    fac?.mfl_code ?? u.mfl_code ?? "",
        initials:    u.initials || ((u.name || "??").split(" ").map(p => p[0]).join("").slice(0, 2).toUpperCase()),
    };
}

export default function App() {
    const [user, setUser]       = useState(null);
    const [loading, setLoading] = useState(true);

    // ── Session restore ─────────────────────────────────────────────────────
    useEffect(() => {
        const token = api.getToken();
        if (!token) { setLoading(false); return; }

        api.auth.me()
            .then(data => {
                const u = data?.user ?? data;
                if (u?.id) setUser(normaliseUser(u));
            })
            .catch(() => api.clearToken())
            .finally(() => setLoading(false));
    }, []);

    if (loading) {
        return (
            <div style={{ display: "flex", alignItems: "center", justifyContent: "center", height: "100vh", background: "#0f172a" }}>
                <div style={{ color: "white", fontSize: 14, opacity: 0.6 }}>Loading…</div>
            </div>
        );
    }

    if (!user) {
        return <LoginScreen onLogin={(u) => setUser(normaliseUser(u))} />;
    }

    return (
        <ScopeShell
            user={user}
            onLogout={() => { api.clearToken(); setUser(null); }}
            onUserUpdate={(u) => setUser(normaliseUser(u))}
        />
    );
}
```

- [ ] **Step 2: Verify the app still loads**

```bash
npm run dev
```

Open the app in the browser. Login should work. After login you'll see a blank screen (ScopeShell doesn't exist yet — that's expected).

- [ ] **Step 3: Commit**

```bash
git add public/m-assessment-app/src/App.jsx
git commit -m "refactor(mobile): strip App.jsx to auth-only (~50 lines)"
```

---

### Task 9: ScopeShell.jsx

**Files:**
- Create: `public/m-assessment-app/src/components/ScopeShell.jsx`

ScopeShell owns: loading scope config, routing logic (0/1/2+ scopes), rendering hub or active scope, and the scope header.

- [ ] **Step 1: Create ScopeShell.jsx**

Create `public/m-assessment-app/src/components/ScopeShell.jsx`:

```jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import { getScopesFromCache, cacheScopeConfig } from "../scope-config.js";
import { ScopeHubScreen } from "./ScopeHubScreen.jsx";
import { AssessmentsScope } from "../scopes/AssessmentsScope.jsx";
import { MentorshipsScope } from "../scopes/MentorshipsScope.jsx";
import { TrainingsScope }   from "../scopes/TrainingsScope.jsx";

// ── Scope header (shown inside any active scope) ──────────────────────────────
function ScopeHeader({ scope, onSwitch, showSwitch, user, onLogout }) {
    const [showProfile, setShowProfile] = useState(false);

    return (
        <>
            <div style={{
                position: "sticky", top: 0, zIndex: 100,
                background: scope.gradient
                    ? `linear-gradient(135deg, ${scope.gradient[0]}, ${scope.gradient[1]})`
                    : scope.color ?? T.indigo,
                padding: "12px 16px",
                display: "flex", alignItems: "center", gap: 12,
            }}>
                {showSwitch && (
                    <button
                        onClick={onSwitch}
                        style={{ background: "rgba(255,255,255,0.15)", border: "none", borderRadius: 8, padding: "6px 8px", cursor: "pointer", color: "white", fontSize: 18, lineHeight: 1 }}
                        aria-label="Switch scope"
                    >
                        ⊞
                    </button>
                )}
                <span style={{ flex: 1, color: "white", fontWeight: 700, fontSize: 16 }}>
                    {scope.icon} {scope.label}
                </span>
                <button
                    onClick={() => setShowProfile(true)}
                    style={{ background: "rgba(255,255,255,0.2)", border: "none", borderRadius: "50%", width: 36, height: 36, cursor: "pointer", color: "white", fontWeight: 700, fontSize: 13 }}
                >
                    {user?.initials ?? "?"}
                </button>
            </div>

            {/* Profile sheet */}
            {showProfile && (
                <div
                    style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.5)", zIndex: 200, display: "flex", alignItems: "flex-end" }}
                    onClick={() => setShowProfile(false)}
                >
                    <div
                        style={{ background: T.surface ?? "#1e293b", width: "100%", borderRadius: "16px 16px 0 0", padding: 24 }}
                        onClick={e => e.stopPropagation()}
                    >
                        <p style={{ color: "white", fontWeight: 700, marginBottom: 4 }}>{user?.name}</p>
                        <p style={{ color: "rgba(255,255,255,0.5)", fontSize: 13, marginBottom: 24 }}>{user?.email}</p>
                        <button
                            onClick={onLogout}
                            style={{ width: "100%", padding: "12px 0", background: "#ef4444", color: "white", border: "none", borderRadius: 10, fontWeight: 600, cursor: "pointer" }}
                        >
                            Log Out
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}

// ── ScopeShell ────────────────────────────────────────────────────────────────
export function ScopeShell({ user, onLogout, onUserUpdate }) {
    const [scopes, setScopes]           = useState([]);
    const [activeScope, setActiveScope] = useState(null);
    const [ready, setReady]             = useState(false);

    // Load scope config from cache, then auto-route if 0 or 1 scopes
    useEffect(() => {
        const userScopes = user?.scopes;

        // user.scopes came from /auth/me — cache it and use it
        if (Array.isArray(userScopes) && userScopes.length > 0) {
            cacheScopeConfig(userScopes);
            applyScopes(userScopes);
        } else {
            // fall back to IndexedDB cache or hardcoded fallback
            getScopesFromCache().then(applyScopes);
        }
    }, [user?.id]);

    function applyScopes(resolved) {
        setScopes(resolved);
        if (resolved.length === 1) {
            // Single scope — skip hub entirely
            setActiveScope(resolved[0]);
        }
        setReady(true);
    }

    if (!ready) {
        return (
            <div style={{ display: "flex", alignItems: "center", justifyContent: "center", height: "100vh", background: "#0f172a" }}>
                <div style={{ color: "white", fontSize: 14, opacity: 0.6 }}>Loading…</div>
            </div>
        );
    }

    // 0 scopes — administrator hasn't configured access
    if (scopes.length === 0) {
        return (
            <div style={{ display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", height: "100vh", background: "#0f172a", padding: 32, textAlign: "center" }}>
                <div style={{ fontSize: 48, marginBottom: 16 }}>🔒</div>
                <p style={{ color: "white", fontWeight: 700, fontSize: 18, marginBottom: 8 }}>No areas configured</p>
                <p style={{ color: "rgba(255,255,255,0.5)", fontSize: 14, marginBottom: 32 }}>Contact your administrator to get access.</p>
                <button onClick={onLogout} style={{ padding: "10px 24px", background: "#374151", color: "white", border: "none", borderRadius: 8, cursor: "pointer" }}>Log Out</button>
            </div>
        );
    }

    const showSwitch = scopes.length > 1;

    // Hub — no active scope yet (or user pressed the switch button)
    if (!activeScope) {
        return (
            <ScopeHubScreen
                scopes={scopes}
                user={user}
                onSelect={setActiveScope}
                onLogout={onLogout}
            />
        );
    }

    // Active scope — render appropriate scope component
    const scopeProps = {
        user,
        scope: activeScope,
        onLogout,
        onUserUpdate,
        header: (
            <ScopeHeader
                scope={activeScope}
                onSwitch={() => setActiveScope(null)}
                showSwitch={showSwitch}
                user={user}
                onLogout={onLogout}
            />
        ),
    };

    return (
        <>
            {activeScope.id === "assessments"  && <AssessmentsScope  {...scopeProps} />}
            {activeScope.id === "mentorships"  && <MentorshipsScope  {...scopeProps} />}
            {activeScope.id === "trainings"    && <TrainingsScope    {...scopeProps} />}
        </>
    );
}
```

- [ ] **Step 2: Verify the app renders (even with stub scopes)**

The app will crash because ScopeHubScreen and scope components don't exist yet. Create temporary stubs so you can verify ScopeShell renders:

```bash
# In public/m-assessment-app/src/components/
echo 'export function ScopeHubScreen() { return <div style={{color:"white",padding:32}}>Hub stub</div>; }' > ScopeHubScreen.jsx

# In public/m-assessment-app/src/scopes/
mkdir -p ../scopes
echo 'export function AssessmentsScope({header}) { return <>{header}<div style={{color:"white",padding:32}}>Assessments stub</div></>; }' > ../scopes/AssessmentsScope.jsx
echo 'export function MentorshipsScope({header}) { return <>{header}<div style={{color:"white",padding:32}}>Mentorships stub</div></>; }' > ../scopes/MentorshipsScope.jsx
echo 'export function TrainingsScope({header}) { return <>{header}<div style={{color:"white",padding:32}}>Trainings stub</div></>; }' > ../scopes/TrainingsScope.jsx
```

Open the app. After login you should see either the hub (multi-scope user) or jump directly into a scope (single-scope user like mentee). The scope header should show with the ⊞ switcher button.

- [ ] **Step 3: Commit**

```bash
git add public/m-assessment-app/src/components/ScopeShell.jsx \
        public/m-assessment-app/src/scopes/
git commit -m "feat(mobile): add ScopeShell with scope routing and header"
```

---

### Task 10: ScopeHubScreen.jsx

**Files:**
- Modify: `public/m-assessment-app/src/components/ScopeHubScreen.jsx` (replace stub)

- [ ] **Step 1: Replace the stub with the full hub screen**

Replace `public/m-assessment-app/src/components/ScopeHubScreen.jsx`:

```jsx
import { useState, useEffect } from "react";
import { T } from "../constants.js";
import api from "../services/api.service.js";

function ScopeCard({ scope, onSelect, index }) {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const t = setTimeout(() => setVisible(true), index * 80);
        return () => clearTimeout(t);
    }, [index]);

    const grad = scope.gradient
        ? `linear-gradient(135deg, ${scope.gradient[0]}, ${scope.gradient[1]})`
        : scope.color;

    const summaryText = () => {
        const s = scope.summary ?? {};
        if (s.in_progress != null)   return `${s.in_progress} in progress · ${s.completed ?? 0} done`;
        if (s.active_classes != null) return `${s.active_classes} active class${s.active_classes !== 1 ? "es" : ""}`;
        if (s.upcoming != null)       return `${s.upcoming} upcoming`;
        return null;
    };

    return (
        <button
            onClick={() => onSelect(scope)}
            style={{
                background: grad,
                border: "none",
                borderRadius: 16,
                padding: "24px 20px",
                cursor: "pointer",
                textAlign: "left",
                opacity: visible ? 1 : 0,
                transform: visible ? "scale(1)" : "scale(0.92)",
                transition: "opacity 0.3s ease, transform 0.3s cubic-bezier(0.34,1.56,0.64,1)",
                boxShadow: "0 4px 20px rgba(0,0,0,0.3)",
                minHeight: 130,
                display: "flex",
                flexDirection: "column",
                justifyContent: "space-between",
            }}
        >
            <span style={{ fontSize: 36 }}>{scope.icon}</span>
            <div>
                <p style={{ color: "white", fontWeight: 700, fontSize: 17, margin: "0 0 4px" }}>
                    {scope.label}
                </p>
                {summaryText() && (
                    <p style={{ color: "rgba(255,255,255,0.75)", fontSize: 12, margin: 0 }}>
                        {summaryText()}
                    </p>
                )}
            </div>
        </button>
    );
}

export function ScopeHubScreen({ scopes, user, onSelect, onLogout }) {
    const [enriched, setEnriched] = useState(scopes);
    const [showProfile, setShowProfile] = useState(false);

    // Fetch live summaries — non-blocking, updates cards after render
    useEffect(() => {
        api.scopes?.getConfig?.()
            .then(data => {
                if (Array.isArray(data?.scopes)) setEnriched(data.scopes);
            })
            .catch(() => {}); // silently use cached summaries
    }, []);

    const hour = new Date().getHours();
    const greeting = hour < 12 ? "Good morning" : hour < 17 ? "Good afternoon" : "Good evening";

    // Lay out cards: 2-column grid, last card full-width if odd count
    const rows = [];
    for (let i = 0; i < enriched.length; i += 2) {
        rows.push(enriched.slice(i, i + 2));
    }

    return (
        <div style={{ minHeight: "100vh", background: "#0f172a", padding: "0 0 32px" }}>
            {/* Header */}
            <div style={{ background: "linear-gradient(135deg, #1e1b4b, #1e293b)", padding: "48px 20px 28px" }}>
                <div style={{ display: "flex", alignItems: "flex-start", justifyContent: "space-between" }}>
                    <div>
                        <p style={{ color: "rgba(255,255,255,0.55)", fontSize: 13, margin: "0 0 4px" }}>
                            {greeting},
                        </p>
                        <p style={{ color: "white", fontWeight: 700, fontSize: 22, margin: "0 0 2px" }}>
                            {user?.name?.split(" ")[0] ?? "there"}
                        </p>
                        <p style={{ color: "rgba(255,255,255,0.35)", fontSize: 12, margin: 0 }}>
                            MNCH Mentorship Platform
                        </p>
                    </div>
                    <button
                        onClick={() => setShowProfile(true)}
                        style={{ background: "rgba(255,255,255,0.15)", border: "none", borderRadius: "50%", width: 42, height: 42, color: "white", fontWeight: 700, fontSize: 14, cursor: "pointer", flexShrink: 0 }}
                    >
                        {user?.initials ?? "?"}
                    </button>
                </div>
            </div>

            {/* Scope cards */}
            <div style={{ padding: "24px 16px 0" }}>
                <p style={{ color: "rgba(255,255,255,0.5)", fontSize: 13, fontWeight: 600, letterSpacing: "0.08em", textTransform: "uppercase", marginBottom: 16 }}>
                    Choose your area
                </p>
                <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
                    {rows.map((row, ri) => (
                        <div key={ri} style={{ display: "grid", gridTemplateColumns: row.length === 1 ? "1fr" : "1fr 1fr", gap: 12 }}>
                            {row.map((scope, si) => (
                                <ScopeCard
                                    key={scope.id}
                                    scope={scope}
                                    onSelect={onSelect}
                                    index={ri * 2 + si}
                                />
                            ))}
                        </div>
                    ))}
                </div>
            </div>

            {/* Profile sheet */}
            {showProfile && (
                <div
                    style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.6)", zIndex: 200, display: "flex", alignItems: "flex-end" }}
                    onClick={() => setShowProfile(false)}
                >
                    <div
                        style={{ background: "#1e293b", width: "100%", borderRadius: "16px 16px 0 0", padding: "24px 20px 32px" }}
                        onClick={e => e.stopPropagation()}
                    >
                        <p style={{ color: "white", fontWeight: 700, fontSize: 16, marginBottom: 4 }}>{user?.name}</p>
                        <p style={{ color: "rgba(255,255,255,0.45)", fontSize: 13, marginBottom: 28 }}>{user?.email}</p>
                        <button
                            onClick={onLogout}
                            style={{ width: "100%", padding: "13px 0", background: "#ef4444", color: "white", border: "none", borderRadius: 10, fontWeight: 600, fontSize: 15, cursor: "pointer" }}
                        >
                            Log Out
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
```

- [ ] **Step 2: Add `scopes.getConfig` to api.service.js**

Open `public/m-assessment-app/src/services/api.service.js`. Inside the `_rawApi` object, add after `resources`:

```js
    scopes: {
        getConfig: () => get('/scope-config'),
    },
```

And in the offline-aware `api` object (the `const api = { ... }` block), add the same:

```js
    scopes: {
        getConfig: () => _rawApi.scopes.getConfig(),
    },
```

- [ ] **Step 3: Verify hub screen renders**

Login with a multi-scope user (e.g. super_admin or admin). You should see:
- Greeting with user's first name
- Scope cards with gradients (Assessments indigo, Mentorships sky, Trainings emerald)
- Staggered fade-in animation
- Tapping a card should enter that scope (showing the stub screen for now)
- Avatar button opens profile sheet with logout

- [ ] **Step 4: Commit**

```bash
git add public/m-assessment-app/src/components/ScopeHubScreen.jsx \
        public/m-assessment-app/src/services/api.service.js
git commit -m "feat(mobile): add ScopeHubScreen with animated scope cards"
```

---

### Task 11: AssessmentsScope.jsx

**Files:**
- Modify: `public/m-assessment-app/src/scopes/AssessmentsScope.jsx` (replace stub)

Wires all existing assessment screens. These screens are unchanged — only their mounting location moves here.

- [ ] **Step 1: Replace the stub**

Replace `public/m-assessment-app/src/scopes/AssessmentsScope.jsx`:

```jsx
import { useState } from "react";
import { T } from "../constants.js";
import { DashboardScreen }        from "../screens/screen-dashboard.jsx";
import { AssessmentsListScreen }  from "../screens/screen-assessments-list.jsx";
import { AssessmentDetailScreen } from "../screens/screen-assessment-detail.jsx";
import { AssessmentFormScreen }   from "../screens/screen-assessment-form.jsx";
import { AssessmentReportScreen } from "../screens/screen-assessment-report.jsx";
import { ReportsScreen }          from "../screens/screen-reports.jsx";
import { EmailJobsScreen }        from "../screens/screen-email-jobs.jsx";
import { ProfileScreen }          from "../screens/screen-profile.jsx";

const TABS = [
    { id: "home",        icon: "🏠", label: "Home" },
    { id: "assessments", icon: "📋", label: "Assessments" },
    { id: "reports",     icon: "📊", label: "Reports" },
    { id: "profile",     icon: "👤", label: "Profile" },
];

function BottomNav({ tabs, active, onChange }) {
    return (
        <nav style={{
            position: "fixed", bottom: 0, left: 0, right: 0,
            background: "#1e293b",
            borderTop: "1px solid rgba(255,255,255,0.08)",
            display: "flex",
            zIndex: 50,
            paddingBottom: "env(safe-area-inset-bottom)",
        }}>
            {tabs.map(tab => (
                <button
                    key={tab.id}
                    onClick={() => onChange(tab.id)}
                    style={{
                        flex: 1, padding: "10px 0 8px",
                        background: "none", border: "none", cursor: "pointer",
                        display: "flex", flexDirection: "column", alignItems: "center", gap: 3,
                        color: active === tab.id ? "#6366F1" : "rgba(255,255,255,0.35)",
                        fontSize: active === tab.id ? 10 : 10,
                        fontWeight: active === tab.id ? 700 : 400,
                        transition: "color 0.15s",
                    }}
                >
                    <span style={{ fontSize: 20 }}>{tab.icon}</span>
                    <span>{tab.label}</span>
                </button>
            ))}
        </nav>
    );
}

export function AssessmentsScope({ user, header, onLogout, onUserUpdate }) {
    const [tab, setTab]     = useState("home");
    const [modal, setModal] = useState(null);

    // ── Modal render ────────────────────────────────────────────────────────
    if (modal?.type === "detail") {
        return (
            <AssessmentDetailScreen
                assessment={modal.data}
                onBack={() => setModal(null)}
                onStartForm={(a, section) => setModal({ type: "form", data: { assessment: a, section } })}
                onViewReport={(a) => setModal({ type: "report", data: a })}
                user={user}
            />
        );
    }
    if (modal?.type === "form") {
        return (
            <AssessmentFormScreen
                assessment={modal.data.assessment}
                initialSection={modal.data.section}
                onBack={() => setModal({ type: "detail", data: modal.data.assessment })}
                onComplete={(a) => setModal({ type: "detail", data: a })}
                user={user}
            />
        );
    }
    if (modal?.type === "report") {
        return (
            <AssessmentReportScreen
                assessment={modal.data}
                onBack={() => setModal({ type: "detail", data: modal.data })}
                user={user}
            />
        );
    }
    if (modal?.type === "emailJobs") {
        return (
            <EmailJobsScreen onBack={() => setModal(null)} user={user} />
        );
    }

    // ── Tab render ──────────────────────────────────────────────────────────
    return (
        <div style={{ paddingBottom: 64, minHeight: "100vh", background: "#0f172a" }}>
            {header}
            {tab === "home" && (
                <DashboardScreen
                    user={user}
                    onViewAssessment={(a) => setModal({ type: "detail", data: a })}
                />
            )}
            {tab === "assessments" && (
                <AssessmentsListScreen
                    user={user}
                    onViewAssessment={(a) => setModal({ type: "detail", data: a })}
                />
            )}
            {tab === "reports" && (
                <ReportsScreen
                    user={user}
                    onViewEmailJobs={() => setModal({ type: "emailJobs" })}
                />
            )}
            {tab === "profile" && (
                <ProfileScreen user={user} onLogout={onLogout} onUserUpdate={onUserUpdate} />
            )}
            <BottomNav tabs={TABS} active={tab} onChange={setTab} />
        </div>
    );
}
```

- [ ] **Step 2: Verify assessments scope works end-to-end**

Login as a user with assessments access. Select "Assessments" from the hub (or auto-load if single scope). Verify:
- Bottom nav shows: Home · Assessments · Reports · Profile
- Home tab loads the dashboard
- Assessments tab shows the list
- Tapping an assessment opens the detail modal
- Scope header shows "🏥 Assessments" with ⊞ switcher
- ⊞ returns to hub (or is hidden for single-scope users)

- [ ] **Step 3: Commit**

```bash
git add public/m-assessment-app/src/scopes/AssessmentsScope.jsx
git commit -m "feat(mobile): wire AssessmentsScope with all existing assessment screens"
```

---

### Task 12: MentorshipsScope.jsx

**Files:**
- Modify: `public/m-assessment-app/src/scopes/MentorshipsScope.jsx` (replace stub)

Wires existing mentorship screens. Includes mentee variant (reduced tab set).

- [ ] **Step 1: Replace the stub**

Replace `public/m-assessment-app/src/scopes/MentorshipsScope.jsx`:

```jsx
import { useState } from "react";
import { MentorshipsListScreen }  from "../screens/screen-mentorships-list.jsx";
import { MentorshipDetailScreen } from "../screens/screen-mentorship-detail.jsx";
import { MentorshipFormScreen }   from "../screens/screen-mentorship-form.jsx";
import { ClassDetailScreen }      from "../screens/screen-class-detail.jsx";
import { ClassFormScreen }        from "../screens/screen-class-form.jsx";
import { ModuleDetailScreen }     from "../screens/screen-module-detail.jsx";
import { ModulePickerScreen }     from "../screens/screen-module-picker.jsx";
import { AttendanceRosterScreen } from "../screens/screen-attendance-roster.jsx";
import { SessionNotesScreen }     from "../screens/screen-session-notes.jsx";
import { MenteeManagerScreen }    from "../screens/screen-mentee-manager.jsx";
import { MyClassesScreen }        from "../screens/screen-my-classes.jsx";
import { ClassProgressScreen }    from "../screens/screen-class-progress.jsx";
import { ProfileScreen }          from "../screens/screen-profile.jsx";

const MENTOR_TABS = [
    { id: "home",         icon: "🏠", label: "Home" },
    { id: "mentorships",  icon: "🎓", label: "Mentorships" },
    { id: "classes",      icon: "📚", label: "Classes" },
    { id: "profile",      icon: "👤", label: "Profile" },
];

const MENTEE_TABS = [
    { id: "home",       icon: "🏠", label: "Home" },
    { id: "my-classes", icon: "📚", label: "My Classes" },
    { id: "profile",    icon: "👤", label: "Profile" },
];

function BottomNav({ tabs, active, onChange }) {
    return (
        <nav style={{
            position: "fixed", bottom: 0, left: 0, right: 0,
            background: "#1e293b",
            borderTop: "1px solid rgba(255,255,255,0.08)",
            display: "flex", zIndex: 50,
            paddingBottom: "env(safe-area-inset-bottom)",
        }}>
            {tabs.map(tab => (
                <button
                    key={tab.id}
                    onClick={() => onChange(tab.id)}
                    style={{
                        flex: 1, padding: "10px 0 8px",
                        background: "none", border: "none", cursor: "pointer",
                        display: "flex", flexDirection: "column", alignItems: "center", gap: 3,
                        color: active === tab.id ? "#0EA5E9" : "rgba(255,255,255,0.35)",
                        fontSize: 10,
                        fontWeight: active === tab.id ? 700 : 400,
                        transition: "color 0.15s",
                    }}
                >
                    <span style={{ fontSize: 20 }}>{tab.icon}</span>
                    <span>{tab.label}</span>
                </button>
            ))}
        </nav>
    );
}

// Simple mentorships home showing a summary message
function MentorshipsHomeScreen({ user }) {
    return (
        <div style={{ padding: "24px 16px" }}>
            <p style={{ color: "white", fontWeight: 700, fontSize: 20, marginBottom: 8 }}>
                Welcome, {user?.name?.split(" ")[0]}
            </p>
            <p style={{ color: "rgba(255,255,255,0.5)", fontSize: 14 }}>
                Manage your mentorship classes and track progress.
            </p>
        </div>
    );
}

function MenteeHomeScreen({ user }) {
    return (
        <div style={{ padding: "24px 16px" }}>
            <p style={{ color: "white", fontWeight: 700, fontSize: 20, marginBottom: 8 }}>
                Welcome, {user?.name?.split(" ")[0]}
            </p>
            <p style={{ color: "rgba(255,255,255,0.5)", fontSize: 14 }}>
                View your class modules and track your learning progress.
            </p>
        </div>
    );
}

export function MentorshipsScope({ user, header, onLogout, onUserUpdate }) {
    const isMentee = (user?.roles ?? []).includes("mentee");
    const TABS = isMentee ? MENTEE_TABS : MENTOR_TABS;

    const [tab, setTab]     = useState("home");
    const [modal, setModal] = useState(null);

    // ── Modal render ────────────────────────────────────────────────────────
    if (modal?.type === "mentorshipDetail") {
        return (
            <MentorshipDetailScreen
                training={modal.data}
                onBack={() => setModal(null)}
                onViewClass={(c) => setModal({ type: "classDetail", data: c, training: modal.data })}
                onEdit={(t) => setModal({ type: "mentorshipForm", data: t })}
                user={user}
            />
        );
    }
    if (modal?.type === "mentorshipForm") {
        return (
            <MentorshipFormScreen
                training={modal.data ?? null}
                onBack={() => setModal(null)}
                onSaved={(t) => setModal({ type: "mentorshipDetail", data: t })}
                user={user}
            />
        );
    }
    if (modal?.type === "classDetail") {
        return (
            <ClassDetailScreen
                cls={modal.data}
                training={modal.training}
                onBack={() => setModal({ type: "mentorshipDetail", data: modal.training })}
                onViewModule={(m, c) => setModal({ type: "moduleDetail", data: m, cls: c, training: modal.training })}
                onEditClass={(c, t) => setModal({ type: "classForm", data: c, training: t })}
                onManageMentees={(c, t) => setModal({ type: "menteeManager", data: c, training: t })}
                user={user}
            />
        );
    }
    if (modal?.type === "classForm") {
        return (
            <ClassFormScreen
                cls={modal.data}
                training={modal.training}
                onBack={() => setModal({ type: "classDetail", data: modal.data, training: modal.training })}
                onSaved={(c) => setModal({ type: "classDetail", data: c, training: modal.training })}
                user={user}
            />
        );
    }
    if (modal?.type === "moduleDetail") {
        return (
            <ModuleDetailScreen
                module={modal.data}
                cls={modal.cls}
                onBack={() => setModal({ type: "classDetail", data: modal.cls, training: modal.training })}
                onAttendance={(m, c) => setModal({ type: "attendance", data: m, cls: c, training: modal.training })}
                onSessionNotes={(s, m) => setModal({ type: "sessionNotes", data: s, module: m, cls: modal.cls, training: modal.training })}
                user={user}
            />
        );
    }
    if (modal?.type === "attendance") {
        return (
            <AttendanceRosterScreen
                module={modal.data}
                cls={modal.cls}
                onBack={() => setModal({ type: "moduleDetail", data: modal.data, cls: modal.cls, training: modal.training })}
                user={user}
            />
        );
    }
    if (modal?.type === "sessionNotes") {
        return (
            <SessionNotesScreen
                session={modal.data}
                module={modal.module}
                onBack={() => setModal({ type: "moduleDetail", data: modal.module, cls: modal.cls, training: modal.training })}
                user={user}
            />
        );
    }
    if (modal?.type === "menteeManager") {
        return (
            <MenteeManagerScreen
                cls={modal.data}
                training={modal.training}
                onBack={() => setModal({ type: "classDetail", data: modal.data, training: modal.training })}
                user={user}
            />
        );
    }
    if (modal?.type === "modulePicker") {
        return (
            <ModulePickerScreen
                cls={modal.data}
                onBack={() => setModal({ type: "classDetail", data: modal.data, training: modal.training })}
                user={user}
            />
        );
    }
    if (modal?.type === "classProgress") {
        return (
            <ClassProgressScreen
                cls={modal.data}
                onBack={() => setModal(null)}
                user={user}
            />
        );
    }

    // ── Tab render ──────────────────────────────────────────────────────────
    return (
        <div style={{ paddingBottom: 64, minHeight: "100vh", background: "#0f172a" }}>
            {header}
            {tab === "home" && !isMentee && <MentorshipsHomeScreen user={user} />}
            {tab === "home" && isMentee  && <MenteeHomeScreen user={user} />}
            {tab === "mentorships" && (
                <MentorshipsListScreen
                    user={user}
                    onViewMentorship={(t) => setModal({ type: "mentorshipDetail", data: t })}
                    onCreateMentorship={() => setModal({ type: "mentorshipForm", data: null })}
                />
            )}
            {tab === "classes" && (
                <MentorshipsListScreen
                    user={user}
                    defaultView="classes"
                    onViewMentorship={(t) => setModal({ type: "mentorshipDetail", data: t })}
                />
            )}
            {tab === "my-classes" && (
                <MyClassesScreen
                    user={user}
                    onViewClass={(c) => setModal({ type: "classProgress", data: c })}
                />
            )}
            {tab === "profile" && (
                <ProfileScreen user={user} onLogout={onLogout} onUserUpdate={onUserUpdate} />
            )}
            <BottomNav tabs={TABS} active={tab} onChange={setTab} />
        </div>
    );
}
```

- [ ] **Step 2: Verify mentorships scope**

Login as a mentor role. Select Mentorships. Verify:
- Mentor tabs: Home · Mentorships · Classes · Profile
- Mentorships list loads
- Navigation into a mentorship → class → module works

Login as `mentee` role (auto-loads Mentorships). Verify:
- No hub shown (auto-loaded)
- Mentee tabs: Home · My Classes · Profile
- No ⊞ switcher in header (single scope)
- My Classes shows mentee class list

- [ ] **Step 3: Commit**

```bash
git add public/m-assessment-app/src/scopes/MentorshipsScope.jsx
git commit -m "feat(mobile): wire MentorshipsScope with mentor and mentee variants"
```

---

### Task 13: TrainingsScope.jsx

**Files:**
- Modify: `public/m-assessment-app/src/scopes/TrainingsScope.jsx` (replace stub)

- [ ] **Step 1: Replace the stub**

Replace `public/m-assessment-app/src/scopes/TrainingsScope.jsx`:

```jsx
import { useState } from "react";
import { TrainingsListScreen }  from "../screens/screen-trainings-list.jsx";
import { TrainingDetailScreen } from "../screens/screen-training-detail.jsx";
import { ProfileScreen }        from "../screens/screen-profile.jsx";

const TABS = [
    { id: "home",      icon: "🏠", label: "Home" },
    { id: "trainings", icon: "📋", label: "Trainings" },
    { id: "profile",   icon: "👤", label: "Profile" },
];

function BottomNav({ tabs, active, onChange }) {
    return (
        <nav style={{
            position: "fixed", bottom: 0, left: 0, right: 0,
            background: "#1e293b",
            borderTop: "1px solid rgba(255,255,255,0.08)",
            display: "flex", zIndex: 50,
            paddingBottom: "env(safe-area-inset-bottom)",
        }}>
            {tabs.map(tab => (
                <button
                    key={tab.id}
                    onClick={() => onChange(tab.id)}
                    style={{
                        flex: 1, padding: "10px 0 8px",
                        background: "none", border: "none", cursor: "pointer",
                        display: "flex", flexDirection: "column", alignItems: "center", gap: 3,
                        color: active === tab.id ? "#10B981" : "rgba(255,255,255,0.35)",
                        fontSize: 10,
                        fontWeight: active === tab.id ? 700 : 400,
                        transition: "color 0.15s",
                    }}
                >
                    <span style={{ fontSize: 20 }}>{tab.icon}</span>
                    <span>{tab.label}</span>
                </button>
            ))}
        </nav>
    );
}

function TrainingsHomeScreen({ user }) {
    return (
        <div style={{ padding: "24px 16px" }}>
            <p style={{ color: "white", fontWeight: 700, fontSize: 20, marginBottom: 8 }}>
                Welcome, {user?.name?.split(" ")[0]}
            </p>
            <p style={{ color: "rgba(255,255,255,0.5)", fontSize: 14 }}>
                Browse and manage training events.
            </p>
        </div>
    );
}

export function TrainingsScope({ user, header, onLogout, onUserUpdate }) {
    const [tab, setTab]     = useState("home");
    const [modal, setModal] = useState(null);

    // ── Modal render ────────────────────────────────────────────────────────
    if (modal?.type === "trainingDetail") {
        return (
            <TrainingDetailScreen
                training={modal.data}
                onBack={() => setModal(null)}
                user={user}
            />
        );
    }

    // ── Tab render ──────────────────────────────────────────────────────────
    return (
        <div style={{ paddingBottom: 64, minHeight: "100vh", background: "#0f172a" }}>
            {header}
            {tab === "home" && <TrainingsHomeScreen user={user} />}
            {tab === "trainings" && (
                <TrainingsListScreen
                    user={user}
                    onViewTraining={(t) => setModal({ type: "trainingDetail", data: t })}
                />
            )}
            {tab === "profile" && (
                <ProfileScreen user={user} onLogout={onLogout} onUserUpdate={onUserUpdate} />
            )}
            <BottomNav tabs={TABS} active={tab} onChange={setTab} />
        </div>
    );
}
```

- [ ] **Step 2: Verify trainings scope**

Login as a role with Trainings access (e.g. admin). Select Trainings from the hub. Verify:
- Tabs: Home · Trainings · Profile
- Trainings list loads
- Tapping a training opens the detail screen
- Back navigation returns to list

- [ ] **Step 3: Final end-to-end verification**

Run through all these scenarios:

| Scenario | Expected behaviour |
|----------|-------------------|
| Login as `super_admin` | Hub shows 3 scope cards with gradients |
| Login as `mentee` | Directly in Mentorships, mentee tabs, no ⊞ |
| Hub → Assessments → ⊞ | Returns to hub |
| Hub → Mentorships → ⊞ | Returns to hub |
| Edit scope in Filament (toggle off) | Scope disappears on next login |
| Add new role to scope in Filament | Role gains access on next login |
| Offline login (cached scope config) | Hub loads from IndexedDB, no network call needed |

- [ ] **Step 4: Build for production**

```bash
cd public/m-assessment-app
npm run build
```

Expected: No errors. `dist/` updated.

- [ ] **Step 5: Final commit**

```bash
git add public/m-assessment-app/src/scopes/TrainingsScope.jsx \
        public/m-assessment-app/dist/
git commit -m "feat(mobile): wire TrainingsScope — scope redesign complete"
```

---

## Summary

| Phase | Tasks | Deliverable |
|-------|-------|-------------|
| Backend | 1–5 | `scopes` + `scope_role_access` tables, Filament `ScopeResource`, `GET /api/v1/scope-config`, scopes in `/auth/me` |
| Mobile | 6–13 | `scope-config.js`, `App.jsx` refactor, `ScopeShell`, `ScopeHubScreen`, `AssessmentsScope`, `MentorshipsScope`, `TrainingsScope` |

**Adding a new scope in future:**
1. Add a DB record via Filament (App Configuration → Scopes)
2. Create `src/scopes/NewScope.jsx` following the pattern in this plan
3. Add one line to `ScopeShell.jsx`: `{activeScope.id === "new" && <NewScope {...scopeProps} />}`

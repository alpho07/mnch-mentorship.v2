# Phase 5b Empty-State Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring 12 bare/incomplete empty states up to the guide's §14 standard (heading + explanation + action where one exists) across Filament resources, mentorship sub-pages, and coordinator analytics views.

**Architecture:** Pure presentation-layer changes — Filament `emptyState*` table builder methods and Blade markup. No new business logic, no new routes, no new PHP classes.

**Tech Stack:** Laravel 12, Filament v3.

## Global Constraints

- Every action added must route to an already-existing page/action — do not create new pages or routes.
- `app/Filament/Resources/MentorshipResource/Pages/ManageClassModules.php` is explicitly **out of scope** — its "Add Modules" action already renders in the page header at all times (verified during brainstorming), so its empty state is already effectively actionable. Do not touch this file.
- No new PHPUnit tests — this is a static presentation change with no conditional logic to break, matching the project's own convention of testing behavior, not label text. Verification is a single manual browser pass at the end (Task 5).

---

### Task 1: Bare Filament resources — add heading + description + action

**Files:**
- Modify: `app/Filament/Resources/ResourceResource.php`
- Modify: `app/Filament/Resources/CategoryResource.php`
- Modify: `app/Filament/Resources/ResourceTypeResource.php`
- Modify: `app/Filament/Resources/AccessGroupResource.php`

All four already `use Filament\Tables;` — no new imports needed.

- [ ] **Step 1: `ResourceResource.php`**

Find the end of `table()` (currently ends):

```php
                        ->defaultSort('created_at', 'desc');
    }
```

Replace with:

```php
                        ->defaultSort('created_at', 'desc')
                        ->emptyStateHeading('No Resources Yet')
                        ->emptyStateDescription('Add articles, guides, or files to the knowledge base.')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make(),
                        ]);
    }
```

- [ ] **Step 2: `CategoryResource.php`**

Find the end of `table()` (currently ends):

```php
            ->defaultSort('sort_order');
    }
```

Replace with:

```php
            ->defaultSort('sort_order')
            ->emptyStateHeading('No Categories Yet')
            ->emptyStateDescription('Categories help organize knowledge base resources.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }
```

- [ ] **Step 3: `ResourceTypeResource.php`**

Find the end of `table()` (currently ends):

```php
                        ->defaultSort('sort_order');
    }
```

Replace with:

```php
                        ->defaultSort('sort_order')
                        ->emptyStateHeading('No Resource Types Yet')
                        ->emptyStateDescription('Resource types classify knowledge base content (e.g. guide, video, form).')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make(),
                        ]);
    }
```

- [ ] **Step 4: `AccessGroupResource.php`**

Find the end of `table()` (currently ends):

```php
                        ->defaultSort('name');
    }
```

Replace with:

```php
                        ->defaultSort('name')
                        ->emptyStateHeading('No Access Groups Yet')
                        ->emptyStateDescription('Access groups control which restricted resources a facility or role can see.')
                        ->emptyStateActions([
                            Tables\Actions\CreateAction::make(),
                        ]);
    }
```

**Note:** each of these four `->defaultSort(...)` lines is unique enough within its own file to be an unambiguous match — verify the surrounding context (a few lines above) still matches what's shown above before applying, since these files may have shifted slightly; if the exact `defaultSort` call differs, locate the true end of that resource's `table()` method instead of guessing.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/ResourceResource.php app/Filament/Resources/CategoryResource.php app/Filament/Resources/ResourceTypeResource.php app/Filament/Resources/AccessGroupResource.php
git commit -m "feat: add empty-state heading, description, and action to 4 bare Filament resources"
```

---

### Task 2: Resources with action but no heading/description

**Files:**
- Modify: `app/Filament/Resources/ActivityResource.php`
- Modify: `app/Filament/Resources/ProgramModuleResource.php`
- Modify: `app/Filament/Resources/ProgramModuleQuizResource.php`

- [ ] **Step 1: `ActivityResource.php:83-85`**

Find:

```php
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
```

Replace with:

```php
            ->emptyStateHeading('No Activities Yet')
            ->emptyStateDescription('Activities (CME, Hands-on Demo, Drill) are assigned to mentorship modules.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
```

- [ ] **Step 2: `ProgramModuleResource.php:222-224`**

Find:

```php
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
```

Replace with:

```php
            ->emptyStateHeading('No Modules Yet')
            ->emptyStateDescription("Modules make up a program's curriculum.")
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
```

- [ ] **Step 3: `ProgramModuleQuizResource.php:177-179`**

Find:

```php
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
```

Replace with:

```php
            ->emptyStateHeading('No Quizzes Yet')
            ->emptyStateDescription('Quizzes are attached to a module as its pre-test or post-test.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
```

**Note:** all three files share the identical 3-line `->emptyStateActions([...])` snippet — when editing each file, confirm you're modifying the one instance in that specific file (each file has only one `table()` method, so there's no ambiguity within a single file, but do not attempt a cross-file find-and-replace).

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/ActivityResource.php app/Filament/Resources/ProgramModuleResource.php app/Filament/Resources/ProgramModuleQuizResource.php
git commit -m "feat: add empty-state heading and description to Activity/ProgramModule/Quiz resources"
```

---

### Task 3: Mentorship sub-pages — add missing action

**Files:**
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ManageModuleMentees.php`
- Modify: `app/Filament/Resources/MentorshipResource/Pages/ManageMentorshipCoMentors.php`

Both files already `use App\Filament\Resources\MentorshipTrainingResource;` — no new imports needed.

- [ ] **Step 1: `ManageModuleMentees.php:601-602`**

Find:

```php
            ->emptyStateHeading('No Mentees Enrolled')
            ->emptyStateDescription('Enroll mentees in the class first.');
    }
```

Replace with:

```php
            ->emptyStateHeading('No Mentees Enrolled')
            ->emptyStateDescription('Enroll mentees in the class first.')
            ->emptyStateActions([
                Tables\Actions\Action::make('go_enroll_mentees')
                    ->label('Enroll Mentees')
                    ->icon('heroicon-o-user-plus')
                    ->url(fn () => MentorshipTrainingResource::getUrl('class-mentees', [
                        'training' => $this->training->id,
                        'class' => $this->class->id,
                    ])),
            ]);
    }
```

- [ ] **Step 2: `ManageMentorshipCoMentors.php:194-196`**

Find:

```php
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Classes Yet')
            ->emptyStateDescription('Create a class cohort to assign co-mentors.');
    }
```

Replace with:

```php
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Classes Yet')
            ->emptyStateDescription('Create a class cohort to assign co-mentors.')
            ->emptyStateActions([
                Tables\Actions\Action::make('go_create_class')
                    ->label('Create Class')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn () => MentorshipTrainingResource::getUrl('classes', ['record' => $this->record->id])),
            ]);
    }
```

**Note:** confirm both files already `use Filament\Tables;` (for `Tables\Actions\Action`) before applying — if a file only imports `Filament\Tables\Table` without the parent `Filament\Tables` namespace alias, add `use Filament\Tables;` alongside it rather than fully qualifying inline.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/MentorshipResource/Pages/ManageModuleMentees.php app/Filament/Resources/MentorshipResource/Pages/ManageMentorshipCoMentors.php
git commit -m "feat: add Enroll Mentees / Create Class actions to two empty states"
```

---

### Task 4: Coordinator analytics views

**Files:**
- Modify: `resources/views/analytics/dashboard/mentor-mode.blade.php`
- Modify: `resources/views/analytics/dashboard/index.blade.php`
- Modify: `resources/views/analytics/dashboard/emonc-mode.blade.php`

- [ ] **Step 1: `mentor-mode.blade.php:334-338`**

Find:

```blade
            @if(count($mentorMatrix) === 0)
            <div style="padding:3rem;text-align:center;color:var(--gray-500);">
                <i class="fas fa-user-tie fa-2x mb-3" style="color:var(--gray-200);"></i>
                <p style="font-size:.9rem;">No mentors found. Try adjusting the filters above.</p>
            </div>
            @else
```

Replace with:

```blade
            @if(count($mentorMatrix) === 0)
            <div style="padding:3rem;text-align:center;color:var(--gray-500);">
                <i class="fas fa-user-tie fa-2x mb-3" style="color:var(--gray-200);"></i>
                <p style="font-size:.95rem;font-weight:700;color:var(--gray-700);margin-bottom:.35rem;">No Mentors Found</p>
                <p style="font-size:.85rem;margin-bottom:1rem;">No mentors match the current filters.</p>
                <a href="?mode=mentor" class="mf-clear" style="display:inline-flex;">
                    <i class="fas fa-times me-1" style="font-size:.72rem;"></i>Clear filters
                </a>
            </div>
            @else
```

- [ ] **Step 2: `index.blade.php:631-636`**

Find:

```blade
                    @empty
                    <div class="sidebar-empty">
                        <i class="fas fa-chart-bar fa-2x mb-3"></i>
                        <p>No training programs found for the selected period.</p>
                    </div>
                    @endforelse
```

Replace with:

```blade
                    @empty
                    <div class="sidebar-empty">
                        <i class="fas fa-chart-bar fa-2x mb-3"></i>
                        <p style="font-weight:700;margin-bottom:.3rem;">No Training Programs Found</p>
                        <p style="font-size:.85rem;margin-bottom:.75rem;">No programs match the selected period.</p>
                        <a href="{{ request()->fullUrlWithQuery(['year' => null]) }}" style="font-size:.82rem;font-weight:600;color:var(--gray-700);text-decoration:underline;">Reset Period</a>
                    </div>
                    @endforelse
```

- [ ] **Step 3: `emonc-mode.blade.php:340-344`**

Find:

```blade
            @if(count($emoncMatrix) === 0)
            <div style="padding:3rem;text-align:center;color:var(--gray-500);">
                <i class="fas fa-heartbeat fa-2x mb-3" style="color:var(--gray-200);"></i>
                <p style="font-size:.9rem;">No EmONC mentorship data found. Programmes must be tagged with <em>Maternal EmONC</em> in the program name.</p>
            </div>
            @else
```

Replace with:

```blade
            @if(count($emoncMatrix) === 0)
            <div style="padding:3rem;text-align:center;color:var(--gray-500);">
                <i class="fas fa-heartbeat fa-2x mb-3" style="color:var(--gray-200);"></i>
                <p style="font-size:.95rem;font-weight:700;color:var(--gray-700);margin-bottom:.35rem;">No EmONC Mentorship Data Found</p>
                <p style="font-size:.85rem;">Programmes must be tagged with <em>Maternal EmONC</em> in the program name.</p>
            </div>
            @else
```

(No action added here by design — the fix (program-name tagging) happens elsewhere, not on this page.)

- [ ] **Step 4: Commit**

```bash
git add resources/views/analytics/dashboard/mentor-mode.blade.php resources/views/analytics/dashboard/index.blade.php resources/views/analytics/dashboard/emonc-mode.blade.php
git commit -m "feat: improve empty states on coordinator analytics views"
```

---

### Task 5: Manual verification pass

No automated tests for this plan (see Global Constraints) — this task is the actual verification.

- [ ] **Step 1: Verify the 4 bare Filament resources**

Log in as a user with admin/knowledge-base access. For each of Resources (Knowledge Base), Categories, Resource Types, and Access Groups: if any already has data, temporarily filter/search for something that yields zero results (or use a fresh scope) to see the empty state; confirm heading, description, and a working "Create" button all render.

- [ ] **Step 2: Verify Activity / ProgramModule / ProgramModuleQuiz resources**

Same approach — confirm heading + description now show alongside the existing Create action.

- [ ] **Step 3: Verify `ManageModuleMentees` and `ManageMentorshipCoMentors`**

Navigate to a class module with zero enrolled mentees and confirm the "Enroll Mentees" button appears and correctly navigates to the class's mentee-enrollment page. Navigate to a mentorship's co-mentors page with zero classes and confirm "Create Class" appears and correctly navigates to `ManageMentorshipClasses`.

- [ ] **Step 4: Verify the 3 coordinator analytics empty states**

At `/analytics/dashboard?mode=mentor`, apply a filter combination that yields zero mentors and confirm the new heading + "Clear filters" link render and the link actually clears the filters. At the default (`mode=mentorship` or similar) view, select a period with no training programs and confirm "Reset Period" appears and works. At `?mode=emonc`, if there's no EmONC data in view, confirm the heading renders (no action expected).

- [ ] **Step 5: Confirm `ManageClassModules` is untouched**

Quick sanity check — open a class with zero modules and confirm the empty state and header "Add Modules" button look exactly as they did before this plan (no regression, no accidental edit).

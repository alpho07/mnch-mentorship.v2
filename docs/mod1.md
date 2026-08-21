# mod1 — Mentorship Lifecycle Guard: Production Rollout

**Audience:** an AI assistant (or operator) with shell access to the
**production** server for this Laravel app, after the code for this change
has already been deployed there (git pull / file sync already done — this
document does not cover code deployment, only what happens after).

**What this change is:** a new invariant — a facility mentorship can't be
`active`/`completed` unless it has a genuinely started class (not `draft`)
with at least one enrolled mentee — plus a one-time cleanup of existing data
that violates it, plus a new stalled-mentorship reminder system.

**Read this whole file before running anything.** Several steps are
irreversible or send real email to real users. Each step says whether it's
read-only, and every irreversible step has a STOP gate before it.

---

## 0. Preconditions

- [ ] Confirm you are on the **production** server, not a dev/staging copy.
      Check: `php artisan tinker --execute="echo config('app.env');"` should
      print `production`, and `DB_DATABASE` in `.env` should be the real
      production database name.
- [ ] Confirm the deployed code already includes these files (git log or
      `ls` them):
  - `app/Models/Training.php` (has `canActivate()` and the `saving` guard)
  - `app/Console/Commands/AutoCloseMentorships.php` (has the `whereHas` guard)
  - `app/Models/MentorshipClass.php` (`start()` takes a `bool $notify = true` param)
  - `app/Services/MentorshipStallReminderService.php`
  - `app/Console/Commands/SendMentorshipStallReminders.php`
  - `app/Filament/Pages/StalledMentorships.php`
  - `database/migrations/2026_08_21_154503_revert_non_compliant_mentorships_to_draft.php`
  - `database/migrations/2026_08_21_160000_start_ready_stalled_mentorship_classes.php`
  - `database/migrations/2026_08_21_161109_create_mentorship_stall_reminders_table.php`
- [ ] If any file is missing, **stop** — the code deploy is incomplete. Do
      not proceed with migrations against incomplete code.

---

## 1. Back up the database (irreversible step ahead — do not skip)

Trigger a backup using the app's existing backup service before touching
anything:

```
php artisan db:backup:check
```

If your production setup normally takes backups a different way (managed
DB snapshot, etc.), take one now via that route instead. Confirm the backup
file/snapshot actually exists and has a non-trivial size before continuing.

**STOP if you cannot confirm a backup exists.** Do not proceed to section 2
without one.

---

## 2. Dry run — preview migration 1's impact (read-only, no data changes)

Run in `php artisan tinker`:

```php
use Illuminate\Support\Facades\DB;

$affected = DB::table('trainings')
    ->where('type', 'facility_mentorship')
    ->where('is_pilot', false)
    ->whereIn('status', ['active', 'completed'])
    ->whereNull('deleted_at')
    ->whereNotIn('id', function ($query) {
        $query->select('mentorship_classes.training_id')
            ->from('mentorship_classes')
            ->join('class_participants', 'class_participants.mentorship_class_id', '=', 'mentorship_classes.id')
            ->whereIn('mentorship_classes.status', ['active', 'completed'])
            ->whereNull('mentorship_classes.deleted_at')
            ->whereIn('class_participants.status', ['enrolled', 'active', 'completed']);
    })
    ->get(['id', 'title', 'status']);

echo 'Would revert to draft: '.$affected->count().PHP_EOL;
echo 'By current status: '.$affected->countBy('status').PHP_EOL;
foreach ($affected as $t) {
    echo "  #{$t->id} [{$t->status}] {$t->title}".PHP_EOL;
}
```

Record the count and skim the titles.

**STOP and escalate to a human if:**
- The count is implausibly large (e.g. most of your active mentorships) —
  that suggests production's data shape differs from what this migration
  assumes (different status values in use, a relationship that isn't
  populated the way it is in dev, etc.). Do not run the real migration
  until a human has reviewed this list.
- Any title in the list is one you have independent reason to believe is a
  genuinely running mentorship. Same action: stop, escalate, do not migrate.

If the list looks sane (mentorships that plausibly never started), continue.

---

## 3. Dry run — preview migration 2's impact (read-only, no data changes)

```php
use App\Models\MentorshipClass;

$candidates = MentorshipClass::where('status', 'draft')
    ->whereHas('training', function ($query) {
        $query->where('type', 'facility_mentorship')
            ->where('is_pilot', false)
            ->where('status', 'draft');
    })
    ->whereHas('participants', fn ($query) => $query->whereIn('status', ['enrolled', 'active', 'completed']))
    ->whereHas('classModules')
    ->get(['id', 'training_id', 'name']);

$ready = $candidates->filter(fn ($c) => $c->canStart());

echo 'Candidate classes: '.$candidates->count().PHP_EOL;
echo 'Actually startable: '.$ready->count().PHP_EOL;
foreach ($ready as $c) {
    echo "  class #{$c->id} (training #{$c->training_id}) — {$c->name}".PHP_EOL;
}
```

This is the set of classes that migration 2 will silently start (**no email
is sent** — `notify: false` is hardcoded into that migration). Record the
count. No stop condition here beyond general sanity — this list is strictly
a subset of section 2's list, and starting a class that already has a
mentee and modules assigned is low-risk by construction.

---

## 4. Run the migrations

```
php artisan migrate --force
```

This runs, in order:
1. `2026_08_21_154503_revert_non_compliant_mentorships_to_draft` — the
   change you previewed in section 2.
2. `2026_08_21_160000_start_ready_stalled_mentorship_classes` — the change
   you previewed in section 3. Discovers its target rows dynamically (not
   hardcoded IDs), so it is safe to run in any environment. Sends no email.
3. `2026_08_21_161109_create_mentorship_stall_reminders_table` — schema
   only, no data risk.

After it finishes, spot-check:

```
php artisan migrate:status
```

Confirm all three show `Ran`.

---

## 5. Grant the new admin page's permission

The "Stalled Mentorships" admin page requires a Shield permission that
doesn't exist until generated. Running the full `php artisan
shield:generate --all` is safe but can be very slow on a large app; the
targeted alternative:

```
php artisan tinker --execute="
\$p = Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'page_StalledMentorships', 'guard_name' => 'web']);
Spatie\Permission\Models\Role::where('name','super_admin')->first()?->givePermissionTo(\$p);
"
php artisan permission:cache-reset
```

Grant to additional roles as needed for your team:

```
php artisan tinker --execute="
Spatie\Permission\Models\Role::where('name','<role_name>')->first()?->givePermissionTo('page_StalledMentorships');
"
```

---

## 6. Clear caches

```
php artisan config:clear
php artisan cache:clear
```

---

## 7. Scheduler + queue worker (Windows Task Scheduler)

**Do not skip this — without it, nothing in this feature actually sends
email.** Two separate, persistent tasks are required. Verify neither
already exists before creating (check Task Scheduler for existing entries
running `artisan schedule:run` or `artisan queue:work` first — don't
duplicate).

**Task A — Laravel scheduler:**
- Trigger: every 1 minute, repeat indefinitely, no expiration
- Action: `php artisan schedule:run`
- Start-in directory: the project root (this matters — the command fails
  silently or errors if run from the wrong working directory)
- This drives `mentorships:send-stall-reminders` (`dailyAt('06:00')`) along
  with the app's pre-existing scheduled commands (`mentorships:auto-close`,
  `rag:lexicon`, `db:backup:check`) — do not create a second competing
  scheduler task if one of those is already wired up some other way; check
  first.

**Task B — persistent queue worker:**
- Trigger: at system startup; configure automatic restart on failure
- Action: `php artisan queue:work --tries=3`
- This must run continuously (not `queue:run-once`), or queued mail (from
  this feature and every other feature already using `Mail::queue()`) sits
  in the `jobs` table indefinitely and never sends.

After both are running, confirm the reminder setting is on (it defaults to
enabled, but verify explicitly):

```
php artisan tinker --execute="echo App\Models\Setting::getBool(App\Models\Setting::STALL_REMINDER_ENABLED, true) ? 'enabled' : 'disabled';"
```

If it prints `disabled`, an admin previously turned it off — leave it as
they set it unless told otherwise. To change it, use the "Mentorship
Settings" admin page rather than tinker, so the toggle's notification and
any future admin viewing that page see accurate state.

---

## 8. Verification

- [ ] Visit `/admin/stalled-mentorships` as a user with the new permission
      — confirm it loads and lists mentorships (or shows the empty state).
- [ ] Confirm the login page's "mentorships" stat reflects the new,
      corrected count (it will likely be lower than before section 4 ran —
      that's expected and correct, not a bug).
- [ ] Do **not** manually run `php artisan mentorships:send-stall-reminders`
      as a smoke test unless you intend real mentors to receive real email
      — the command has no dry-run mode. Wait for Task A's next scheduled
      06:00 run, or explicitly confirm with a human first if you want to
      trigger it early.
- [ ] `php artisan queue:failed` should be empty shortly after the queue
      worker starts processing anything — if jobs are failing, investigate
      before considering this rollout complete (likely an SMTP credential
      or connectivity issue).

---

## Rollback

- Migrations 1 and 2 (the data corrections) are **not reversible** by
  design — `down()` is a no-op for both, documented in each migration file.
  The section-1 backup is the actual rollback path if something here turns
  out to be wrong.
- Migration 3 (the new table) reverses cleanly: `php artisan migrate:rollback`
  targeting that migration drops `mentorship_stall_reminders` with no
  side effects on other data.
- To fully disable the new reminder behavior without rolling back schema,
  turn off `STALL_REMINDER_ENABLED` via the Mentorship Settings admin page,
  or remove Task Scheduler entry A/B.

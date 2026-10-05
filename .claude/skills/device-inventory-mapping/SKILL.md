---
name: device-inventory-mapping
description: Use when mapping a device/equipment inventory (spreadsheet, pasted table, or workbook) to Kenyan health facilities — resolving facility names to MFL codes and facilities.id, unpivoting to one-device-per-row, and exporting a validated CSV. Triggers on facility inventory, MFL code, KMHFR, asset tag, device dispatch, pulse oximeter, manikin.
---

# Device inventory → facility mapping

Turns a device inventory into a validated long-format CSV where every row names a real facility by MFL code and local `facilities.id`.

## The one rule that matters

**Join on `mfl_code`. Never on `name`.**

`facilities.name` in this database is unreliable (see `references/known-issues.md`). MFL codes are stable across renames, upgrades and the corruption. Name matching is a way to *find* a candidate code; it is never the thing you trust.

## Phase 1 — Extract

Get the source into `{facility, device, quantity, identifier}` tuples.

For `.xlsx`, unzip and parse the XML directly (`scripts/xlsx_extract.php`) — no PhpSpreadsheet in this project. Gotchas that have bitten before, all handled in the script:

- A header row may contain a **second** `Facility` column belonging to a side table. Take the **first** occurrence of each header name, ignore columns right of `Tag`.
- Stray `DeviceSeq`/`Tag` labels appear in data rows. Require `facility` **and** `deviceseq` **and** `tag` on the header row. Do **not** also require `county` — some sheets omit it.
- Cross-check each sheet's extracted count against its own declared total and report any gap; a shortfall usually means an allocated device with no dispatch row.
- Watch for stale duplicate sheets (`Homabay Summary` vs `Homa Bay Summary`). Prefer the one the workbook's own change log says was kept.

## Phase 2 — Resolve to MFL codes

Three tiers, in order. Stop at the first that gives a confident answer.

1. **Local DB name match**, county-constrained, normalised (case, punctuation, `SUB COUNTY`→`SUBCOUNTY`, `REFFERAL`/`REFERAL`→`REFERRAL`).
2. **KMHFR master list** (`references/known-issues.md` has the source). Match the workbook name against master names within the same county. This resolves renames and upgrades: a 2020 "X Health Centre" and a 2026 "X Sub County Hospital" are the same code.
3. **Web search** for post-2020 facilities absent from the master: `"<name>" MFL code <county>`. doctor254.com and hosi.co.ke surface MOH codes.

Then **verify tier 1 against tier 2**. Roughly 1 in 30 name matches is wrong in a way only the master reveals — a Level-2 clinic standing in for a county referral hospital, or a code belonging to a facility in another county. Review every disagreement by hand; some will be the DB being right and the master's fuzzy pick being wrong.

## Phase 3 — Close the gaps

Every code must exist as a live `facilities` row. If it does not, insert it from the master (name, mfl_code, subcounty_id resolved by `county|subcounty`, ward). See `scripts/resolve_facilities.php`.

## Phase 4 — Export and validate

Six columns, always: `SEQ, FACILITY_NAME, MFL_CODE, FACILITY_ID, DEVICE, QUANTITY`. One physical device per row; `SEQ` carries its asset tag or serial, blank when unknown.

Run `scripts/audit_export.php`. It must pass all of:

- no blank `MFL_CODE`, no blank `FACILITY_ID`
- every `FACILITY_ID` resolves to a live (`deleted_at IS NULL`) row
- `FACILITY_ID.mfl_code == MFL_CODE` on every row
- no MFL code shared by two source facilities
- quantities sum to the pre-transformation total, checked **per facility × device**, not just on the grand total — offsetting errors cancel out in a grand total

## What never gets invented

Facility identity is researchable; chase it. **Asset tags and serial numbers are not** — they are labels on physical equipment. Leave them blank when unknown and report them. A plausible invented tag becomes wrong inventory that nobody catches.

Mark any code that rests on reasoning rather than a registry lookup as `INFERRED` in the exceptions log, so a single wrong inference stays traceable to its rows.

## Before writing to the database

Back up first — CSV of the affected columns plus a rollback `.sql` of per-row `UPDATE`s (`scripts/backup_facilities.php`). Dry-run and classify the change set before applying. Never bulk-change `mfl_code` or `subcounty_id`: codes need human adjudication, and `subcounty_id` drives `scopedCountyIds()`/`scopedFacilityIds()`, so moving it changes who can see what.

Before deleting or merging a facility, count dependents in `assessments`, `facility_user`, `training_data_master`, `trainings`, `trainings_v2`, `users`. None have FK constraints on `facility_id`, so an orphaning delete succeeds silently. When merging duplicates, keep the row **with** dependents and rename it; retire the empty one.

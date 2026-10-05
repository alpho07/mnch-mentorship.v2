# Known data pathologies — mnch-master facilities

## The `go` corruption (repaired 2026-09-17, but read this)

`facilities.name` had **every literal substring `go` deleted** by an unbounded find-and-replace. 393 rows were restored by joining on `mfl_code` to the master list. Names containing `go` went 2 → 395.

Examples of what it looked like: `Bungoma`→`Bunma`, `Migori`→`Miri`, `Kerugoya`→`Keruya`, `Ngong`→`Nng`, `Chogoria`→`Choria`, `Baragoi`→`Barai`, `Good Health…`→`od Health…`.

If a name still looks truncated, test `str_ireplace('go','',$masterName) === $dbName`. Rows whose `mfl_code` is absent from the master could not be repaired this way.

**Still open** (reports in `storage/app/exports/`):
| File | Rows | What |
|---|---|---|
| `facilities_name_conflicts.csv` | 1,755 | DB name genuinely differs from master for the same code |
| `facilities_subcounty_mismatches.csv` | 375 | `subcounty_id` disagrees with the master — RBAC impact, do not bulk-apply |
| `facilities_code_not_in_master.csv` | 1,101 | codes absent from the 2020 snapshot, mostly newer facilities |

Two codes are held by two live rows each: `17684`, `23198` — same-name duplicates, safe to merge.

## Codes that are wrong in the DB, not just unmatched

`mfl_code` values are not automatically trustworthy either. Confirmed examples:

- `15200` sits on a Mogotio row, but `15200` is **Soin Sub County Hospital, Nakuru**; `20005` (the real Mogotio, Baringo) sits on a different row. The two appear swapped, with `subcounty_id` pointing at the wrong county too. Unresolved — needs human adjudication.
- Facility `68469` had `mfl_code` `157791`, a 6-digit non-MFL value typed to dodge a uniqueness clash. Restored to `15779`.

`mfl_code` is `varchar(10)` with **no unique constraint**, so collisions and malformed values insert silently. Adding a unique index is worth doing once the duplicates are resolved.

## Facility identity resolution — the master list

KMHFR is unreachable from this environment (`kmhfr.health.go.ke` and `api.kmhfr.health.go.ke` → `41.89.93.173`, ECONNREFUSED; Bash has no outbound network — only WebFetch/WebSearch do).

Use the **Kenya Master Health Facility List 2020** on openAFRICA: 12,394 rows with `Code, Name, Keph level, Facility type, Owner, County, Constituency, Sub county, Ward`.
https://open.africa/dataset/kenya-master-health-facility-list-2020
The download 302-redirects to S3; WebFetch returns the redirect target, and fetching that saves the 2.9MB file locally.

## Name variants that defeat naive matching

Real fixes from this project — the pattern is spelling drift, renames on upgrade, and sub-county names standing in for town names.

| Source name | Actual facility | MFL |
|---|---|---|
| Fort Tenana | Forttenan Sub District Hospital | 14501 |
| Gaki | Giaki Sub-District Hospital | 12036 |
| Mitamboni | Mitaboni Health Centre | 12530 |
| Ndiikini | Ndithini Level 4 Hospital | 12637 |
| Laisamiss | Laisamis Sub County Referral | 18856 |
| Nyamaranga | Nyamaraga Sub County Hospital | 13897 |
| Rwamba | Rwambwa Sub-county Hospital | 14063 |
| Sigomore | Sigomere Subcounty Hospital | 14085 |
| Lusigetti | Lussigetti (double-s in DB) | 10666 |
| Langalanga | Langa Langa Hospital (spaced) | 15009 |
| Rhamu | **Mandera North** Sub County Hospital | 13423 |
| Takaba | **Mandera West** Sub County Hospital | 13445 |
| Kitale County Referral | renamed Wamalwa Kijana T&R Hospital | 27376 |
| Mama Rachel Ruto Maternity | formerly West Health Centre | 15779 |
| Keumbu Sub County | master lists it as `KSDH` | 13680 |
| Rachuonyo East | Kabondo SCH (= Kabondo Kasipul) | 13638 |
| Rachuonyo North | Kandiege SCH (= Karachuonyo) | 13653 |
| Suba North | Mbita SCH, Kasgunga ward | 13798 |

County referral hospitals frequently still carry their pre-devolution names: `Nakuru PGH` 15288, `Kakamega PGH` 15915, `Meru District Hospital` 12516, `Nyeri PGH` 10903, `Maralal District Hospital` 15126.

## Source-data defects that are not yours to fix

Contradictions in the supplied sheets. Report them; do not resolve them by inventing values.

- An asset serial appearing on two facilities (e.g. air-device serial `175` on both Pumwani and Kapenguria).
- Serials listed for a facility whose stock reads zero (Moi TRH: serials 310/137, 0 air devices).
- Asset tags duplicated within a county (Nairobi `MOH4701–4703` on both Mbagathi and Jumuia Huruma), and `TBC`/blank tags.
- Devices allocated in a summary table with no corresponding dispatch row.
- Facilities named for sub-counties that do not exist (`Suba West` — Homa Bay has only Suba North and Suba South).

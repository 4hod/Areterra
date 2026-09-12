# Stage 2 — the linking layer

Points 10, 11, 13 and the first working example of point 3.
**Additive only. Nothing existing is altered or dropped.**

## Migration — `2026_09_07_000001_create_linking_layer.php`

- `documents` gains nullable `attachable_type/id` + a `consent` json column.
  Every existing row keeps working with a null owner and behaves exactly as
  it does today. Nothing is backfilled.
- New `tasks` table with polymorphic `taskable`. **`maintenance_tasks` is
  untouched and still runs** — moving it across is a separate decision, not
  something to do blind.
- `down()` drops only what `up()` added.

## Code

- `Task` model with `open()` / `overdue()` scopes.
- `Concerns\HasTasks` and `Concerns\HasDocuments` — wired into `Member`,
  `Animal`, `Grant`, `ComplianceItem`, `MemberInvoice`, `Incident`.
- `Document::attachable()` relation added.
- `Support\GlobalSearch` — one search across members, animals, grants,
  invoices, documents, tasks, incidents and compliance. Every source declares
  the capability required to read it, so a volunteer's search never surfaces
  safeguarding or finance hits.
- `SearchController` at `GET /search?q=` behind `auth` + `can:access_hub`.
- `Listeners\CreateFollowUpTaskForHealthConcern` — raising a health concern
  creates the vet follow-up task automatically. This is point 3 working end to
  end, and the template for every other listener.

## Two things I had to decide without you

1. **`completes_on_event`** on tasks is a string key (`'vet_record_created'`).
   Recording the underlying action clears the task. The dispatcher that reads
   this key is stage 3 — the column is there so it doesn't need another
   migration later.
2. **Capability names.** I first wrote `view_finance`, `view_incidents` and
   `view_governance` in GlobalSearch. None exist — your matrix uses
   `manage_finance`, `view_all_incidents`, `view_all_compliance`. Corrected.
   Worth knowing, because the wrong name fails *closed* and silently: results
   just never appear, with no error.

## Still unverified

Same as stage 1. Everything parses under PHP 8.3; nothing has been run.
The migration in particular has never touched a database.

```bash
php artisan migrate --pretend      # read the SQL before running it
php artisan migrate
php artisan test
```

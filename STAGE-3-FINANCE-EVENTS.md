# Stage 3 — finance layer, events, statuses, tests

Additive migration only. `down()` removes exactly what `up()` adds.

## Points now covered

| # | Point | Where |
|---|---|---|
| 1 | One source of truth | `PayrollRates`, `Ledger` (costs posted from source, never retyped) |
| 2 | Contextual links | `RecordContext`, `Timeline` |
| 3 | Workflows not forms | `Listeners/CreateFollowUpTaskForHealthConcern` |
| 4 | Finance as a layer | `Support/Ledger` + 3 posting listeners |
| 5 | Payroll workflow | `Workflows/Payroll/*` |
| 6 | No silent errors | `Workflow`, `WorkflowException` |
| 7 | Real tests | `PayrollWorkflowTest`, `PeriodEngineTest` |
| 8 | Event system | `app/Events`, `app/Listeners` |
| 9 | Timeline & audit | `Support/Timeline` over existing `audit_log` |
| 10 | Documents on records | `documents.attachable`, `HasDocuments` |
| 11 | Tasks across everything | `tasks`, `HasTasks`, `CompleteTasksOnEvent` |
| 12 | Notifications from data | `Support/DueScanner` |
| 13 | Global search | `Support/GlobalSearch`, `GET /search` |
| 15 | Period engine | `Support/Period` |
| 16 | Statuses & approval | `Concerns/HasStatus`, `status_transitions` |
| 17 | Rollback by reversal | `Ledger::reverse()`, `scopeEffective()` |
| 18 | Whole-platform dashboard | `Support/Dashboard` |

## Still open — deliberately

- **14 (reports from live records)** and **20 (integration APIs)** need the
  ledger to have real data in it first. Building them now would be guesswork.
- **19 (record-level permissions)**. Your capability matrix is solid at role
  level, but per-record rules ("this volunteer, these animals") mean touching
  every controller. That's the biggest remaining job and the one most likely
  to lock someone out of something they need mid-shift.
- **`maintenance_tasks` → `tasks`.** Both exist. Merging them is a data
  migration on live rows and shouldn't be done blind.

## Things I decided without asking

1. `PayrollPeriod` statuses are `draft → finalised → paid`, matching the
   existing column values rather than your `Draft → Validated → Approved →
   Processed`. Renaming would break existing rows. The machine supports the
   four-stage version whenever you want to migrate the values.
2. `Ledger::post()` is idempotent per source+category, so re-approving a pay
   run updates rather than double-posts.
3. Reversals are mirror rows; `scopeEffective()` excludes both sides.

## Two real bugs found on the way

- `currentRate()` used `today()`, so reprocessing an old pay period applied
  today's rate to old hours. Fixed via `PayrollRates::asAt()`, with a test.
- Account holders were listed on the payroll index but never given entries —
  only roster members were prefilled.

## Before any of this runs

Nothing has been executed. No database, no `composer`, no `artisan`. Every
file parses under PHP 8.3 and the period arithmetic was verified standalone.
That is the whole of the verification.

```bash
composer install
php artisan migrate --pretend    # read the SQL first
php artisan migrate
php artisan test
```

`PayrollWorkflowTest` is the one to watch — if those six pass, the spine holds.

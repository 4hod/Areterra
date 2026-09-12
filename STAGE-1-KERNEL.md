# Stage 1 — the kernel

16 files, 919 insertions. This is the layer your 20 points depend on. It does
not add features; it gives cross-cutting behaviour somewhere to live.

## What landed

**`app/Support/Period.php`** — point 15. The 4-week finance period, indexed from
an anchor date. Period lookup by date, navigation, operating-day counts (including
per-weekday, for attendance-driven income), and frequency normalisation. The
`toFourWeekly()` that was private inside `FinanceController` now lives here, and
`FinanceController` delegates to it. Set `PERIOD_ANCHOR` in `.env` to the start of
a 4-week period you know is right — **it defaults to 2026-01-05, which is a guess.**

**`app/Workflows/Workflow.php`** — points 3, 6, 16, 17. Base class with one rule:
`problems()` inspects, `execute()` writes, `run()` wraps `execute()` in a
transaction and refuses to call it if `problems()` is non-empty. Controllers call
`run()` and never touch `DB::transaction` themselves.

**`app/Exceptions/WorkflowException.php`** — carries plain-English problems,
logs them server-side with context, and renders back to the user (422 for JSON).
No more generic 500s.

**`app/Support/PayrollRates.php`** — resolves a rate *as at a date*. `currentRate()`
used `today()`, so reprocessing an old period applied today's rate to old hours.

**`app/Workflows/Payroll/`** — your payroll bug, fixed at the root:
- `OpenPayrollPeriod` — creates period and lines in one transaction. Prefills
  account holders as well as roster members (they were silently omitted before).
  A missing rate is reported by name instead of becoming `?? 0`.
- `PayrollPreview` — read-only. Per-line working shown verbatim
  (`"37.5 hrs × £12.60 = £472.50 basic, + £0.00 holiday..."`), plus everything
  blocking approval: missing rates, £0 rate against a rate on record, duplicate
  names, unlinked lines, >200 hours in a 4-week period.
- `ApprovePayrollPeriod` — Draft → Approved gate. Recomputes every total from
  stored inputs rather than trusting the browser, then fires `PayrollApproved`.

**`app/Events/`** — point 8. `PayrollApproved`, `MemberAttended`, `InvoicePaid`,
`AnimalHealthConcernRaised`, `GrantExpenseRecorded`. Laravel 11+ auto-discovers
listeners in `app/Listeners` (empty for now — stage 2 fills it).

**`PayrollController`** — `storePeriod` uses the workflow; `saveEntries` is wrapped
in a transaction; `show` returns the preview; new `approve` action + route.

## Verify before trusting any of it

I have no database, no `composer`, and no way to boot Laravel in this session.
Every file parses cleanly under PHP 8.3 (`php -l`), and I verified the period
arithmetic separately — indexing, contiguity across ±20 periods, and frequency
round-tripping all pass. That is *not* the same as it working.

```bash
php artisan test --filter=Payroll
php artisan tinker
>>> App\Support\Period::current()->toArray();
>>> App\Support\Period::containing('2026-03-15')->label();
```

Then open a pay period against a staff member with no rate. You should get a
named warning, and approval should refuse — not a 500, and not £0.00.

## Not done yet

Points 1, 2, 10, 11, 13 need a schema change: `Document` has no polymorphic owner
(just `uploaded_by`), and there is no general `Task` — only `MaintenanceTask`. That
is stage 2, and it is a migration, so it needs discussing before it is written.

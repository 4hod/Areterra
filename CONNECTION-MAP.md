# Connection map — what talks to what

Derived from the schema, not from the prompt. 78 tables, every foreign key
and polymorphic link traced.

## What the analysis found

`member_id` appears in 19 tables and `user_id` in 23 — Member and User are the
real hubs. Everything else is thinner than it looks:

| Table | Referenced by | Verdict |
|---|---|---|
| `members` | 19 | Hub. Fine. |
| `users` | 23 | Hub. Fine. |
| `animals` | 3 | Under-connected |
| `grants` | 3 | Under-connected |
| `vehicles` | **0** | Complete island |
| `fixed_costs`, `additional_incomes` | 0 | Islands by design (manual entry) |

### The seven breaks

1. **`transport_runs` had no `vehicle_id`.** Transport didn't know which vehicle
   did the run, so vehicle costs could never reach Finance. This is why
   `vehicles` was an island.
2. **`incidents` had no subject.** Only free-text `persons_involved`. An incident
   could not point at the member, animal or vehicle it was about — so it could
   never surface anywhere else. **This is exactly the case you described.**
3. **`activities` had no participants.** No join to members at all. A session
   could not feed a member timeline, outcomes or impact reporting.
4. **`attendances` had no `activity_id`.** Attendance was member + date only, so
   it couldn't be attendance *for* anything.
5. **`member_invoices` had no link to what they cover.** Typed amount, no period,
   no attendance link. Attendance → expected income → invoice was fully manual.
6. **`staff_roster` and `users` had no link.** The same person could exist twice
   with payroll matching them by name. Two spellings, two payslips.
7. **`compliance_items` had no subject.** Vehicle MOT/service dates lived on
   `vehicles` as raw columns, duplicating compliance.

### Ownership conflicts

- `welfare_checks.concern` and `daily_monitoring.concern` both record the same
  fact with no owner. Pick one. Suggest `welfare_checks` owns "there is a
  concern"; monitoring records observations only.
- `vehicles.mot_due` / `service_due` vs `compliance_items.due_date` — same
  problem. Compliance should own deadlines; the vehicle is the subject.
- Expected income: attendance, `member_finance_profiles` and invoices all have
  a claim. Attendance owns "what happened", finance profile owns "the rate",
  invoicing derives. Nothing else should compute it.

### A genuine bug

`activities` declares `activity_date` **twice** in its migration.

## The flows, now wired

```
Incident logged ──▶ subject (member / animal / vehicle)
                    └─▶ follow-up task on that record, priority by severity

Transport run completed ──▶ vehicle mileage
                            └─▶ vehicle_costs in the ledger

Session completed ──▶ participants
                      └─▶ attendance rows ──▶ member timeline ──▶ impact reports

Payroll approved ──▶ staff_costs in the ledger
Grant expense ──▶ grant_spend, restricted, against the grant
Invoice paid ──▶ member_fees income
Health concern ──▶ vet follow-up task ──▶ auto-completes on vet record
```

## What each module emits / listens for

| Module | Emits | Listens for |
|---|---|---|
| Attendance | `MemberAttended` | `SessionCompleted` |
| Sessions | `SessionCompleted` | — |
| Transport | `TransportRunCompleted` | `MemberAttended` |
| Incidents | `IncidentLogged` | — |
| Animals | `AnimalHealthConcernRaised` | `IncidentLogged` |
| Grants | `GrantExpenseRecorded` | — |
| Payroll | `PayrollApproved` | — |
| Invoicing | `InvoicePaid` | `MemberAttended` |
| Finance | — | all four posting events |
| Tasks | — | every event, via `completes_on_event` |

## Deliberately standalone

You were right that not everything needs connecting. These stay alone:
Settings, Announcements, Policies, Forms builder, Directory, Timeclock,
Leave. They serve people, not records, and coupling them adds risk for nothing.

## Still needs you

1. **Backfilling the new columns.** Every join added is nullable and empty.
   Linking existing roster members to user accounts, and existing incidents to
   their subjects, is a judgement call on real rows I can't see.
2. **Invoice generation from attendance.** The columns exist now
   (`period_index`, `covers_start/end`, `generated`) but the generator needs
   your billing rules confirmed — 2-week vs 4-week per member, and whether
   advance invoicing bills expected or actual attendance.
3. **The duplicate `activity_date`.** Needs a look at live data before removing.

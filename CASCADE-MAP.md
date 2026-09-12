# Cascade map — what happens when something changes

The earlier audit checked that records *can* reference each other. This one
checks that a change in one module actually *causes* the right change in
another. They are different problems and the second is the one that matters
day to day.

Every row is a state change. ✅ = wired and tested. ⬜ = identified, not built.

## Register ↔ transport (your example)

| When | Should cause | |
|---|---|---|
| Member marked absent | Afternoon run cancelled | ✅ |
| Member marked absent | Transport charge reversed *unless collected* | ✅ |
| Member marked absent | Session participation set to not attended | ✅ |
| Driver marks "not picked up" | Member marked absent on register | ✅ |
| Driver marks "not picked up" | Afternoon run cancelled (cascades on) | ✅ |
| Collection undone | Day charge reversed | ✅ |
| Member marked present after absence | Afternoon run reinstated | ⬜ |
| Absent 3+ days running, no reason | Welfare check / contact task raised | ⬜ |

**Two live bugs this fixed.** `undo()` deleted the transport run but left the
£ charge standing — the member stayed billed for a journey the system no longer
believed happened. And absence wasn't representable at all: a member who didn't
come simply had no attendance row, so nothing could react to it.

## Member lifecycle

| When | Should cause | |
|---|---|---|
| Member leaves / set inactive | Future scheduled attendance cleared | ⬜ |
| Member leaves | Transport stopped, standing invoice closed | ⬜ |
| Member leaves | Open tasks about them closed or reassigned | ⬜ |
| Member's funding ends | Invoicing switches to private rate | ⬜ |
| Member's contact details change | Transport route re-geocoded | ⬜ |

## Sessions and activities

| When | Should cause | |
|---|---|---|
| Session completed | Attendance rows created for participants | ✅ |
| Session cancelled | Participants' attendance reverted | ⬜ |
| Session cancelled | Transport for that session cancelled | ⬜ |
| Animal assigned to session | Animal's session history updated | ⬜ |

## Animals

| When | Should cause | |
|---|---|---|
| Health concern raised | Vet follow-up task created | ✅ |
| Vet record added | That follow-up task auto-completes | ✅ |
| Animal deceased / rehomed | Welfare checks stop, open tasks closed | ⬜ |
| Animal deceased / rehomed | Removed from future session assignments | ⬜ |
| Medication course ends | Recurring medication task stops | ⬜ |

## Finance

| When | Should cause | |
|---|---|---|
| Payroll approved | Staff costs posted to ledger | ✅ |
| Grant expense recorded | Restricted spend posted against grant | ✅ |
| Invoice paid | Income posted to ledger | ✅ |
| Transport run completed | Vehicle mileage costed | ✅ |
| Payroll un-approved (finalised → draft) | Ledger entry reversed | ⬜ |
| Invoice voided or credited | Ledger entry reversed | ⬜ |
| Grant closed | Further expenditure blocked | ⬜ |
| Expenditure exceeds grant balance | Blocked, or flagged for approval | ⬜ |

## Staff

| When | Should cause | |
|---|---|---|
| Leave approved | Hours excluded from payroll for that period | ⬜ |
| Staff member leaves | Excluded from future pay runs | ⬜ |
| Staff member leaves | Open tasks reassigned | ⬜ |
| DBS expires | Blocked from lone working / flagged | ⬜ |

## Vehicles and compliance

| When | Should cause | |
|---|---|---|
| Incident logged | Follow-up task on member / animal / vehicle | ✅ |
| Vehicle marked off-road | Its transport runs need reassignment | ⬜ |
| MOT or insurance expires | Vehicle blocked from transport | ⬜ |
| Compliance item completed | Next occurrence scheduled | ⬜ |

## The pattern in the unbuilt rows

Nearly every ⬜ is the same shape: **something ends, is cancelled, or is
undone.** Everything built so far fires on creation. That was the real gap —
not missing links, but a system that only knows how to move forwards.

The other recurring shape is **a threshold being crossed** (grant overspent,
DBS expired, third absence in a row). Those need the `DueScanner` to run on a
schedule, which means a cron entry — worth checking your hosting supports it,
because if it doesn't, none of them will ever fire.

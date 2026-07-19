# Areterra Hub — Feature Specification

Compiled from the production WordPress-plugin implementation. This is the functional
source of truth for the standalone rebuild; see [`REBUILD_PLAN.md`](./REBUILD_PLAN.md)
for architecture and phasing.

## Overview

Areterra Hub is a bespoke operational platform for Areterra, a registered charity
(No. 1196211) providing day opportunity services for adults with learning disabilities
through animal care, horticulture and practical skills. It replaces Connecteam, paper
records and spreadsheets.

Current implementation: WordPress plugin (PHP 8.1+, MySQL 8+). Target rebuild:
standalone modern stack.

**Branding:** Primary blue `#009DE6`, Dark blue `#00345C`, Yellow `#FFD900`.
Font: Inter. Logo: https://areterra.co.uk/wp-content/uploads/2023/01/Areterra-logo-3-transparent_.png
Tagline: "Animals. People. Purpose."
Address: Little Croft, Fenn Green, WV15 6JA. Phone: 01562 307 306. Email: team@areterra.co.uk.

## Architecture (current)

- **Frontend:** Single-page application. One bundled JS file, CSS design system with
  CSS custom properties. Mobile-first, PWA-ready with service worker for push
  notifications. Bottom nav on mobile, sidebar on desktop.
- **Backend:** REST API under `/api/v1/`. All responses
  `{ success: bool, data: any, message?: string }`. Authentication via session/JWT.
  Role-based capability system.
- **Database:** MySQL/PostgreSQL. Soft deletes (`deleted_at`) on all major tables.
  Timestamps `created_at`, `updated_at` on everything.

## Roles & permissions

| Role | Key capabilities |
|---|---|
| administrator | Full access, manage settings, manage all users |
| manager | Approve leave, view all timeclock, manage members, manage animals, view reports, manage compliance |
| staff | Own timeclock, request leave, view members, log sessions, welfare checks |
| volunteer | Own timeclock, request leave, view members (limited) |
| safeguarding_lead | All staff caps + access safeguarding records |

Capabilities checked: `access_hub`, `view_members`, `edit_members`, `create_members`,
`delete_members`, `view_animals`, `edit_animals`, `approve_leave`, `view_all_leave`,
`view_all_timeclock`, `edit_timeclock`, `manage_incidents`, `view_all_incidents`,
`build_forms`, `view_reports`, `export_reports`, `manage_vehicles`, `view_vehicles`,
`upload_documents`, `manage_compliance`, `view_all_compliance`, `manage_directory`,
`manage_settings`, `view_audit_log`, `access_safeguarding`.

## Production data snapshot

- **12 members:** Amy Buckle, Andrew Toomey, Colin Gibbons, Coriander Montgomery,
  Elizabeth Russell, Gareth Warner (Gaz), Kim Scriven, Matthew England (Matt),
  Michelle Ballard, Michelle Walker, Nicholas Thomas (Nick), William Garrett (Billy).
- **23 animals:** 4 Macaws (Demon, Angel, Ricco, Pilot), 2 Chinchillas (Lily, Fidget),
  4 Degus (Daisy, Pansy, Cerys, Tulip), 5 Guinea Pigs, 5 Rabbits, 3 Chickens.
- **Staff roster:** Lucy Mills (£12.71/hr), Vanessa Goodall (£13.36/hr).
- **Vehicle:** YE66 EGY, Silver Ford Transit Custom.
- **Operating days:** Monday, Tuesday, Thursday, Friday only.

## Modules

### 1. Dashboard
- Greeting by time of day with user's name
- Today's quick stats: members in today, animals needing welfare checks, leave requests pending
- Quick action buttons: Clock In, Submit Leave, Log End of Day
- Welfare alerts widget (amber/red animals)
- Announcements feed (latest 3)
- Leave balance widget
- Push notification permission prompt (3s after login)

### 2. Today page (checklist)
Five-item checklist that auto-checks based on real data:
1. **Transport Register** — done when at least one transport run recorded today
2. **Morning Register** — done when `present_count > 0` (members checked in, not just scheduled)
3. **Arrival Moods** — done when mood entries exist for today's attendees
4. **Animal Welfare Checks** — done when all active animals have a welfare check logged today
5. **End of Day Records** — done when all members who attended have an end-of-day record

### 3. Members
- Photo upload, status (active/inactive/on-leave/archived)
- DOB, NHS number, support needs, diagnoses
- Contact details, address (with postcode autocomplete via postcodes.io)
- Emergency contacts
- 11 profile tabs: Sessions, Comms, Goals, Outcomes, Settings, Alerts, Circle of Care,
  Consents, Body Map, ABC Obs, GP Info
- Transport enable/disable toggle (per member setting)
- Responsive two-column layout collapses to single on mobile
- Member settings store `transport_required`, attendance days, key worker

### 4. Communications log (Comms tab)
- Types: email, phone, letter, meeting, text, other
- Direction: inbound/outbound/both
- Subject, summary, contact name, organisation, date
- Timeline display

### 5. Email composer
- Opens from member's Comms tab
- Contact picker pulls from member's Circle of Care (social workers, appointees, GPs)
- Merge tags: `{{member_name}}`, `{{today}}`
- Save emails as reusable templates
- Sends branded HTML email: "From: Areterra Team", Reply-To `team@areterra.co.uk`
- Auto-logs every sent email to member's Comms tab
- "Log Only" option for emails already sent via Outlook

### 6. Animals
- Species: Macaw, Chinchilla, Degu, Guinea Pig, Rabbit, Chicken
- Per animal: name, species, DOB, microchip, sex, breed, photo, status, welfare status (green/amber/red)
- Welfare checks grouped by species — one button per species triggers group modal
  (all healthy, or flag one)
- Individual welfare check modal: status, notes, concern flag
- Vet records with appointment history
- Daily monitoring per animal per day: weight (grams), body condition score (1–5),
  coat condition, appetite, droppings, behaviour, enrichment given + duration, notes,
  concern flag
- Concern flag triggers push notification to managers

### 7. Daily register / attendance
- Log member as checked in (`checked_in: true`) with time
- Arrival mood: emoji selection (😊😐😟😠😰)
- Notes per member
- Shows scheduled members for the day vs who's actually in

### 8. End of Day records (session handover)
Per member per day:
- Arrival mood (emoji) + end-of-day mood (emoji)
- Session type, activities participated in
- General notes
- Concern flag — if ticked, triggers push notification to all managers with detail
- View history by member

### 9. Transport planner
Step-by-step flow (not a flat list):
- **Phase 1: Morning Collection** — next person to collect shown prominently in a blue
  card with address, Navigate (Google Maps), Call, and "Mark as Collected ✓" button.
  Remaining queue below. Already collected collapse into a disclosure.
- **Phase 2: Afternoon Drop-off** — only revealed once ALL morning collections
  complete. Same layout in purple.
- **Phase 3: Complete.**
- Progress stepper at top shows current phase; undo on completed items; missing-address
  warning for members with no address.

**Transport fees:**
- £5/day per member, cash
- Running balance per member (paid − charged = balance)
- Credit system: pay £10 = 2 days credit, auto-deducts each transport day
- Payment modal with quick-select £5/£10/£15/£20 buttons; shows days in credit
- "Save & Receipt" prints branded receipt with balance
- Monthly summary: total charged vs collected vs outstanding
- Inline fee status shown on transport planner next-person card

### 10. Payroll calculator
- Staff roster — manual staff list independent of user accounts (for staff without
  system access). Stored as `1,000,000 + roster_id` in payroll to avoid ID clash with
  system users.
- Pay rates per staff member with effective date history
- Pay periods: label, start/end dates, separate **Pay Date** field (e.g. 28th is pay
  date, period is 22nd–21st), authorised-by name (pre-fills on PDF),
  status draft → finalised → paid
- Columns: Staff Name, N.I.C No., Hourly Rate (£), Total Hrs, Basic Pay,
  Holiday Pay (£), Total SSP, Mileage, Mileage Pay (£), Total (£)
- Live recalculation as you type; total banner updates live
- Delete period, Finalise button
- PDF export: landscape A4, "Pay Date - 28th July 2026" heading underlined, Areterra
  logo, correct columns, signature lines (authorised-by pre-filled if set)
- DB column upgrade routine handles existing tables

### 11. Supervisions & appraisals
- Types: supervision, appraisal, probation review, return to work, informal 1:1, disciplinary
- Date, duration, supervisor, discussion notes, actions agreed, development notes, next due date
- Staff signed-off checkbox; grouped by staff member showing history
- Overdue flag (>12 weeks) feeds System Audit

### 12. System Audit
Automatic health checks with traffic-light severity (Critical/Warning/Info) and direct
navigate links:
- Members with no review date or overdue review
- Animals with no vet record in 12 months
- Animals with welfare status amber/red
- Members with no session record in 30 days
- Staff with no supervision in 12 weeks
- Documents requiring read confirmation
- Pending referrals older than 5 days

### 13. Leave management
- Request leave: type (annual, sick, compassionate, unpaid, TOIL, other), dates, reason
- Manager approval workflow; entitlement tracking (28 days default); calendar view
- Push + email notification to manager on request; to staff on approval/decline

### 14. Time clock
- Clock in/out with optional GPS; break tracking; total hours per day
- Manager can edit/correct entries; report by staff member by period

### 15. Finance & grants
- Grant tracker: title, funder, amount, start/end dates, status, expenditure against grant
- In-kind donations: donor, type, category, estimated value, quantity, date, optional grant link
- Finance summary: income, costs, grant spend
- Member fees tracked separately — invoiced via QuickBooks

### 16. Invoice tracker (QuickBooks references)
Not a full invoicing system — QuickBooks raises/manages invoices. The Hub stores:
- QB invoice reference (e.g. INV-0042), member, amount, invoice/due/paid dates
- Status: draft/sent/paid/overdue/cancelled; optional direct QB URL
- Summary dashboard: outstanding total, overdue count, collected total
- Auto-flags overdue when due date passes; one-click "✓ Paid"

### 17. Policies
- Rich text editor (bold, italic, headings, lists)
- Five pre-built templates: Medication Administration, Safeguarding Adults, GDPR/Data
  Protection, Health & Safety, Confidentiality — pre-filled with Areterra details
- Version number, review date, status (draft/active/archived)
- Print branded A4 PDF with logo, version, date

### 18. Document library
- Upload and categorise documents; require read confirmation from staff
- Staff mark as read; unread count feeds System Audit

### 19. Risk assessments
- Create/edit; risk matrix (likelihood × severity); sign-off workflow; review date tracking

### 20. Member reviews
- Schedule and log review meetings; outcomes, actions, next review date
- Overdue reviews feed System Audit

### 21. Announcements
- Post to all staff; triggers push + email fallback on creation; staff mark as read

### 22. Push notifications
- VAPID key pair auto-generated on first load; service worker at site root
- Permission prompt 3s after login (dismissible, once per session)
- Subscription stored per user per device; email fallback when no push subscription
- Triggers:
  - New announcement → all staff
  - Welfare concern flagged → managers
  - Safeguarding concern in end of day → managers
  - Leave request submitted → managers
  - Register not done by 12:00 → managers (operating days only, only if members
    scheduled, deduped once per day)
  - End of day not done by 14:30 → managers (same conditions)
- Notification preferences per user (push on/off, email on/off, per-category toggles); send-test button

### 23. Microsoft SSO
- Azure OAuth2 (authorize → token → Microsoft Graph `/me`)
- Button always shows on login: active if configured, greyed with setup link if not
- Maps by email to existing user account
- Errors redirect to login with friendly message (no white error screens)
- Configured via Hub Settings: Client ID, Client Secret, Tenant ID

### 24. SAR / referral form
- Public-facing referral form; submissions stored and surfaced in Hub
- Pending referrals >5 days feed System Audit
- Generate SAR (Subject Access Request) PDF

### 25. Safeguarding
- Secure section with password re-verification
- Log safeguarding concerns; end-of-day concern flags auto-create entries here

### 26. Compliance
- Compliance items with due dates; overdue alerts; summary dashboard

### 27. Wellbeing / ABC observations
- ABC (Antecedent-Behaviour-Consequence) observation logging per member
- Concern flagging; wellbeing scoring

### 28. Body map
- Visual body map for recording marks/injuries

### 29. Hub settings
- Org name, logo URL, reply-to email
- Microsoft SSO configuration card
- Dashboard banner (upload image/text)
- Module enable/disable

### 30. Staff directory
- All staff profiles; job titles, contact details; "me" endpoint for own profile

### 31. Vehicles
- Register vehicles; defect reporting; MOT/service date tracking

### 32. Activities & calendar
- Activity log per session; calendar view; upcoming activities widget

### 33. Recognition
- Staff recognition/shoutouts; like system

## Database schema (key tables, current)

`am_members`, `am_member_settings`, `am_attendance`, `am_daily_registers`,
`am_end_of_day`, `am_animals`, `am_welfare_checks`, `am_vet_records`,
`am_daily_monitoring`, `am_transport_runs`, `am_transport_fees` (charge/payment
ledger), `am_comms_log`, `am_email_templates`, `am_leave_requests`,
`am_leave_balances`, `am_timeclock`, `am_payroll_periods`, `am_payroll_entries`,
`am_payroll_rates`, `am_staff_roster` (manual staff w/ NI number), `am_supervisions`,
`am_grants`, `am_in_kind`, `am_member_invoices`, `am_policies`, `am_documents`,
`am_doc_reads`, `am_risk_assessments`, `am_reviews`, `am_announcements`,
`am_push_subscriptions`, `am_notification_prefs`, `am_notification_log` (dedupe),
`am_referrals`, `am_safeguarding`, `am_compliance_items`, `am_abc_observations`,
`am_body_maps`, `am_vehicles`, `am_vehicle_defects`, `am_activities`,
`am_recognition`, `am_settings` (key-value), `am_audit_log`.

## Key UX patterns
- Cards with 12px border radius, subtle shadows
- Traffic-light status colours (green `#10B981`, amber `#F59E0B`, red `#EF4444`)
- Emoji mood selectors throughout
- Modals for all create/edit operations; toast notifications on save
- Skeleton loaders; pull-down refresh on mobile
- Print-friendly branded PDF exports via browser print (no server PDF lib) — logo,
  colour headers, signature lines
- Search/filter on all list views; status pills with coloured backgrounds

## Login page

- **Left panel:** dark blue `#00345C`, Areterra logo, tagline "Animals. People.
  Purpose." in bold chunky text, feature bullets, charity number footer.
- **Right panel:** photo of animals/centre with "Animals. People. Purpose." overlay
  text bottom-left.
- **Form:** white glassmorphic card centred in left panel. Microsoft SSO button at top
  (white card style with Microsoft logo), divider "or sign in with password", then
  username/password fields, remember me, Log In button.
- **Mobile (<900px):** blue background, logo + tagline above white form card.
- **SSO button:** always visible — active if configured, greyed with a
  "set up in Hub Settings" link if not.

## PDF exports

- **Payroll PDF:** landscape A4. "Pay Date - 28th July 2026" heading underlined,
  centred. "Areterra" bold underlined, left-aligned. Table with exact columns:
  Staff Name, N.I.C No., Hourly Rate (£), Total No. Hours, Basic Pay, Holiday Pay (£),
  Total SSP, Mileage, Mileage Pay (£), Total (£). Dark blue total row. Three signature
  lines: Authorised by (pre-filled if set), Date, Received by.
- **Policy PDF:** A4 portrait. Logo top-left. Version + date top-right. Title as H1.
  Rich HTML content. Footer with address.
- **Receipt PDF:** small receipt format. Areterra logo. "Transport Receipt". Date,
  rate, days covered, amount, running balance. Print button.
- **SAR PDF:** member full data extract with Areterra branding.

## Mobile behaviour

- Bottom navigation bar: Today, Dashboard, Members, All Animals, Announcements, More
- Sidebar hidden on mobile, accessed via hamburger
- All two-column grids collapse to single column at <960px
  (`.ah-profile-grid` handles member/animal profile collapse)
- Topbar icons: 44×44px minimum tap targets
- Modals: bottom-sheet style (slides up from bottom, 92vh max)
- All inputs: min 48px height, 16px font to prevent iOS zoom
- `oninput` not `onchange` for live calculation (fires immediately)

## Key technical notes (current implementation)

1. **UNSIGNED integer constraint:** staff roster IDs stored as `1,000,000 + roster_id`
   in payroll tables to avoid clash with real user IDs while satisfying the UNSIGNED
   constraint.
2. **Service worker:** must be served from site root (not plugin directory) for push
   notification scope to work.
3. **DB upgrades:** never destructive. New columns added via
   `ALTER TABLE ADD COLUMN IF NOT EXISTS`, version-gated.
4. **All user input:** escaped with `escapeHtml()` before DOM insertion. APIs sanitise
   on write.
5. **Soft deletes:** all major tables have `deleted_at DATETIME NULL`; queries always
   filter `WHERE deleted_at IS NULL`.
6. **Animal monitoring:** upsert on `(animal_id, monitor_date)` — editing the same
   animal on the same day updates rather than duplicating.
7. **Transport fees:** charges auto-apply on mark-as-collected.
   Balance = sum(payments) − sum(charges). Negative balance = owes.
8. **Email:** From display name "Areterra Team", Reply-To `team@areterra.co.uk`.
   SMTP via Microsoft 365 recommended.
9. **Push VAPID:** P-256 EC keypair. JWT signed with private key. Raw 64-byte r||s
   signature — DER→raw conversion required. `410 Gone` from a push endpoint means
   deactivate that subscription.
10. **Operating days:** Monday=1, Tuesday=2, Thursday=4, Friday=5 (ISO day of week).
    Wednesday and weekends never trigger reminders.

## Integration points
- **postcodes.io** — free UK postcode lookup for addresses
- **Microsoft Graph** — SSO profile
- **QuickBooks** — reference links only (no API yet; future OAuth integration)
- **Google Maps** — navigation deep links from transport
- **Web Push API** — notifications

## Scheduled tasks (cron)
- 12:00 register reminder check
- 14:30 end-of-day reminder check
- Daily compliance/audit sweep
- All deduped via `am_notification_log`

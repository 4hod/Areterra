# Areterra Hub — Standalone Rebuild Plan

Areterra Hub is the operational platform for Areterra (registered charity No. 1196211),
providing day opportunity services for adults with learning disabilities. This document
is the architecture and phasing plan for rebuilding the existing WordPress-plugin
implementation as a standalone application.

The full feature specification (33 modules, database schema, UX patterns, integrations)
lives in [`docs/SPEC.md`](./SPEC.md). This document covers **how** we rebuild it.

---

## 1. Recommended stack

**Laravel 12 monolith with Inertia.js + React, on a single server.**

| Layer | Choice | Why |
|---|---|---|
| Backend | Laravel 12 (PHP 8.3) | The team's existing code is PHP, so migration logic can be ported rather than rewritten. Laravel ships everything the spec needs out of the box: auth, queues, **scheduler** (the 12:00 / 14:30 reminder crons), notifications with multiple channels (push + email fallback), soft deletes, policies. |
| Frontend | Inertia.js + React + TypeScript | Gives the SPA feel the current Hub has (one bundled JS app, bottom nav, no full page reloads) **without** maintaining a separate API + SPA deployment. One repo, one deploy. |
| Styling | Tailwind CSS with brand tokens | CSS custom properties map directly: `--primary: #009DE6`, `--dark: #00345C`, `--accent: #FFD900`, Inter font, 12px card radius, traffic-light status colours. |
| Database | MySQL 8 | Keeping MySQL makes the WordPress data migration a straight table-to-table import. (Postgres is fine too, but buys nothing here and adds friction at migration time.) |
| PWA / Push | Service worker + `laravel-notification-channels/webpush` | Same VAPID web-push model as today, but as a first-class notification channel with the email fallback handled by Laravel's notification routing. |
| Hosting | Single UK-region VPS (e.g. DigitalOcean LON1) managed via Laravel Forge | ~£20–30/month total. One server runs app + queue worker + scheduler + MySQL. UK data residency matters — see §6. |

**Why not Laravel API + Next.js?** Two codebases, two deploys, CORS, token auth, and
duplicated validation — for a system with perhaps a dozen concurrent users. The
separate-frontend architecture pays off at a scale Areterra will never hit. Inertia
gives 95% of the UX benefit at 40% of the complexity. If a native mobile app is ever
genuinely needed, an API can be extracted then — the controllers are already returning
structured data.

## 2. Architecture overview

A **modular monolith**. One Laravel app, with domain modules mirroring the spec:

```
app/
  Domains/
    Members/        # profiles, settings, comms log, reviews, consents, body map, ABC
    Attendance/     # daily register, moods, end-of-day records, Today checklist
    Animals/        # animals, welfare checks, vet records, daily monitoring
    Transport/      # planner (3-phase flow), fee ledger, credit system
    People/         # staff roster, directory, supervisions, leave, timeclock, payroll
    Finance/        # grants, in-kind, invoice tracker (QuickBooks refs)
    Governance/     # policies, documents, risk assessments, compliance, system audit
    Safeguarding/   # gated section, concerns, auto-created from end-of-day flags
    Comms/          # announcements, email composer, templates, recognition
    Platform/       # settings, notifications, push subscriptions, audit log, SSO
```

Each domain owns its models, controllers, policies, and Inertia pages. Cross-domain
communication goes through events (e.g. `EndOfDayConcernFlagged` → safeguarding entry
created + managers notified), which keeps the notification triggers in the spec clean.

**Conventions carried over from the spec, enforced globally:**

- Soft deletes (`deleted_at`) on all major tables — Laravel's `SoftDeletes` trait.
- `created_at` / `updated_at` everywhere (Eloquent default).
- Audit log via `spatie/laravel-activitylog` — replaces the hand-rolled `am_audit_log`.
- API-shaped responses aren't needed (Inertia passes props), but any genuine API
  endpoints (referral form submission) keep the `{ success, data, message }` envelope.

## 3. Data model

The existing `am_*` schema translates almost one-to-one into Laravel migrations —
it's already well-normalised. Notable mappings:

| Existing | Rebuild |
|---|---|
| `am_members` + `am_member_settings` | `members` + `member_settings` (transport toggle, attendance days, key worker) |
| `am_transport_fees` charge/payment ledger | Keep the ledger design — it's correct. Balance is derived, never stored. |
| `am_staff_roster` with `1,000,000 + roster_id` hack | Replaced by a proper `payable_type`/`payable_id` polymorphic relation on payroll entries — roster staff and system users are both first-class payees. |
| `am_notification_log` dedupe | `notification_log` with a unique index on (type, subject, date) — same dedupe guarantee, enforced by the DB. |
| `am_settings` key-value | `settings` key-value via `spatie/laravel-settings`. |

**Sensitive-field encryption:** NHS numbers, diagnoses, support needs, safeguarding
notes, and body-map records use Laravel's `encrypted` cast — encrypted at rest even if
the DB is compromised. This is special-category data under UK GDPR (§6).

## 4. Roles & permissions

`spatie/laravel-permission` maps directly onto the spec:

- **Roles:** administrator, manager, staff, volunteer, safeguarding_lead.
- **Permissions:** the 24 capabilities from the spec (`access_hub`, `view_members`,
  `approve_leave`, `access_safeguarding`, …) become named permissions, assigned to
  roles in a seeder, checked via Laravel policies and Inertia-shared props (so the
  frontend hides what the backend forbids).
- Safeguarding section additionally requires password re-verification
  (`password.confirm` middleware) — matching the current behaviour.

## 5. Cross-cutting decisions

- **Push notifications:** each trigger in the spec becomes a Laravel Notification with
  `webpush` + `mail` channels; per-user/per-category preferences decide routing.
  Service worker served from site root; permission prompt 3s after login, as today.
- **Scheduled tasks:** Laravel scheduler — `registers:remind` at 12:00,
  `endofday:remind` at 14:30 (both guarded by operating-day check Mon/Tue/Thu/Fri,
  scheduled-members check, and dedupe log), plus daily `audit:sweep` that computes the
  System Audit findings.
- **PDFs:** keep the browser-print pattern (branded print stylesheets) — it works and
  costs nothing. Payroll's landscape A4 export gets a dedicated print route.
- **Microsoft SSO:** Laravel Socialite's Microsoft provider replaces the hand-rolled
  OAuth2 flow. Maps by email to existing users; friendly-error redirects preserved.
- **postcodes.io** stays as a client-side fetch for address autocomplete.
- **QuickBooks** stays reference-links-only; the tracker table design is unchanged.
  A future OAuth API integration slots in behind the same model.
- **Email composer:** merge tags rendered server-side, sent via a transactional
  provider (Postmark/SES) with Reply-To `team@areterra.co.uk`; every send auto-logged
  to the member's comms timeline.

## 6. Data protection (non-negotiable given the user base)

This system holds special-category data about vulnerable adults: health data,
safeguarding records, body maps. The rebuild treats this as a design constraint:

- UK-region hosting; daily encrypted off-site backups (`spatie/laravel-backup`).
- Field-level encryption for the sensitive columns listed in §3.
- HTTPS-only, session cookies `Secure`/`HttpOnly`, sensible session lifetime.
- Role-gated access everywhere, safeguarding double-gated, full audit trail.
- Soft deletes + retention policy so SAR requests (module 24) can be answered
  completely; SAR PDF generation reads across all domains for a member.

## 7. Build phases

Ordered so the things that replace paper day-to-day land first, and the current
WordPress Hub keeps running until cutover.

**Phase 0 — Foundation (week 1)**
Repo scaffold, CI (Pest + Pint + type checks), auth, roles/permissions seeder, design
system (tokens, cards, status pills, modals, toasts, skeletons), app shell with bottom
nav (mobile) / sidebar (desktop), PWA manifest + service worker.

**Phase 1 — Daily operations MVP**
Members (profiles, settings, core tabs), Daily Register + arrival moods, End of Day
records (with concern flag → manager notification), Animals + species-grouped welfare
checks + daily monitoring, Dashboard, **Today checklist** (all five auto-check rules).
*At the end of Phase 1 the paper records are replaceable.*

**Phase 2 — Movement & workforce basics**
Transport planner (three-phase flow, undo, missing-address warnings) + fee ledger with
credit system and receipts, Leave management (request → approve, balances, calendar),
Time clock, Announcements, full push-notification wiring incl. the 12:00/14:30
reminder crons.

**Phase 3 — Staff & governance**
Payroll (roster, rate history, periods, live-recalc grid, landscape PDF), Supervisions
& appraisals, Staff directory, Policies (editor + 5 templates), Document library with
read confirmations, Risk assessments, Member reviews, **System Audit** (all seven
health checks — cheap to build now that every data source exists).

**Phase 4 — Finance & remaining modules**
Grants + in-kind, Invoice tracker, Safeguarding section, Compliance, ABC observations,
Body map, Vehicles + defects, Activities & calendar, Recognition, SAR/referral public
form, Microsoft SSO, Hub Settings, notification preferences page.

**Phase 5 — Migration & cutover**
Artisan import command reading the WordPress `am_*` tables (direct DB connection or
SQL dump) with per-table mappers and dry-run mode; import verification report
(row counts, spot checks against the 12 members / 23 animals / known roster);
parallel-run week with staff entering data in both; DNS/bookmark switch; WordPress
plugin set read-only, then archived.

## 8. Testing strategy

- **Pest** feature tests per module; model factories for everything.
- The business rules with sharp edges get dedicated tests: Today-checklist auto-check
  logic, transport credit deduction, payroll calculation + roster/user polymorphism,
  reminder dedupe + operating-day guards, capability matrix per role.
- **Playwright** smoke suite for the golden path: log in → register members → welfare
  checks → end of day → checklist fully green.

## 9. What stays deliberately simple

- No microservices, no separate API tier, no Kubernetes — one server, one app.
- No server-side PDF library — print stylesheets.
- No custom form-builder rewrite until a real need appears (`build_forms` capability
  reserved).
- QuickBooks stays manual-reference; API integration is a later, isolated project.

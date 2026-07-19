# Areterra Hub

Operational platform for [Areterra](https://areterra.co.uk) — a registered charity
(No. 1196211) providing day opportunity services for adults with learning disabilities
through animal care, horticulture and practical skills.

*Animals. People. Purpose.*

This repository is the **standalone rebuild** of the Hub (Laravel 12 + Inertia.js +
React), replacing the previous WordPress-plugin implementation.

## Documentation

- [`docs/SPEC.md`](docs/SPEC.md) — full functional specification (33 modules),
  compiled from the production system
- [`docs/REBUILD_PLAN.md`](docs/REBUILD_PLAN.md) — architecture, stack decisions,
  and phased build plan

## Status

**Phases 0–3 built.**

- *Phase 0–1:* auth + role/capability system, design system and app shell
  (sidebar / bottom nav, PWA), Dashboard, Today checklist (all five auto-check
  rules), Morning Register with arrival moods, End of Day records with concern
  flagging, Members, Animals with species-grouped welfare checks, daily
  monitoring and vet records.
- *Phase 2:* Transport planner (three-phase flow) with the £5/day cash fee
  ledger and credit system, Leave management with manager approval and
  balances, Time Clock, Announcements, and web-push notifications with email
  fallback, per-user preferences, and the 12:00 / 14:30 reminder crons
  (operating days Mon/Tue/Thu/Fri, deduped once per day).
- *Phase 3:* Payroll (roster with rate history, pay periods, live-recalc grid,
  landscape A4 print sheet), Supervisions & appraisals, Staff Directory,
  Policies with five pre-filled templates and branded print, Document library
  with read confirmations, Risk assessments (likelihood × severity), Member
  reviews, and the System Audit health checks.

Next: Phase 4 — finance & grants, invoice tracker, safeguarding section,
compliance, ABC observations, body map, vehicles, activities, recognition,
SAR/referral form, Microsoft SSO, Hub settings.

The scheduler needs a cron entry in production (`php artisan schedule:run`
every minute) for the reminder notifications; VAPID keys for push are generated
with `php artisan webpush:vapid` into `.env`.

## Getting started

Requirements: PHP 8.3+, Composer, Node 22+.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm run build          # or: npm run dev
php artisan serve
```

Log in with one of the seeded dev users (password `password` — **dev only**):
`ekilburn@areterra.co.uk` (administrator), `lucy@areterra.co.uk` (staff),
`vanessa@areterra.co.uk` (staff).

Run the tests with `php artisan test`.

## Stack notes

- **SQLite** for local dev; production targets MySQL 8 (`DB_CONNECTION=mysql`).
- Sensitive member fields (NHS number, diagnoses, support needs, emergency
  contacts) are **encrypted at rest** via Eloquent casts.
- Roles/capabilities live in [`config/capabilities.php`](config/capabilities.php);
  gates are defined from it in `AppServiceProvider`.
- The service worker (`public/ah-sw.js`) is served from the site root so push
  notification scope covers the whole app (Phase 2).

# Areterra Hub go-live checklist

This checklist must be signed off before real member records are used by the
whole staff team. Record the person and date beside every completed item.

## Technical release gate

- [ ] The deployment branch is protected: pull request required, force pushes
      and deletion blocked, and both `tests` and `frontend` CI checks required.
- [ ] `php artisan hub:security-check` passes in production.
- [ ] Laravel Cloud scheduler is enabled and `hub:run-automations` has a recent
      successful run.
- [ ] Scale to zero is disabled for the production staff environment.
- [ ] Private object storage is attached and `FILESYSTEM_DISK=s3`.
- [ ] `hub:migrate-private-media --source=local --target=s3` reports no missing
      files; the `--commit` run is followed by a successful download check.
- [ ] Database backups are retained for 30 days and a restore has been tested.
- [ ] A separate secure recovery copy of `APP_KEY` is held by two authorised
      administrators.
- [ ] Notification delivery is tested in-app, by push, and by email (when email
      is enabled) using a real staff account.

## People and access

- [ ] At least two administrators can sign in and manage permissions.
- [ ] Every staff member has completed a real Microsoft sign-in with MFA on the
      phone they will use. Personal Gmail/Hotmail identities have been tested,
      not merely assumed to work.
- [ ] Access follows least privilege. Volunteers cannot see medical, finance or
      safeguarding information unless their role genuinely requires it.
- [ ] The joiner, role-change, leaver and lost-phone processes have named owners.
- [ ] Staff know how to report a suspected privacy/security incident immediately.

## Records and continuity

- [ ] Member identities, allergies, medicines, emergency contacts, transport
      arrangements and attendance days have been reconciled against source
      records by a second person.
- [ ] The fire register and transport lists have been checked against a complete
      mock day, including a late arrival, absence and early departure.
- [ ] Paper register, medication, emergency-contact and safeguarding fallbacks
      are available for loss of internet or portal access.
- [ ] One controlled pilot shift has been completed by two or three staff on
      actual phones and the following morning's records have been checked.

## Data protection and governance

- [ ] DPIA, privacy notice, record of processing, lawful bases and Article 9
      condition are documented for member health and support information.
- [ ] Retention/deletion rules are agreed for care records, photos, incidents,
      safeguarding, staff records, audit logs and backups.
- [ ] Supplier/data-processing terms for Laravel Cloud, Microsoft and the email
      provider are recorded and approved.
- [ ] The personal-data breach plan identifies the decision maker, evidence to
      preserve, affected-person communication and the ICO reporting route.
- [ ] A named person owns first-day support, daily backup checks and monthly
      access reviews.


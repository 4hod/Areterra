# Deploying the Hub to shared (cPanel) hosting

The Hub runs happily on ordinary shared hosting — the same kind that runs
areterra.co.uk. You need three things from the host: **PHP 8.3 or newer**,
**MySQL**, and **cron jobs**. Almost every cPanel host has all three.

The server never runs Composer or npm: the GitHub Action
(**Actions → "Build deploy package" → Run workflow**) produces a ZIP with
everything pre-built. Download the `areterra-hub` artifact from the run page.

## 1 · Check PHP version

cPanel → **MultiPHP Manager** (or "Select PHP Version"). The domain must be
on **PHP 8.3 or 8.4**. If only 8.2 or older is offered, contact the host —
or use a managed platform instead (see README).

## 2 · Create the subdomain

cPanel → **Domains / Subdomains** → create `hub.areterra.co.uk`.
Set its **document root** to:

```
hub-app/public
```

(cPanel will create the folder; the `/public` suffix is essential — it keeps
the application code, `.env`, and database outside the web root.)

## 3 · Create the database

cPanel → **MySQL Databases**:

1. Create a database (e.g. `areterra_hub`).
2. Create a user with a strong password.
3. Add the user to the database with **ALL PRIVILEGES**.
4. Note all three values — they go in `.env`.

## 4 · Upload the app

1. cPanel → **File Manager** → your home directory (the level *above*
   `public_html`).
2. Upload the ZIP from the GitHub Action and **Extract** it so the app lives
   at `~/hub-app` (i.e. `hub-app/artisan` exists).
3. If the subdomain's document root from step 2 doesn't already point at
   `~/hub-app/public`, adjust it now (Domains → manage → Document Root).

## 5 · Configure `.env`

In File Manager, inside `hub-app`, copy `.env.example` → `.env` and edit
(File Manager → Edit). Settings that matter:

```ini
APP_NAME="Areterra Hub"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hub.areterra.co.uk

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=<from step 3>
DB_USERNAME=<from step 3>
DB_PASSWORD=<from step 3>

# Microsoft 365 mail (or ask the host for their SMTP details)
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_SCHEME=null
MAIL_USERNAME=team@areterra.co.uk
MAIL_PASSWORD=<mailbox or app password>
MAIL_FROM_ADDRESS=team@areterra.co.uk
MAIL_FROM_NAME="Areterra Team"
```

Leave `APP_KEY` and the VAPID keys empty — the next step fills them.

## 6 · One-time setup commands

cPanel → **Terminal** (most hosts have it; if not, run each line as a
one-off cron job):

```bash
cd ~/hub-app
php artisan key:generate --force
php artisan migrate --seed --force
php artisan storage:link
php artisan webpush:vapid          # then paste the two printed keys into .env
php artisan config:cache && php artisan route:cache
```

## 7 · The scheduler cron

cPanel → **Cron Jobs** → add, running **every minute**
(`* * * * *`):

```bash
php /home/YOUR_CPANEL_USER/hub-app/artisan schedule:run >> /dev/null 2>&1
```

This powers the 12:00 register and 14:30 end-of-day reminders.

## 8 · HTTPS

cPanel hosts normally issue a free certificate automatically (AutoSSL)
within an hour of the subdomain existing. Check **SSL/TLS Status** and
"Run AutoSSL" if `hub.areterra.co.uk` isn't green yet.

## 9 · First login — immediately

Log in at `https://hub.areterra.co.uk` as `ekilburn@areterra.co.uk` /
`password` and **change every seeded password before inviting anyone else**
(the three seeded users all start with `password`).

Then in **Hub Settings**, set the reply-to address and, if you want
Microsoft sign-in, the Azure Client/Tenant IDs (the settings card shows the
callback URI to paste into Azure).

## 10 · Backups

cPanel → **Backup**: check the host runs automatic backups that include
MySQL. If not, schedule a weekly manual **Download a MySQL Database Backup**
— this system will hold sensitive personal data, so backups are not
optional.

## Updating later

Run the GitHub Action again, download the new ZIP, and in File Manager
extract it over `~/hub-app` (your `.env`, `storage/`, and the database are
untouched — the ZIP deliberately contains no `.env`). Then in Terminal:

```bash
cd ~/hub-app && php artisan migrate --force && php artisan config:cache && php artisan route:cache
```

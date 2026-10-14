# Deploying MeepleMania Mini to cPanel (no SSH)

Everything here is done with **cPanel's web interface** plus your PC. Composer and the frontend build run on your PC; anything that needs `php artisan` runs as a **cron job** (add it, wait a minute or two, read the log, delete it).

---

## 0. Fill these in first

Write your values here; every command below uses them.

| Placeholder | What it is | Where to find it | Your value |
|---|---|---|---|
| `CPUSER` | Your cPanel username | Top-right of cPanel, or the home path `/home/CPUSER` | |
| `PHP` | Full path to PHP 8.4 (or 8.3) | Step 1 below | e.g. `/opt/cpanel/ea-php84/root/usr/bin/php` |
| `DOMAIN` | The site address | Your domain | e.g. `play.example.mw` |
| DB name / user / password | MySQL database | Step 2 below | |
| Mailbox + password | Sends reset-password emails | Step 6 below | e.g. `no-reply@example.mw` |

The app will live in **`/home/CPUSER/meeplemania`** (outside `public_html`, so `.env` is never downloadable).

---

## 1. Check PHP and find its path

1. **cPanel → MultiPHP Manager**: set your domain to **PHP 8.4** (8.3 also works, 8.4 matches the PC build).
2. **cPanel → Select PHP Version / PHP Extensions** (if your host shows it): make sure these are on: `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `ctype`, `fileinfo`, `tokenizer`, `xml`, `curl`, `zip`, `intl`.
3. **cPanel → Cron Jobs**: add this **one-off** job, set to *Once Per Minute* (`* * * * *`):

   ```
   { ls -d /opt/cpanel/ea-php*/root/usr/bin/php; echo ---; /usr/local/bin/php -v; } > /home/CPUSER/php-check.txt 2>&1
   ```

4. After a minute, open **File Manager → `php-check.txt`** in your home folder. Pick the line ending in `ea-php84/root/usr/bin/php` (or `ea-php83`). That is your **`PHP`** value. If there are no `ea-php` lines, use `/usr/local/bin/php` as long as the version printed is 8.3 or higher.
5. **Delete the cron job** and the `php-check.txt` file.

---

## 2. Create the database

1. **cPanel → phpMyAdmin**: on the home screen check *Server version*. You need **MySQL 8.0+** or **MariaDB 10.2+** (leaderboards use `ROW_NUMBER()`).
2. **cPanel → MySQL Database Wizard**:
   - Create a database, e.g. `meeple` → cPanel names it `CPUSER_meeple`.
   - Create a user, e.g. `meeple` → `CPUSER_meeple`, with a strong password.
   - Give the user **ALL PRIVILEGES** on the database.
3. Note the full database name, user name and password.

---

## 3. Build the upload package (on your PC)

Open **PowerShell** (not Git Bash; this uses Windows' built-in `tar.exe` to make a `.zip`):

```powershell
cd C:\projects\herd-projects\meeplemania-memorymaster

npm run build
composer install --no-dev --optimize-autoloader

tar.exe -a -c -f ..\meeplemania-deploy.zip `
  --exclude=./node_modules --exclude=./.git --exclude=./tests --exclude=./.env `
  --exclude=./public/hot --exclude=./uibuilder --exclude=./.idea --exclude=./.vscode `
  --exclude=./.phpunit.cache --exclude="./storage/logs/*.log" `
  --exclude="./bootstrap/cache/*.php" --exclude="./storage/framework/views/*.php" `
  --exclude="./storage/framework/sessions/*" --exclude="./storage/framework/cache/data/*" `
  --exclude="./storage/framework/testing" .

composer install    # puts your local dev tools back
```

This creates `C:\projects\herd-projects\meeplemania-deploy.zip` (about 20 MB).

Also generate the app's secret key and **copy the output** (it starts with `base64:`):

```powershell
php artisan key:generate --show
```

> Do **not** run `php artisan optimize` on your PC before packaging. It would bake Windows paths into the cache. The excludes above also strip any cached files.

---

## 4. Upload and extract

1. **cPanel → File Manager** → go to your home folder (`/home/CPUSER`).
2. **+ Folder** → create `meeplemania`, open it.
3. **Upload** → `meeplemania-deploy.zip`. Wait for 100%.
4. Right-click the zip → **Extract** → into `/home/CPUSER/meeplemania`.
5. Check you can see `artisan`, `app`, `public`, `vendor`, `.env.example` directly inside `meeplemania` (turn on *Settings → Show Hidden Files* to see dot-files). If everything landed in a sub-folder, move it up one level.
6. Delete the zip.
7. Permissions: right-click **`storage`** → *Change Permissions* → `755` and tick *recurse* if offered; do the same for **`bootstrap/cache`**. (On most cPanel hosts PHP runs as your user, so 755 is enough. Use 775 only if you later see "permission denied" in the log.)

---

## 5. Create `.env`

In File Manager inside `meeplemania`: copy **`.env.example`** → name the copy **`.env`**, then **Edit** it. Change these lines (leave the others as they are):

```dotenv
APP_NAME="MeepleMania Mini"
APP_ENV=production
APP_KEY=base64:PASTE-THE-KEY-FROM-STEP-3
APP_DEBUG=false
APP_URL=https://DOMAIN

LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=CPUSER_meeple
DB_USERNAME=CPUSER_meeple
DB_PASSWORD=your-database-password

SESSION_SECURE_COOKIE=true

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.DOMAIN-OR-YOUR-MAIL-HOST
MAIL_PORT=465
MAIL_USERNAME=no-reply@your-domain
MAIL_PASSWORD=mailbox-password
MAIL_FROM_ADDRESS="no-reply@your-domain"
MAIL_FROM_NAME="${APP_NAME}"
```

Notes:
- `APP_URL` must be the real `https://` address. Password-reset links are built from it.
- `APP_DEBUG=false` is important: `true` shows passwords and keys on error pages.
- Never upload your PC's `.env`. Its key and database settings are for your machine.
- **After any later edit to `.env`, run the "Refresh caches" cron (task C below)**, because the settings are cached.

---

## 6. Email account (for reset-password emails)

1. **cPanel → Email Accounts → Create**: `no-reply@your-domain` with a strong password.
2. Click **Connect Devices** next to it. Copy the **Outgoing (SMTP) server** and **SSL port** (usually `465`) into `MAIL_HOST` / `MAIL_PORT` in `.env`.
   - If only port `587` is offered: set `MAIL_PORT=587` and `MAIL_SCHEME=smtp`.

---

## 7. Point the domain at the app

Pick **one** option.

### Option A: subdomain or addon domain (recommended, no code edits)

1. **cPanel → Domains** (or *Subdomains* / *Addon Domains* on older cPanel).
2. Create or **Manage** the domain and set **Document Root** to:

   ```
   meeplemania/public
   ```

3. Save. Done.

### Option B: your main domain (document root is fixed to `public_html`)

1. Back up anything already in `public_html` (download or move it into a folder like `old_site`).
2. In File Manager open `meeplemania/public`, **select all** (including `.htaccess`), **Copy** to `/home/CPUSER/public_html`.
3. Edit **`public_html/index.php`** and replace its contents with:

   ```php
   <?php

   use Illuminate\Foundation\Application;
   use Illuminate\Http\Request;

   define('LARAVEL_START', microtime(true));

   $base = __DIR__.'/../meeplemania';

   if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
       require $maintenance;
   }

   require $base.'/vendor/autoload.php';

   /** @var Application $app */
   $app = require_once $base.'/bootstrap/app.php';
   $app->usePublicPath(__DIR__);

   $app->handleRequest(Request::capture());
   ```

4. **On every update** you must copy `meeplemania/public/build` into `public_html/build` again (see section 11). With Option A you don't.

---

## 8. HTTPS

**cPanel → SSL/TLS Status** → tick your domain → **Run AutoSSL**. Wait until it shows a valid certificate.

Do this **before** signing in: `SESSION_SECURE_COOKIE=true` means sign-in only works over `https://`.

---

## 9. One-time setup (cron)

**cPanel → Cron Jobs → Add New Cron Job**, *Once Per Minute*, and paste **task A** (from the table in section 10, with `CPUSER` and `PHP` filled in). After **two minutes**:

1. Open `meeplemania/storage/logs/cron.log` in File Manager. You should see the migrations listed as `DONE` and `Caching ... DONE` lines, and **no** `ERROR`.
2. **Delete the cron job.** (It is safe if it ran twice; it only seeds missing data.)
3. Visit `https://DOMAIN`. The arena should load.

### Make yourself an admin

1. On the site, **Create account** with your email (`ronaldfredsikwese@gmail.com`), username, district and password.
2. Add **task B** as a one-off cron, wait a minute, check `cron.log` says `... is now an administrator.`, then **delete the cron job**.
3. Sign in at **`https://DOMAIN/admin/login`**. Further admins (up to 5 in total) can now be created from **Admin → Admins**, with no cron needed.

### Turn on the scheduler (permanent)

Add **task S** and **leave it running forever**. It closes idle game runs, creates each day's Try Once challenges, takes leaderboard snapshots, and deletes expired reset links.

---

## 10. All cron tasks

Replace **`CPUSER`** and **`PHP`** (e.g. `/opt/cpanel/ea-php84/root/usr/bin/php`) in every line. Set each to **Once Per Minute** (`* * * * *`).
Every one-off task appends to `meeplemania/storage/logs/cron.log`. Read it there, then **delete the one-off job**.

| Task | Keep? | When to use | Command |
|---|---|---|---|
| **S: Scheduler** | **Keep forever** | Always | `PHP /home/CPUSER/meeplemania/artisan schedule:run >> /dev/null 2>&1` |
| **A: First-time setup** | One-off | First deploy only | `cd /home/CPUSER/meeplemania && { date; PHP artisan migrate --seed --force && PHP artisan optimize; } >> storage/logs/cron.log 2>&1` |
| **B: Make admin** | One-off | Promote an existing account (max 5 admins) | `cd /home/CPUSER/meeplemania && { date; PHP artisan games:make-admin ronaldfredsikwese@gmail.com; } >> storage/logs/cron.log 2>&1` |
| **C: Refresh caches** | One-off | After editing `.env` | `cd /home/CPUSER/meeplemania && { date; PHP artisan optimize:clear && PHP artisan optimize; } >> storage/logs/cron.log 2>&1` |
| **D: After an update** | One-off | After uploading a new version | `cd /home/CPUSER/meeplemania && { date; PHP artisan migrate --force && PHP artisan optimize:clear && PHP artisan optimize; } >> storage/logs/cron.log 2>&1` |
| **M1: Maintenance on** | One-off | Before uploading a big update (optional) | `cd /home/CPUSER/meeplemania && { date; PHP artisan down --retry=60; } >> storage/logs/cron.log 2>&1` |
| **M2: Maintenance off** | One-off | After task D has finished | `cd /home/CPUSER/meeplemania && { date; PHP artisan up; } >> storage/logs/cron.log 2>&1` |
| **H: Health check** | One-off | Troubleshooting: shows PHP version, environment, cache, DB, mail driver | `cd /home/CPUSER/meeplemania && { date; PHP artisan about; PHP artisan migrate:status; } >> storage/logs/cron.log 2>&1` |
| **X: Clear caches only** | One-off | Something looks stale or broken after a change | `cd /home/CPUSER/meeplemania && { date; PHP artisan optimize:clear; } >> storage/logs/cron.log 2>&1` |

> Some hosts only allow every 5 or 15 minutes. That is fine for task S. Runs and challenges are just tidied up a little later.
> If your host emails you cron output, task S's `>> /dev/null 2>&1` keeps it quiet.

Example with values filled in (`CPUSER` = `meepleco`, PHP 8.4):

```
/opt/cpanel/ea-php84/root/usr/bin/php /home/meepleco/meeplemania/artisan schedule:run >> /dev/null 2>&1
```

---

## 11. Updating to a new version

1. **On your PC:** commit your changes, then repeat **section 3** (build + package). You do not need a new `APP_KEY`.
2. *(Optional)* Run **task M1** to show a "back soon" page.
3. **File Manager → `meeplemania`** → **Upload** the new zip → **Extract** into `/home/CPUSER/meeplemania`, overwriting files. The zip contains no `.env` and no logs, so your settings and data are untouched. Delete the zip.
4. **Option B only:** copy `meeplemania/public/build` into `public_html/build` (overwrite). Also copy any other changed files from `meeplemania/public` except `index.php`.
5. Run **task D**, check `cron.log`, delete the job.
6. If you used M1, run **task M2**.

Never delete or replace `/home/CPUSER/meeplemania/.env` or `storage/`.

---

## 12. Check everything works

- [ ] `https://DOMAIN` loads with a padlock; the header says **MeepleMania Mini**.
- [ ] Register a test player → choose a district → play a game → it appears on **Ranks** (country-wide, region and district).
- [ ] `https://DOMAIN/admin/login` → sign in → **Admin → Admins** shows your sign-in in the activity log.
- [ ] **Forgot password** on the sign-in page → the email arrives and the link starts with `https://DOMAIN/reset-password/<username>/...`.
- [ ] After 10 minutes, `storage/logs/laravel.log` has no new errors (no file at all is fine).

---

## 13. Optional: Cloudflare in front

You can point your domain's nameservers at Cloudflare (free plan) for extra speed and protection. The app still runs on cPanel.

- **SSL/TLS mode: Full (strict)**, set only after AutoSSL has issued the certificate (section 8). *Flexible* causes endless redirects.
- Leave caching on its defaults (it caches the `/build` files, not signed-in pages).
- **Ask for one small code change before switching**: the app must be told to trust Cloudflare's addresses. Otherwise the admin activity log shows Cloudflare's IPs instead of real visitors', and rate limits (e.g. sign-in attempts) are shared between everyone.

---

## 14. Troubleshooting

| Symptom | Fix |
|---|---|
| **500 / blank page** | Open `meeplemania/storage/logs/laravel.log` (newest error at the bottom). Common causes: wrong DB details in `.env` → fix and run task C; PHP below 8.3 → MultiPHP Manager. |
| **"Your Composer dependencies require a PHP version ≥ 8.4"** | Set the domain to PHP 8.4 in MultiPHP Manager and use the `ea-php84` path in cron. |
| **Page loads without styling** | The `build` folder is missing from the document root. Option A: check `meeplemania/public/build/manifest.json` exists. Option B: copy `meeplemania/public/build` to `public_html/build`. Also make sure there is **no** `public/hot` file. |
| **Can't stay signed in / 419 Page Expired** | Use `https://` (not `http://`); `APP_URL` must match the address exactly; run task C. |
| **`.env` changes ignored** | Settings are cached: run task C. |
| **Reset emails never arrive** | Check `MAIL_*` against *Connect Devices*; run task C; look for `Swift`/`Mailer` errors in `laravel.log`; check spam. |
| **Cron did nothing / no `cron.log`** | Wrong `PHP` path or `CPUSER`: redo section 1. Make sure the job is set to run every minute and wait at least 2 minutes. |
| **"Permission denied" in the log** | Set `storage` and `bootstrap/cache` to 775 (recursive). |
| **Leaderboard SQL error mentioning `OVER` / `ROW_NUMBER`** | The database is too old: you need MySQL 8+ or MariaDB 10.2+. Ask the host to upgrade. |
